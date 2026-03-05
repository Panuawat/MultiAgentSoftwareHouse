<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $baseUrl;
    private string|null $chatId;

    public function __construct()
    {
        $token = config('app.telegram_bot_token');
        $this->baseUrl = "https://api.telegram.org/bot{$token}";
        $this->chatId  = config('app.telegram_chat_id');
    }

    public function send(string $message, string|null $chatId = null): bool
    {
        $target = $chatId ?? $this->chatId;

        if (empty($target) || empty(config('app.telegram_bot_token'))) {
            return false; // Silently skip if not configured
        }

        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/sendMessage", [
                'chat_id'    => $target,
                'text'       => $message,
                'parse_mode' => 'HTML',
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram notification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send message with inline keyboard buttons.
     * Returns full Telegram response array or false on failure.
     */
    public function sendWithInlineKeyboard(string $message, array $keyboard, string|null $chatId = null): array|false
    {
        $target = $chatId ?? $this->chatId;

        if (empty($target) || empty(config('app.telegram_bot_token'))) {
            return false;
        }

        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/sendMessage", [
                'chat_id'      => $target,
                'text'         => $message,
                'parse_mode'   => 'HTML',
                'reply_markup' => ['inline_keyboard' => $keyboard],
            ]);

            return $response->successful() ? $response->json() : false;
        } catch (\Throwable $e) {
            Log::warning('Telegram sendWithInlineKeyboard failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Edit an existing message's text.
     */
    public function editMessageText(int $messageId, string $text, string|null $chatId = null): bool
    {
        $target = $chatId ?? $this->chatId;

        if (empty($target) || empty(config('app.telegram_bot_token'))) {
            return false;
        }

        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/editMessageText", [
                'chat_id'    => $target,
                'message_id' => $messageId,
                'text'       => $text,
                'parse_mode' => 'HTML',
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram editMessageText failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Acknowledge a callback query (required by Telegram after button press).
     */
    public function answerCallbackQuery(string $callbackId, string $text = ''): bool
    {
        try {
            $response = Http::timeout(5)->post("{$this->baseUrl}/answerCallbackQuery", [
                'callback_query_id' => $callbackId,
                'text'              => $text,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram answerCallbackQuery failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send PM review notification with Approve/Revise inline buttons.
     */
    public function notifyPmReview(int $taskId, string $title, array|string $pmAnalysis): array|false
    {
        $header = "📋 <b>PM Analysis for Task #{$taskId}</b>\n";
        $header .= "📝 {$title}\n\n";

        $body = $this->formatPmAnalysis($pmAnalysis);

        // Telegram message limit is 4096 chars; keep under 3800 to leave room for header
        $maxBody = 3800 - mb_strlen($header);
        if (mb_strlen($body) > $maxBody) {
            $body = mb_substr($body, 0, $maxBody - 20) . "\n\n<i>[truncated]</i>";
        }

        $keyboard = [
            [
                ['text' => '✅ Approve', 'callback_data' => "pm_approve:{$taskId}"],
                ['text' => '✏️ Revise', 'callback_data' => "pm_revise:{$taskId}"],
            ],
        ];

        return $this->sendWithInlineKeyboard($header . $body, $keyboard);
    }

    /**
     * Format PM analysis arrays into readable Telegram HTML.
     */
    private function formatPmAnalysis(array|string $analysis): string
    {
        if (is_string($analysis)) {
            return $analysis;
        }

        $sections = [];

        if (isset($analysis['features']) && is_array($analysis['features'])) {
            $items = array_slice($analysis['features'], 0, 10);
            $list = implode("\n", array_map(fn($f) => "  • {$f}", $items));
            if (count($analysis['features']) > 10) {
                $list .= "\n  <i>... and " . (count($analysis['features']) - 10) . " more</i>";
            }
            $sections[] = "<b>Features:</b>\n{$list}";
        }

        if (isset($analysis['requirements']) && is_array($analysis['requirements'])) {
            $items = array_slice($analysis['requirements'], 0, 10);
            $list = implode("\n", array_map(fn($r) => "  • {$r}", $items));
            if (count($analysis['requirements']) > 10) {
                $list .= "\n  <i>... and " . (count($analysis['requirements']) - 10) . " more</i>";
            }
            $sections[] = "<b>Requirements:</b>\n{$list}";
        }

        if (isset($analysis['constraints']) && is_array($analysis['constraints'])) {
            $items = array_slice($analysis['constraints'], 0, 10);
            $list = implode("\n", array_map(fn($c) => "  • {$c}", $items));
            if (count($analysis['constraints']) > 10) {
                $list .= "\n  <i>... and " . (count($analysis['constraints']) - 10) . " more</i>";
            }
            $sections[] = "<b>Constraints:</b>\n{$list}";
        }

        if (empty($sections)) {
            // Fallback: dump as JSON if structure is non-standard
            return "<pre>" . e(json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        }

        return implode("\n\n", $sections);
    }

    public function notifyTaskCompleted(int $taskId, string $title, float $costUsd = 0): void
    {
        $cost = $costUsd > 0 ? "\n💰 API Cost: ~\${$costUsd}" : '';
        $this->send(
            "✅ <b>Task Completed!</b>\n" .
            "🆔 Task #{$taskId}: {$title}{$cost}\n" .
            "🔗 Dashboard: http://localhost:3000"
        );
    }

    public function notifyHumanReviewRequired(int $taskId, string $title, string $reason = ''): void
    {
        $reasonText = $reason ? "\n⚠️ Reason: {$reason}" : '';
        $this->send(
            "🚨 <b>Human Review Required!</b>\n" .
            "🆔 Task #{$taskId}: {$title}{$reasonText}\n" .
            "👉 Please open the dashboard to resume or cancel."
        );
    }

    public function notifyQaFailed(int $taskId, string $title, int $retryCount): void
    {
        $this->send(
            "🔄 <b>QA Failed — Retrying</b>\n" .
            "🆔 Task #{$taskId}: {$title}\n" .
            "🔁 Retry #{$retryCount} (max 3)"
        );
    }

    public function notifyGithubPush(int $taskId, string $title, bool $success, string $error = ''): void
    {
        if ($success) {
            $this->send(
                "🐙 <b>Code Pushed to GitHub</b>\n" .
                "🆔 Task #{$taskId}: {$title}\n" .
                "✅ Auto-push completed successfully."
            );
        } else {
            $this->send(
                "🐙 <b>GitHub Push Failed</b>\n" .
                "🆔 Task #{$taskId}: {$title}\n" .
                "❌ Error: {$error}"
            );
        }
    }
}
