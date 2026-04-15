<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\ChatBinding;
use App\Models\Connection;
use App\Models\Report;
use App\Services\AgentService;
use App\Services\TelegramService;

/**
 * Receives Telegram webhook updates.
 *
 * Flow:
 * 1. First message from new chat_id → send "connect Google" link with one-time auth_token
 * 2. User follows link → AuthController handles ?tg_token=<...>, binds connection
 * 3. Subsequent messages → run AgentService, reply with narrative
 * 4. Commands: /start, /help, /logout
 */
class TelegramWebhookController extends Controller
{
    public function handle(Request $request, TelegramService $tg)
    {
        // Verify secret token — set via setWebhook(..., secret_token=...)
        $expected = config('services.telegram.webhook_secret');
        if ($expected && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $expected) {
            Log::warning('Telegram webhook: secret mismatch');
            return response()->json(['ok' => false], 403);
        }

        $update = $request->all();
        $message = $update['message'] ?? $update['edited_message'] ?? null;
        if (!$message) return response()->json(['ok' => true]);

        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim($message['text'] ?? '');
        if (!$chatId || $text === '') return response()->json(['ok' => true]);

        // Command routing
        if (Str::startsWith($text, '/start'))  return $this->cmdStart($tg, $chatId);
        if (Str::startsWith($text, '/help'))   return $this->cmdHelp($tg, $chatId);
        if (Str::startsWith($text, '/logout')) return $this->cmdLogout($tg, $chatId);
        if (Str::startsWith($text, '/status')) return $this->cmdStatus($tg, $chatId);

        // Find connection
        $binding = ChatBinding::where('platform', 'telegram')->where('chat_id', $chatId)->first();
        $conn = $binding?->connection_id ? Connection::find($binding->connection_id) : null;

        if (!$conn) {
            return $this->promptConnect($tg, $chatId);
        }

        // Run agent
        $tg->sendChatAction($chatId, 'typing');

        try {
            $agent = new AgentService($conn);
            $result = $agent->run($text);

            // Extract and send images separately
            foreach ($tg->extractImageUrls($result['narrative']) as $imgUrl) {
                $tg->sendPhoto($chatId, $imgUrl);
            }
            $tg->sendMessage($chatId, $result['narrative']);

            // Persist as report
            $report = Report::create([
                'connection_id' => $conn->id,
                'type' => 'ask',
                'title' => Str::limit($text, 80),
                'metrics' => [
                    'prompt' => $text,
                    'tool_calls' => $result['tool_calls'] ?? [],
                    'iterations' => $result['iterations'] ?? null,
                    'source' => 'telegram',
                ],
                'narrative' => $result['narrative'],
            ]);

            $tg->sendMessage($chatId, "Full report: " . route('report.show', $report));
        } catch (\Throwable $e) {
            Log::error('Telegram agent error', ['e' => $e->getMessage(), 'chat' => $chatId]);
            $tg->sendMessage($chatId, "Something went wrong: <code>" . e($e->getMessage()) . "</code>");
        }

        return response()->json(['ok' => true]);
    }

    protected function cmdStart(TelegramService $tg, string $chatId)
    {
        $binding = ChatBinding::where('platform', 'telegram')->where('chat_id', $chatId)->first();
        if ($binding?->connection_id) {
            $tg->sendMessage($chatId,
                "<b>Welcome back</b>\n\nYou're connected. Just send me a question like:\n" .
                "• <i>Top landing pages last 7 days</i>\n" .
                "• <i>Which keywords am I losing rank for?</i>\n" .
                "• <i>Compare mobile vs desktop conversion</i>\n\n" .
                "Commands: /status /logout /help"
            );
            return response()->json(['ok' => true]);
        }
        return $this->promptConnect($tg, $chatId);
    }

    protected function cmdHelp(TelegramService $tg, string $chatId)
    {
        $tg->sendMessage($chatId,
            "<b>SEOKRU Analytics Bot</b>\n\n" .
            "Ask any question about your Google Analytics 4 + Search Console data.\n\n" .
            "Examples:\n" .
            "• Top pages losing traffic last month\n" .
            "• Striking-distance keywords (rank 4-20)\n" .
            "• Which URLs cannibalize each other for my top query?\n" .
            "• Is my non-brand traffic growing?\n\n" .
            "Commands:\n" .
            "/start — welcome\n" .
            "/status — show connected account\n" .
            "/logout — disconnect Google\n" .
            "/help — this message"
        );
        return response()->json(['ok' => true]);
    }

    protected function cmdStatus(TelegramService $tg, string $chatId)
    {
        $binding = ChatBinding::where('platform', 'telegram')->where('chat_id', $chatId)->first();
        $conn = $binding?->connection_id ? Connection::find($binding->connection_id) : null;
        if (!$conn) {
            return $this->promptConnect($tg, $chatId);
        }
        $tg->sendMessage($chatId,
            "<b>Connected</b>\n" .
            "Google account: <code>" . e($conn->email) . "</code>\n" .
            "GA4 property: <code>" . e($conn->ga4_property_id ?: 'not set') . "</code>\n" .
            "GSC site: <code>" . e($conn->gsc_site_url ?: 'not set') . "</code>"
        );
        return response()->json(['ok' => true]);
    }

    protected function cmdLogout(TelegramService $tg, string $chatId)
    {
        ChatBinding::where('platform', 'telegram')->where('chat_id', $chatId)->delete();
        $tg->sendMessage($chatId, "Disconnected. Send /start to reconnect.");
        return response()->json(['ok' => true]);
    }

    protected function promptConnect(TelegramService $tg, string $chatId)
    {
        $token = Str::random(40);
        ChatBinding::updateOrCreate(
            ['platform' => 'telegram', 'chat_id' => $chatId],
            ['auth_token' => $token, 'auth_token_expires_at' => now()->addMinutes(15)]
        );

        $url = url('/auth/google?tg_token=' . $token);
        $tg->sendMessage($chatId,
            "<b>Welcome to SEOKRU Analytics</b>\n\n" .
            "I answer plain-English questions about your GA4 + Search Console data.\n\n" .
            "To get started, connect your Google account:\n" .
            "<a href=\"{$url}\">Connect Google →</a>\n\n" .
            "<i>Link expires in 15 minutes.</i>"
        );
        return response()->json(['ok' => true]);
    }
}
