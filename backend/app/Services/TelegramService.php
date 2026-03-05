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
}
