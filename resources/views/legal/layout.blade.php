<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>@yield('title', 'SEOKRU Analytics')</title>
<meta name="description" content="@yield('description', 'SEOKRU Analytics — GA4 + Search Console in plain English.')">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/favicon-180.png">
<style>
*{box-sizing:border-box}
body{font-family:system-ui,sans-serif;max-width:760px;margin:0 auto;padding:20px;color:#222;line-height:1.65}
.topbar{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #eee;margin-bottom:2em;flex-wrap:wrap;gap:10px}
.topbar .brand{font-weight:700;color:#1a73e8;font-size:1.15rem;text-decoration:none}
.topbar a{color:#555;text-decoration:none;font-size:.9rem;margin-left:14px}
.topbar a:hover{color:#1a73e8}
h1{font-size:1.75rem;margin:.1em 0 .3em}
h2{font-size:1.15rem;margin:1.6em 0 .4em;color:#1a73e8}
h3{font-size:1rem;margin:1.2em 0 .3em}
.meta{color:#888;font-size:.85rem;margin-bottom:1.8em}
p,ul{margin:.6em 0}
ul{padding-left:1.4em}
code{background:#f0f0f0;padding:2px 5px;border-radius:3px;font-size:.88rem;word-break:break-all}
.foot{margin-top:3em;padding-top:1em;border-top:1px solid #eee;color:#999;font-size:.82rem;text-align:center}
.foot a{color:#1a73e8;text-decoration:none;margin:0 8px}
.callout{background:#f5f8ff;border-left:3px solid #1a73e8;padding:12px 16px;margin:1em 0;font-size:.95rem}
</style>
</head>
<body>

<div class="topbar">
<a href="/" class="brand">SEOKRU Analytics</a>
<div>
<a href="/about">About</a>
<a href="/privacy">Privacy</a>
<a href="/terms">Terms</a>
</div>
</div>

@yield('content')

<div class="foot">
<a href="/">Home</a> · <a href="/about">About</a> · <a href="/privacy">Privacy</a> · <a href="/terms">Terms</a>
<p>SEOKRU Analytics · operated by SEOKRU · <a href="mailto:info@seokru.com">info@seokru.com</a></p>
</div>

</body>
</html>
