<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook {url? : Public URL (e.g. https://xxx.ngrok.io)} {--remove : Remove the webhook instead}';

    protected $description = 'Set or remove the Telegram bot webhook for PM review buttons';

    public function handle(): int
    {
        $token = config('app.telegram_bot_token');

        if (empty($token)) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env');
            return 1;
        }

        $baseUrl = "https://api.telegram.org/bot{$token}";

        if ($this->option('remove')) {
            return $this->removeWebhook($baseUrl);
        }

        $url = $this->argument('url');

        if (empty($url)) {
            $this->error('Please provide a public URL: php artisan telegram:set-webhook https://xxx.ngrok.io');
            return 1;
        }

        $webhookUrl = rtrim($url, '/') . '/api/telegram/webhook';
        $secret = config('app.telegram_webhook_secret');

        $payload = [
            'url'             => $webhookUrl,
            'allowed_updates' => ['callback_query', 'message'],
        ];

        if (! empty($secret)) {
            $payload['secret_token'] = $secret;
        }

        $this->info("Setting webhook to: {$webhookUrl}");

        $response = Http::timeout(10)->post("{$baseUrl}/setWebhook", $payload);

        if ($response->successful() && ($response->json('ok') ?? false)) {
            $this->info('Webhook set successfully!');
            if (empty($secret)) {
                $this->warn('Tip: Set TELEGRAM_WEBHOOK_SECRET in .env for extra security.');
            }
            return 0;
        }

        $this->error('Failed to set webhook: ' . ($response->json('description') ?? $response->body()));
        return 1;
    }

    private function removeWebhook(string $baseUrl): int
    {
        $this->info('Removing webhook...');

        $response = Http::timeout(10)->post("{$baseUrl}/deleteWebhook");

        if ($response->successful() && ($response->json('ok') ?? false)) {
            $this->info('Webhook removed successfully.');
            return 0;
        }

        $this->error('Failed to remove webhook: ' . ($response->json('description') ?? $response->body()));
        return 1;
    }
}
