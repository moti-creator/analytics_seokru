<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin Telegram Bot API wrapper. https://core.telegram.org/bots/api
 * All methods no-op if TELEGRAM_BOT_TOKEN is not set (dev-safe).
 */
class TelegramService
{
    protected ?string $token;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
    }

    protected function api(string $method, array $params = [])
    {
        if (!$this->token) {
            Log::warning('TelegramService called without bot token', compact('method'));
            return null;
        }
        $resp = Http::timeout(15)->post(
            "https://api.telegram.org/bot{$this->token}/{$method}",
            $params
        );
        if (!$resp->ok()) {
            Log::warning('Telegram API error', ['method' => $method, 'body' => $resp->body()]);
        }
        return $resp->json();
    }

    public function sendMessage(string $chatId, string $text, array $extra = []): ?array
    {
        // Telegram HTML mode supports <b>, <i>, <a href>, <code>, <pre>. Strip unsupported tags.
        $safe = $this->cleanHtmlForTelegram($text);
        // Telegram max message length = 4096. Split if needed.
        $chunks = $this->chunk($safe, 4000);
        $last = null;
        foreach ($chunks as $chunk) {
            $last = $this->api('sendMessage', array_merge([
                'chat_id' => $chatId,
                'text' => $chunk,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ], $extra));
        }
        return $last;
    }

    public function sendPhoto(string $chatId, string $photoUrl, ?string $caption = null): ?array
    {
        return $this->api('sendPhoto', array_filter([
            'chat_id' => $chatId,
            'photo' => $photoUrl,
            'caption' => $caption ? $this->cleanHtmlForTelegram($caption) : null,
            'parse_mode' => 'HTML',
        ]));
    }

    public function sendChatAction(string $chatId, string $action = 'typing'): ?array
    {
        return $this->api('sendChatAction', compact('chatId', 'action') + ['chat_id' => $chatId, 'action' => $action]);
    }

    public function setWebhook(string $url, ?string $secretToken = null): ?array
    {
        return $this->api('setWebhook', array_filter([
            'url' => $url,
            'secret_token' => $secretToken,
            'drop_pending_updates' => true,
        ]));
    }

    public function getWebhookInfo(): ?array
    {
        return $this->api('getWebhookInfo');
    }

    /**
     * Telegram HTML supports a narrow tag set. Strip everything else.
     * Extract <img src=""> URLs separately — caller sends as sendPhoto.
     */
    public function extractImageUrls(string $html): array
    {
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m);
        return $m[1] ?? [];
    }

    public function cleanHtmlForTelegram(string $html): string
    {
        // Convert <h2>...</h2> to <b>...</b>\n
        $html = preg_replace('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', "<b>$1</b>\n", $html);
        // Convert <strong>/<em> (keep)
        // Convert <br> to newline
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        // Convert <p> to newline
        $html = preg_replace('/<p[^>]*>/i', '', $html);
        $html = str_ireplace('</p>', "\n\n", $html);
        // Convert <li> to "• " prefix
        $html = preg_replace('/<li[^>]*>/i', '• ', $html);
        $html = str_ireplace('</li>', "\n", $html);
        $html = preg_replace('/<\/?(ul|ol)[^>]*>/i', '', $html);
        // Tables — flatten to rows (Telegram has no table support)
        $html = preg_replace_callback('/<table[^>]*>(.*?)<\/table>/is', function ($m) {
            $rows = preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $m[1], $tr) ? $tr[1] : [];
            $out = [];
            foreach ($rows as $row) {
                preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $row, $cells);
                $out[] = implode(' | ', array_map(fn($c) => trim(strip_tags($c)), $cells[1] ?? []));
            }
            return "\n<pre>" . implode("\n", $out) . "</pre>\n";
        }, $html);
        // Remove <img> (we send via sendPhoto)
        $html = preg_replace('/<img[^>]*>/i', '', $html);
        // Whitelist: keep only supported tags
        $html = strip_tags($html, '<b><i><u><s><a><code><pre>');
        // Collapse triple newlines
        $html = preg_replace("/\n{3,}/", "\n\n", $html);
        return trim($html);
    }

    protected function chunk(string $text, int $max): array
    {
        if (mb_strlen($text) <= $max) return [$text];
        $out = [];
        $lines = explode("\n", $text);
        $buf = '';
        foreach ($lines as $ln) {
            if (mb_strlen($buf) + mb_strlen($ln) + 1 > $max) {
                if ($buf) $out[] = $buf;
                $buf = $ln;
            } else {
                $buf = $buf === '' ? $ln : $buf . "\n" . $ln;
            }
        }
        if ($buf) $out[] = $buf;
        return $out;
    }
}
