<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Clarify — SEOKRU Analytics</title>
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/favicon-180.png">
<style>
*{box-sizing:border-box}
body{font-family:system-ui,sans-serif;max-width:760px;margin:0 auto;padding:20px;color:#222;line-height:1.55}
.brand{font-weight:700;color:#1a73e8;font-size:1.15rem;padding:12px 0;border-bottom:1px solid #eee;margin-bottom:2em}
.brand a{color:#1a73e8;text-decoration:none}
.orig{background:#f5f5f5;border-left:3px solid #1a73e8;padding:10px 14px;color:#555;font-size:.92rem;border-radius:4px;margin-bottom:1em}
.orig .lbl{font-size:.72rem;color:#888;text-transform:uppercase;font-weight:700;margin-bottom:3px}
.qbox{background:linear-gradient(135deg,#fffbeb,#fef3c7);border:2px solid #f59e0b;border-radius:14px;padding:22px 26px;margin-bottom:1.2em}
.qbox h2{margin:0 0 .3em;font-size:1rem;color:#92400e;text-transform:uppercase;letter-spacing:.05em}
.qbox p{margin:0;font-size:1.1rem;color:#222;font-weight:500}
.reply textarea{width:100%;min-height:100px;padding:12px;font-size:1rem;border:1px solid #cfd8e3;border-radius:8px;font-family:inherit;resize:vertical}
.reply textarea:focus{outline:none;border-color:#1a73e8;box-shadow:0 0 0 3px rgba(26,115,232,.12)}
.reply .row{display:flex;justify-content:space-between;align-items:center;margin-top:12px;gap:10px}
.reply button{background:#1a73e8;color:#fff;border:0;padding:10px 22px;border-radius:8px;font-size:.95rem;cursor:pointer;font-weight:600}
.reply button:hover{background:#1557b8}
.reply a{color:#888;font-size:.9rem;text-decoration:none}
.reply a:hover{color:#1a73e8}
</style>
</head>
<body>

<div class="brand"><a href="/">SEOKRU Analytics</a></div>

<div class="orig">
<div class="lbl">Your question</div>
{{ $clarify['original_prompt'] }}
</div>

<div class="qbox">
<h2>Agent needs clarification</h2>
<p>{{ $clarify['question'] }}</p>
</div>

<form method="post" action="{{ route('ask.clarify.submit') }}" class="reply">
@csrf
<textarea name="answer" placeholder="Type your answer here…" required autofocus></textarea>
<div class="row">
<a href="{{ route('ask.form') }}">← cancel, rewrite my question</a>
<button type="submit">Send answer →</button>
</div>
</form>

@include('partials.footer')

</body>
</html>
