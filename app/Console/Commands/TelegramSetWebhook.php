<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TelegramService;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {url? : Override URL (default: APP_URL/webhooks/telegram)}';
    protected $description = 'Register the Telegram webhook with the Bot API';

    public function handle(TelegramService $tg): int
    {
        if (!config('services.telegram.bot_token')) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env');
            return 1;
        }

        $url = $this->argument('url') ?: rtrim(config('app.url'), '/') . '/webhooks/telegram';
        $secret = config('services.telegram.webhook_secret');

        $this->info("Setting webhook to: {$url}");
        if ($secret) $this->info("Using secret token from TELEGRAM_WEBHOOK_SECRET");

        $resp = $tg->setWebhook($url, $secret);
        $this->line(json_encode($resp, JSON_PRETTY_PRINT));

        $info = $tg->getWebhookInfo();
        $this->line('--- getWebhookInfo ---');
        $this->line(json_encode($info, JSON_PRETTY_PRINT));

        return ($resp['ok'] ?? false) ? 0 : 1;
    }
}
