<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>SEOKRU Analytics — Answers GA4 won't give you</title>
<style>
body{font-family:system-ui,sans-serif;max-width:960px;margin:50px auto;padding:20px;color:#222;line-height:1.55}
.nav{display:flex;justify-content:space-between;align-items:center;margin-bottom:2em}
.nav .brand{font-weight:600;color:#1a73e8}
.nav form{margin:0}
.nav button{background:none;border:1px solid #ddd;padding:6px 14px;border-radius:6px;cursor:pointer;color:#555;font-size:.88rem}
.nav button:hover{border-color:#1a73e8;color:#1a73e8}
h1{font-size:2.4rem;margin:.1em 0}
.sub{color:#666;font-size:1.15rem;margin-bottom:1.8em}
.hero{background:linear-gradient(135deg,#f5f8ff 0%,#eef3ff 100%);border:1px solid #d8e4ff;border-radius:14px;padding:32px;margin-bottom:2.5em}
.hero textarea{width:100%;min-height:110px;padding:14px;font-size:1rem;border:1px solid #cfd8e3;border-radius:8px;resize:vertical;font-family:inherit;box-sizing:border-box}
.hero textarea:focus{outline:none;border-color:#1a73e8;box-shadow:0 0 0 3px rgba(26,115,232,.12)}
.hero .row{display:flex;justify-content:space-between;align-items:center;margin-top:14px;gap:12px;flex-wrap:wrap}
.hero .hint{color:#777;font-size:.88rem}
.hero button{background:#1a73e8;color:#fff;border:0;padding:12px 26px;border-radius:8px;font-size:1rem;cursor:pointer;font-weight:500}
.hero button:hover{background:#1557b8}
.examples{margin-top:10px;font-size:.85rem;color:#888}
.examples a{color:#1a73e8;cursor:pointer;text-decoration:none;margin-right:14px}
.examples a:hover{text-decoration:underline}
.divider{text-align:center;color:#999;font-size:.85rem;margin:2em 0 1.5em;text-transform:uppercase;letter-spacing:.1em}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}
.card{border:1px solid #e3e3e3;border-radius:10px;padding:20px;transition:all .2s;text-decoration:none;color:inherit;display:block}
.card:hover{border-color:#1a73e8;box-shadow:0 4px 18px rgba(26,115,232,.12);transform:translateY(-2px)}
.card h3{margin:0 0 .4em;color:#1a73e8;font-size:1.1rem}
.card p{margin:0;font-size:.9rem;color:#555}
.badge{display:inline-block;background:#f0f7ff;color:#1a73e8;font-size:.72rem;padding:3px 8px;border-radius:4px;margin-top:.7em}
.card-cross{border-color:#d8b4ff;background:linear-gradient(135deg,#faf5ff 0%,#fff 100%)}
.card-cross:hover{border-color:#7c3aed;box-shadow:0 4px 18px rgba(124,58,237,.14)}
.card-cross h3{color:#7c3aed}
.badge-cross{background:#f3e8ff;color:#7c3aed}
.foot{margin-top:3em;color:#888;font-size:.88rem;text-align:center}
</style>
</head>
<body>

<div class="nav">
<div class="brand">SEOKRU Analytics</div>
@if($connection_id)
<form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Log out</button></form>
@endif
</div>

<h1>Answers GA4 won't give you.</h1>
<p class="sub">Ask any question about your site's traffic. Or pick a preset report. Plain English, 60 seconds.</p>

<div class="hero">
<form method="post" action="{{ route('ask.start') }}">
@csrf
<textarea name="prompt" id="prompt" placeholder="e.g. Which blog posts lost the most organic traffic last month, and why? Compare mobile vs desktop conversion rate. Show my top 10 pages by revenue this week." required></textarea>
<div class="row">
<span class="hint">Connect Google once. Agent fetches GA4 + Search Console data for you.</span>
<button type="submit">Ask →</button>
</div>
</form>
<div class="examples">
Try:
<a onclick="setP('Which pages lost the most organic traffic in the last 30 days vs the 30 days before?')">Content decay</a>
<a onclick="setP('Compare mobile vs desktop conversion rate this month')">Mobile vs desktop</a>
<a onclick="setP('Top 20 search queries where I rank positions 4-20 with over 100 impressions')">Striking distance</a>
</div>
</div>

<div class="divider">— Cross-platform reports (GA4 + Search Console joined) —</div>

<div class="grid">
<a class="card card-cross" href="{{ route('start', 'silent_winners') }}">
<h3>Silent Winners</h3>
<p>Pages ranking well but barely clicked — or clicked but users bounce. Title &amp; intent gaps.</p>
<span class="badge badge-cross">GA4 × GSC</span>
</a>

<a class="card card-cross" href="{{ route('start', 'converting_queries') }}">
<h3>Converting Queries Slipping</h3>
<p>Your revenue pages — are their Google rankings dropping? Money-weighted rank watch.</p>
<span class="badge badge-cross">GA4 × GSC</span>
</a>

<a class="card card-cross" href="{{ route('start', 'cannibalization') }}">
<h3>Cannibalization Detector</h3>
<p>Queries where multiple of your URLs fight. GA4 tells you which one actually converts.</p>
<span class="badge badge-cross">GA4 × GSC</span>
</a>

<a class="card card-cross" href="{{ route('start', 'brand_rescue') }}">
<h3>Brand Rescue vs Real Growth</h3>
<p>Is brand traffic masking non-brand decay? Split-adjusted growth truth.</p>
<span class="badge badge-cross">GA4 × GSC</span>
</a>
</div>

<div class="divider">— Single-source presets —</div>

<div class="grid">
<a class="card" href="{{ route('start', 'content_decay') }}">
<h3>Content Decay</h3>
<p>Which pages are losing traffic and by how much.</p>
<span class="badge">GA4</span>
</a>

<a class="card" href="{{ route('start', 'striking_distance') }}">
<h3>Striking-Distance Keywords</h3>
<p>Keywords ranking 4-20 with high impressions. Fastest SEO wins.</p>
<span class="badge">Search Console</span>
</a>

<a class="card" href="{{ route('start', 'conversion_leak') }}">
<h3>Conversion Leak</h3>
<p>High-traffic pages that aren't converting. Fix-priority list.</p>
<span class="badge">GA4</span>
</a>

<a class="card" href="{{ route('start', 'anomaly') }}">
<h3>Weekly Anomaly Scan</h3>
<p>Every metric that moved &gt;20% this week vs last.</p>
<span class="badge">GA4 + GSC</span>
</a>

<a class="card" href="{{ route('start', 'brand_split') }}">
<h3>Brand vs Non-Brand</h3>
<p>Split search traffic by brand vs non-brand queries.</p>
<span class="badge">Search Console</span>
</a>
</div>

<p class="foot">Pilot — free, no credit card. Connect Google in one click.</p>

<script>
function setP(t){document.getElementById('prompt').value=t;document.getElementById('prompt').focus();}
</script>
</body>
</html>
