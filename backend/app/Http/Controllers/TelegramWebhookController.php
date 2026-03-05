<?php

namespace App\Http\Controllers;

use App\Jobs\PmAgentJob;
use App\Jobs\UxAgentJob;
use App\Models\Task;
use App\Services\StateMachineService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        // Always return 200 to prevent Telegram retry loops
        if (! $this->verifyRequest($request)) {
            Log::warning('Telegram webhook: unauthorized request');
            return response()->json(['ok' => true]);
        }

        $payload = $request->all();

        if (isset($payload['callback_query'])) {
            $this->handleCallbackQuery($payload['callback_query']);
        } elseif (isset($payload['message']['text'])) {
            $this->handleMessage($payload['message']);
        }

        return response()->json(['ok' => true]);
    }

    private function verifyRequest(Request $request): bool
    {
        $secret = config('app.telegram_webhook_secret');

        // If no secret configured, skip verification (dev mode)
        if (empty($secret)) {
            return $this->verifyChatId($request);
        }

        $headerSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($headerSecret !== $secret) {
            return false;
        }

        return $this->verifyChatId($request);
    }

    private function verifyChatId(Request $request): bool
    {
        $configChatId = config('app.telegram_chat_id');
        if (empty($configChatId)) {
            return false;
        }

        $payload = $request->all();

        // Extract chat_id from callback_query or message
        $chatId = $payload['callback_query']['message']['chat']['id']
            ?? $payload['message']['chat']['id']
            ?? null;

        return (string) $chatId === (string) $configChatId;
    }

    private function handleCallbackQuery(array $callbackQuery): void
    {
        $telegram = app(TelegramService::class);
        $callbackId = $callbackQuery['id'];
        $data = $callbackQuery['data'] ?? '';
        $messageId = $callbackQuery['message']['message_id'] ?? null;
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? '');

        // Parse callback data: pm_approve:{taskId} or pm_revise:{taskId}
        if (preg_match('/^pm_approve:(\d+)$/', $data, $matches)) {
            $taskId = (int) $matches[1];
            $this->handlePmApprove($taskId, $callbackId, $messageId, $telegram);
        } elseif (preg_match('/^pm_revise:(\d+)$/', $data, $matches)) {
            $taskId = (int) $matches[1];
            $this->handlePmRevise($taskId, $callbackId, $messageId, $chatId, $telegram);
        } else {
            $telegram->answerCallbackQuery($callbackId, 'Unknown action');
        }
    }

    private function handlePmApprove(int $taskId, string $callbackId, ?int $messageId, TelegramService $telegram): void
    {
        $telegram->answerCallbackQuery($callbackId, 'Processing...');

        try {
            DB::transaction(function () use ($taskId, $messageId, $telegram) {
                $task = Task::where('id', $taskId)->lockForUpdate()->first();

                if (! $task || $task->status !== 'pm_review') {
                    if ($messageId) {
                        $telegram->editMessageText($messageId,
                            "⚠️ Task #{$taskId} is no longer in PM review state.\nCurrent status: " . ($task->status ?? 'not found'));
                    }
                    return;
                }

                $sm = app(StateMachineService::class);
                $sm->transition($taskId, 'ux_processing');
                UxAgentJob::dispatch($taskId);

                if ($messageId) {
                    $telegram->editMessageText($messageId,
                        "✅ <b>Task #{$taskId} Approved!</b>\nPipeline continuing to UX design...");
                }

                // Clear cached message ID
                Cache::forget("telegram_pm_review_msg:{$taskId}");
            });
        } catch (\Throwable $e) {
            Log::error("Telegram PM approve failed: {$e->getMessage()}");
            if ($messageId) {
                $telegram->editMessageText($messageId,
                    "❌ Error approving Task #{$taskId}: {$e->getMessage()}");
            }
        }
    }

    private function handlePmRevise(int $taskId, string $callbackId, ?int $messageId, string $chatId, TelegramService $telegram): void
    {
        $task = Task::find($taskId);

        if (! $task || $task->status !== 'pm_review') {
            $telegram->answerCallbackQuery($callbackId, 'Task is no longer in PM review state.');
            if ($messageId) {
                $telegram->editMessageText($messageId,
                    "⚠️ Task #{$taskId} is no longer in PM review state.");
            }
            return;
        }

        $telegram->answerCallbackQuery($callbackId, 'Please type your feedback...');

        // Store taskId in cache so next message from this chat is treated as revision
        Cache::put("telegram_revision:{$chatId}", $taskId, now()->addHour());

        if ($messageId) {
            $telegram->editMessageText($messageId,
                "✏️ <b>Revising Task #{$taskId}</b>\nPlease type your feedback in the next message...");
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim($message['text'] ?? '');

        if (empty($text) || empty($chatId)) {
            return;
        }

        // Check if there's a pending revision for this chat
        $taskId = Cache::pull("telegram_revision:{$chatId}");

        if (! $taskId) {
            // No pending revision — ignore the message
            return;
        }

        $telegram = app(TelegramService::class);

        try {
            DB::transaction(function () use ($taskId, $text, $telegram) {
                $task = Task::where('id', $taskId)->lockForUpdate()->first();

                if (! $task || $task->status !== 'pm_review') {
                    $telegram->send("⚠️ Task #{$taskId} is no longer in PM review state.");
                    return;
                }

                // Append user revision to pm_messages
                $messages = $task->pm_messages ?? [];
                $messages[] = [
                    'role'       => 'user',
                    'content'    => $text,
                    'source'     => 'telegram',
                    'created_at' => now()->toISOString(),
                ];
                $task->pm_messages = $messages;
                $task->save();

                // Transition back to pm_processing and re-run PM agent
                $sm = app(StateMachineService::class);
                $sm->transition($taskId, 'pm_processing');
                PmAgentJob::dispatch($taskId);

                $telegram->send("🔄 <b>Revision received for Task #{$taskId}</b>\nPM is re-analyzing with your feedback...");
            });
        } catch (\Throwable $e) {
            Log::error("Telegram revision failed: {$e->getMessage()}");
            $telegram->send("❌ Error processing revision for Task #{$taskId}: {$e->getMessage()}");
        }
    }
}
