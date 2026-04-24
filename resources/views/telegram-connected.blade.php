<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Connected — SEOKRU Analytics</title>
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/favicon-180.png">
<style>
body{font-family:system-ui,sans-serif;max-width:520px;margin:80px auto;padding:20px;text-align:center;color:#222;line-height:1.6}
.check{font-size:4rem;margin-bottom:.2em}
h1{color:#1e8e3e;margin:.2em 0}
.email{background:#f5f8ff;border:1px solid #d8e4ff;padding:8px 16px;border-radius:6px;display:inline-block;margin:1em 0;font-family:monospace}
.btn{display:inline-block;background:#229ED9;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;margin-top:1em}
</style>
</head>
<body>
<div class="check">✅</div>
<h1>Connected!</h1>
<p>Your Google account is now linked to the Telegram bot.</p>
<div class="email">{{ $email }}</div>
<p>You can close this tab and return to Telegram.</p>
@if(config('services.telegram.bot_username'))
<a class="btn" href="https://t.me/{{ config('services.telegram.bot_username') }}">Open Telegram bot →</a>
@endif
</body>
</html>
