<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Laravel\Socialite\Facades\Socialite;
use App\Models\Connection;
use App\Models\ChatBinding;
use App\Services\TelegramService;

class AuthController extends Controller
{
    public function redirect(Request $request)
    {
        // If coming from Telegram bot, stash token in session to bind after callback.
        if ($tg = $request->query('tg_token')) {
            session(['tg_token' => $tg]);
        }

        return Socialite::driver('google')
            ->scopes([
                'https://www.googleapis.com/auth/analytics.readonly',
                'https://www.googleapis.com/auth/webmasters.readonly',
            ])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        $g = Socialite::driver('google')->user();

        $existing = Connection::where('google_user_id', $g->getId())->first();

        $conn = Connection::updateOrCreate(
            ['google_user_id' => $g->getId()],
            [
                'email' => $g->getEmail(),
                'access_token' => $g->token,
                // Keep previous refresh token if Google didn't return a new one.
                'refresh_token' => $g->refreshToken ?? ($existing->refresh_token ?? null),
                'expires_at' => now()->addSeconds($g->expiresIn ?? 3600),
            ]
        );

        session(['connection_id' => $conn->id]);

        // Remember cookie — 30 days — restore session across browser restarts.
        Cookie::queue('remember_connection', (string) $conn->id, 60 * 24 * 30);

        // Telegram bot OAuth kickoff → bind chat and return a friendly page.
        if ($tgToken = session('tg_token')) {
            session()->forget('tg_token');
            $binding = ChatBinding::where('auth_token', $tgToken)
                ->where('auth_token_expires_at', '>', now())
                ->first();
            if ($binding) {
                $binding->update([
                    'connection_id' => $conn->id,
                    'auth_token' => null,
                    'auth_token_expires_at' => null,
                ]);
                try {
                    (new TelegramService())->sendMessage($binding->chat_id,
                        "<b>✅ Connected!</b>\n\nGoogle account: <code>" . e($conn->email) . "</code>\n\n" .
                        "Now just send me a question like:\n" .
                        "• <i>Top pages losing traffic this month</i>\n" .
                        "• <i>Striking-distance keywords</i>\n" .
                        "• <i>Is my non-brand traffic growing?</i>"
                    );
                } catch (\Throwable $e) {}
                return view('telegram-connected', ['email' => $conn->email]);
            }
        }

        // Route back based on intent stored before OAuth.
        $type = session('report_type');
        if ($type === 'ask') return redirect()->route('ask.form');

        // Go home — landing handles all states (property select, dashboard, etc.)
        return redirect('/');
    }

    public function logout(Request $r)
    {
        $r->session()->forget('connection_id');
        $r->session()->forget('report_type');
        $r->session()->forget('pending_prompt');
        Cookie::queue(Cookie::forget('remember_connection'));
        return redirect('/');
    }
}
