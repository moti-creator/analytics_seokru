<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>SEOKRU Analytics — Answers GA4 won't give you</title>
<style>
body{font-family:system-ui,sans-serif;max-width:900px;margin:60px auto;padding:20px;color:#222;line-height:1.55}
h1{font-size:2.2rem;margin-bottom:.2em}
.sub{color:#666;font-size:1.1rem;margin-bottom:2.5em}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px}
.card{border:1px solid #e3e3e3;border-radius:10px;padding:22px;transition:all .2s;text-decoration:none;color:inherit;display:block}
.card:hover{border-color:#1a73e8;box-shadow:0 4px 18px rgba(26,115,232,.12);transform:translateY(-2px)}
.card h3{margin:0 0 .4em;color:#1a73e8;font-size:1.15rem}
.card p{margin:0;font-size:.92rem;color:#555}
.badge{display:inline-block;background:#f0f7ff;color:#1a73e8;font-size:.75rem;padding:3px 8px;border-radius:4px;margin-top:.8em}
.foot{margin-top:3em;color:#888;font-size:.9rem;text-align:center}
</style>
</head>
<body>
<h1>Answers GA4 won't give you.</h1>
<p class="sub">Pick a report. Connect Google. Get the answer in 60 seconds — in plain English.</p>

<div class="grid">
<a class="card" href="{{ route('start', 'content_decay') }}">
<h3>Content Decay</h3>
<p>Which pages are losing traffic and by how much. Built from GA4 in one click.</p>
<span class="badge">GA4</span>
</a>

<a class="card" href="{{ route('start', 'striking_distance') }}">
<h3>Striking-Distance Keywords</h3>
<p>Keywords ranking position 4-20 with high impressions. Your fastest SEO wins.</p>
<span class="badge">Search Console</span>
</a>

<a class="card" href="{{ route('start', 'conversion_leak') }}">
<h3>Conversion Leak</h3>
<p>High-traffic pages that aren't converting. Fix-priority list, not a maze of reports.</p>
<span class="badge">GA4</span>
</a>

<a class="card" href="{{ route('start', 'anomaly') }}">
<h3>Weekly Anomaly Scan</h3>
<p>Every metric that moved &gt;20% this week vs last, with likely causes.</p>
<span class="badge">GA4 + GSC</span>
</a>

<a class="card" href="{{ route('start', 'brand_split') }}">
<h3>Brand vs Non-Brand</h3>
<p>Split your search traffic by brand vs non-brand queries. Growth signal check.</p>
<span class="badge">Search Console</span>
</a>
</div>

<p class="foot">Pilot — free, no credit card. Connect Google in one click.</p>
</body>
</html>
