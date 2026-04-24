<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>SEOKRU Analytics — GA4 + Search Console in one plain-English report</title>
<meta name="description" content="Ask any question about your site's traffic. We join Google Analytics 4 with Search Console data and answer in plain English. In 60 seconds.">
<style>
*{box-sizing:border-box}
body{font-family:system-ui,sans-serif;max-width:960px;margin:0 auto;padding:20px;color:#222;line-height:1.55}

/* Top bar */
.topbar{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #eee;margin-bottom:1.5em;flex-wrap:wrap;gap:10px}
.topbar .brand{font-weight:700;color:#1a73e8;font-size:1.15rem}
.topbar .right{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.btn-connect{display:inline-block;background:#1a73e8;color:#fff;padding:8px 20px;border-radius:6px;text-decoration:none;font-weight:600;font-size:.9rem}
.btn-connect:hover{background:#1557b8}
.btn-connect-lg{padding:14px 36px;font-size:1.1rem;border-radius:10px}
.btn-sm{padding:6px 14px;border:1px solid #ddd;border-radius:6px;cursor:pointer;color:#555;font-size:.85rem;background:#fff;text-decoration:none}
.btn-sm:hover{border-color:#1a73e8;color:#1a73e8}
.step-badge{display:inline-block;background:#fef3c7;color:#92400e;font-size:.75rem;padding:3px 8px;border-radius:4px;font-weight:600}

/* Property selector — prominent centered box */
.property-picker{background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);border:2px solid #f59e0b;border-radius:14px;padding:24px 28px;margin-bottom:2em;text-align:center}
.property-picker h2{margin:0 0 .4em;font-size:1.15rem;color:#92400e}
.property-picker p{margin:0 0 1em;color:#78716c;font-size:.9rem}
.property-picker .selects{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}
.property-picker select{padding:10px 16px;border:2px solid #f59e0b;border-radius:8px;font-size:.95rem;min-width:240px;background:#fff;color:#222;cursor:pointer}
.property-picker select:focus{outline:none;border-color:#d97706;box-shadow:0 0 0 3px rgba(245,158,11,.2)}

/* Status bar */
.status{background:#f5f8ff;border:1px solid #d8e4ff;border-radius:8px;padding:10px 16px;margin-bottom:1.5em;font-size:.88rem;color:#555;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.status code{background:#e8f0fe;padding:2px 6px;border-radius:3px;font-size:.82rem}

/* Hero */
h1{font-size:2rem;margin:.1em 0 .15em}
.sub{color:#666;font-size:1.05rem;margin-bottom:1.5em}
.hero{background:linear-gradient(135deg,#f5f8ff 0%,#eef3ff 100%);border:1px solid #d8e4ff;border-radius:14px;padding:28px;margin-bottom:2em;position:relative}
.hero textarea{width:100%;min-height:90px;padding:12px;font-size:1rem;border:1px solid #cfd8e3;border-radius:8px;resize:vertical;font-family:inherit}
.hero textarea:focus{outline:none;border-color:#1a73e8;box-shadow:0 0 0 3px rgba(26,115,232,.12)}
.hero .row{display:flex;justify-content:space-between;align-items:center;margin-top:12px;gap:10px;flex-wrap:wrap}
.hero .hint{color:#777;font-size:.85rem}
.hero button{background:#1a73e8;color:#fff;border:0;padding:10px 22px;border-radius:8px;font-size:.95rem;cursor:pointer;font-weight:500}
.hero button:hover{background:#1557b8}
.hero-disabled textarea{opacity:.7}

/* Connect CTA — centered prominent box */
.connect-cta{text-align:center;background:linear-gradient(135deg,#f5f8ff 0%,#eef3ff 100%);border:2px solid #1a73e8;border-radius:14px;padding:32px;margin-bottom:2em}
.connect-cta h2{margin:0 0 .3em;color:#1a73e8;font-size:1.2rem}
.connect-cta p{margin:0 0 1.2em;color:#666;font-size:.95rem}

/* Cards */
.divider{text-align:center;color:#999;font-size:.82rem;margin:1.5em 0 1em;text-transform:uppercase;letter-spacing:.1em}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}
.card{border:1px solid #e3e3e3;border-radius:10px;padding:18px;transition:all .2s;text-decoration:none;color:inherit;display:block}
.card:hover{border-color:#1a73e8;box-shadow:0 4px 18px rgba(26,115,232,.12);transform:translateY(-2px)}
.card h3{margin:0 0 .3em;color:#1a73e8;font-size:1.05rem}
.card p{margin:0;font-size:.88rem;color:#555}
.badge{display:inline-block;background:#f0f7ff;color:#1a73e8;font-size:.7rem;padding:2px 7px;border-radius:4px;margin-top:.6em}
.card-cross{border-color:#d8b4ff;background:linear-gradient(135deg,#faf5ff 0%,#fff 100%)}
.card-cross:hover{border-color:#7c3aed;box-shadow:0 4px 18px rgba(124,58,237,.14)}
.card-cross h3{color:#7c3aed}
.badge-cross{background:#f3e8ff;color:#7c3aed}
.card-gated{opacity:.55;pointer-events:none;position:relative}
.card-gated:hover{transform:none;box-shadow:none}

/* Recent */
.recent-section{margin-top:2em;border-top:1px solid #eee;padding-top:1.5em}
.recent-section h3{font-size:.95rem;color:#555;margin:0 0 .6em}
.recent-list{list-style:none;padding:0;margin:0}
.recent-list li{padding:6px 0;border-bottom:1px solid #f0f0f0;display:flex;justify-content:space-between;align-items:center}
.recent-list a{color:#1a73e8;text-decoration:none;font-size:.9rem}
.recent-list a:hover{text-decoration:underline}
.recent-list .meta{color:#999;font-size:.8rem}
.recent-list .type-badge{background:#f0f7ff;color:#1a73e8;font-size:.7rem;padding:2px 6px;border-radius:3px;margin-left:6px}

.foot{margin-top:2.5em;color:#aaa;font-size:.82rem;text-align:center}
</style>
</head>
<body>

{{-- ============ TOP BAR ============ --}}
<div class="topbar">
<div class="brand">SEOKRU Analytics</div>
<div class="right">
@if($conn)
    <span style="color:#555;font-size:.85rem">{{ $conn->email }}</span>
    <form method="post" action="{{ route('logout') }}" style="margin:0">@csrf<button type="submit" class="btn-sm">Log out</button></form>
@endif
</div>
</div>

{{-- ============ HERO ============ --}}
<h1>GA4 + Search Console in one plain-English report.</h1>
<p class="sub">Ask any question. Or pick a preset. 60 seconds.</p>

{{-- ============ STATE 1: NOT CONNECTED ============ --}}
@if(!$conn)
<div class="connect-cta">
    <h2>Step 1 — Connect your Google account</h2>
    <p>We'll read your GA4 + Search Console data (read-only) and answer questions in plain English.</p>
    <a href="/auth/google" class="btn-connect btn-connect-lg">Connect Google →</a>
</div>

{{-- ============ STATE 2: CONNECTED, NO PROPERTY ============ --}}
@elseif(!$hasProperty)
<div class="property-picker">
    <h2>Step 2 — Choose your site</h2>
    <p>Select a GA4 property or Search Console site to unlock all reports.</p>
    <form method="post" action="{{ route('dashboard.property') }}" id="propForm">
    @csrf
    <div class="selects">
    <select name="ga4_property_id" onchange="document.getElementById('propForm').submit()">
    <option value="">— Select GA4 property —</option>
    @foreach($properties as $p)
    <option value="{{ $p['id'] }}" @if($conn->ga4_property_id === $p['id']) selected @endif>{{ $p['name'] }}</option>
    @endforeach
    </select>
    <select name="gsc_site_url" onchange="document.getElementById('propForm').submit()">
    <option value="">— Select Search Console site —</option>
    @foreach($sites as $s)
    <option value="{{ $s['url'] }}" @if($conn->gsc_site_url === $s['url']) selected @endif>{{ $s['url'] }}</option>
    @endforeach
    </select>
    </div>
    </form>
</div>

{{-- ============ STATE 3: READY — ASK ANYTHING ============ --}}
@else
<div class="status">
    <span>
    @if($conn->ga4_property_id) GA4: <code>{{ $conn->ga4_property_id }}</code> @endif
    @if($conn->gsc_site_url) · GSC: <code>{{ $conn->gsc_site_url }}</code> @endif
    </span>
    <form method="post" action="{{ route('dashboard.property') }}" id="propForm" style="display:flex;gap:6px;align-items:center;margin:0">
    @csrf
    <select name="ga4_property_id" onchange="document.getElementById('propForm').submit()" style="padding:4px 8px;border:1px solid #ddd;border-radius:4px;font-size:.8rem">
    <option value="">GA4: none</option>
    @foreach($properties as $p)
    <option value="{{ $p['id'] }}" @if($conn->ga4_property_id === $p['id']) selected @endif>{{ $p['name'] }}</option>
    @endforeach
    </select>
    <select name="gsc_site_url" onchange="document.getElementById('propForm').submit()" style="padding:4px 8px;border:1px solid #ddd;border-radius:4px;font-size:.8rem">
    <option value="">GSC: none</option>
    @foreach($sites as $s)
    <option value="{{ $s['url'] }}" @if($conn->gsc_site_url === $s['url']) selected @endif>{{ $s['url'] }}</option>
    @endforeach
    </select>
    </form>
</div>

<div class="hero">
    <form method="post" action="{{ route('ask.start') }}">
    @csrf
    <textarea name="prompt" placeholder="e.g. Which blog posts lost the most organic traffic last month, and why? Compare mobile vs desktop conversion. Show my top 10 keywords by impressions." required></textarea>
    <div class="row">
    <span class="hint">Agent pulls GA4 + Search Console data, computes the math, writes the answer.</span>
    <button type="submit">Ask →</button>
    </div>
    </form>
</div>
@endif

@php $gated = !$hasProperty; $gscReady = $conn && $conn->gsc_site_url; @endphp

{{-- ============ CROSS-PLATFORM CARDS ============ --}}
<div class="divider">— Cross-platform reports (GA4 × Search Console) —</div>
<div class="grid">
<a class="card card-cross @if($gated) card-gated @endif" href="{{ $gated ? '#' : route('generate.direct', 'silent_winners') }}">
<h3>Silent Winners</h3>
<p>Ranking well but barely clicked — title &amp; intent gaps.</p>
<span class="badge badge-cross">GA4 × GSC</span>
</a>

<a class="card card-cross @if($gated) card-gated @endif" href="{{ $gated ? '#' : route('generate.direct', 'converting_queries') }}">
<h3>Converting Queries Slipping</h3>
<p>Revenue pages losing Google rank.</p>
<span class="badge badge-cross">GA4 × GSC</span>
</a>

<a class="card card-cross @if($gated) card-gated @endif" href="{{ $gated ? '#' : route('generate.direct', 'cannibalization') }}">
<h3>Cannibalization Detector</h3>
<p>Multiple URLs fighting for same query.</p>
<span class="badge badge-cross">GA4 × GSC</span>
</a>

<a class="card card-cross @if($gated) card-gated @endif" href="{{ $gated ? '#' : route('generate.direct', 'brand_rescue') }}">
<h3>Brand Rescue vs Real Growth</h3>
<p>Is brand traffic masking non-brand decay?</p>
<span class="badge badge-cross">GA4 × GSC</span>
</a>
</div>

{{-- ============ SINGLE-SOURCE CARDS ============ --}}
<div class="divider">— Single-source presets —</div>
<div class="grid">

<a class="card @if(!$gscReady) card-gated @endif" href="{{ $gscReady ? route('generate.direct', 'keyword_rankings') : '#' }}">
<h3>Keyword Rankings — Web</h3>
<p>Query × month heatmap of organic blue-link positions. Top 50 keywords by impressions, last 13 months.</p>
<span class="badge">Search Console</span>
</a>

<a class="card @if(!$gscReady) card-gated @endif" href="{{ $gscReady ? route('generate.direct', 'keyword_rankings_news') : '#' }}">
<h3>Keyword Rankings — News</h3>
<p>Same pivot, but only Top Stories / News tab results. See where you rank in Google News.</p>
<span class="badge">Search Console · News</span>
</a>

<a class="card @if($gated) card-gated @endif" href="{{ $gated ? '#' : route('generate.direct', 'content_decay') }}">
<h3>Content Decay</h3>
<p>Pages losing traffic and by how much.</p>
<span class="badge">GA4</span>
</a>

<a class="card @if($gated) card-gated @endif" href="{{ $gated ? '#' : route('generate.direct', 'striking_distance') }}">
<h3>Striking-Distance Keywords</h3>
<p>Keywords ranked 4-20 with high impressions.</p>
<span class="badge">Search Console</span>
</a>

<a class="card @if($gated) card-gated @endif" href="{{ $gated ? '#' : route('generate.direct', 'conversion_leak') }}">
<h3>Conversion Leak</h3>
<p>High-traffic pages not converting.</p>
<span class="badge">GA4</span>
</a>

<a class="card @if($gated) card-gated @endif" href="{{ $gated ? '#' : route('generate.direct', 'anomaly') }}">
<h3>Weekly Anomaly Scan</h3>
<p>Metrics that moved >20% this week.</p>
<span class="badge">GA4 + GSC</span>
</a>

<a class="card @if($gated) card-gated @endif" href="{{ $gated ? '#' : route('generate.direct', 'brand_split') }}">
<h3>Brand vs Non-Brand</h3>
<p>Split by brand vs non-brand queries.</p>
<span class="badge">Search Console</span>
</a>
</div>

{{-- ============ RECENT REPORTS ============ --}}
@if($recent->count())
<div class="recent-section">
<h3>Recent reports</h3>
<ul class="recent-list">
@foreach($recent as $r)
<li>
<span>
<a href="{{ route('report.show', $r) }}">{{ Str::limit($r->title, 50) }}</a>
<span class="type-badge">{{ $r->type }}</span>
</span>
<span class="meta">{{ $r->created_at->diffForHumans() }}</span>
</li>
@endforeach
</ul>
</div>
@endif

<p class="foot">Free pilot — no credit card. GA4 + Search Console joined. Plain English.<br>
<a href="/about" style="color:#1a73e8;text-decoration:none;margin:0 6px">About</a> ·
<a href="https://www.seokru.com/legal/privacy/" style="color:#1a73e8;text-decoration:none;margin:0 6px">Privacy Policy</a> ·
<a href="https://www.seokru.com/legal/terms/" style="color:#1a73e8;text-decoration:none;margin:0 6px">Terms of Service</a> ·
<a href="mailto:info@seokru.com" style="color:#1a73e8;text-decoration:none;margin:0 6px">info@seokru.com</a>
</p>

</body>
</html>
