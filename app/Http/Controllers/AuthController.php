<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Laravel\Socialite\Facades\Socialite;
use App\Models\Connection;

class AuthController extends Controller
{
    public function redirect()
    {
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

        // Route back based on intent stored before OAuth.
        $type = session('report_type');
        if ($type === 'ask') return redirect()->route('ask.form');
        return redirect()->route('connect');
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
