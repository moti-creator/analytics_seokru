"""Update www.seokru.com/services/ai-analytics/ — v2: fix buttons, bg animation, anchor, CTA visibility."""
import json, urllib.request, urllib.error, base64

USER = 'n8n_seokru'
PASS = 'gkef aSUL Cg8f nvDl Rhz6 d1gm'
BASE = 'https://www.seokru.com/wp-json/wp/v2/pages'

CONTENT = r"""<style>
.skru-ana-section{width:100%;max-width:1200px;margin:0 auto;padding:0 20px;box-sizing:border-box;}
.skru-ana-hero{text-align:center;padding:80px 20px 60px;max-width:820px;margin:0 auto;position:relative;z-index:2;}
.skru-ana-hero h1{font-size:2.7em;font-weight:900;color:#fff;line-height:1.15;margin:0 0 20px;letter-spacing:-.02em;}
.skru-ana-hero h1 span{background:linear-gradient(135deg,#51be89,#7ee0b0);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.skru-ana-hero p{color:rgba(255,255,255,.6);font-size:1.15em;line-height:1.75;margin:0 0 40px;}
.skru-btn-row{display:flex;gap:18px;justify-content:center;flex-wrap:wrap;}
.skru-btn{display:inline-flex;align-items:center;justify-content:center;min-width:220px;padding:16px 32px;border-radius:10px;text-decoration:none;font-weight:800;font-size:1.05em;letter-spacing:.01em;transition:all .35s cubic-bezier(.2,.8,.2,1);position:relative;overflow:hidden;box-sizing:border-box;}
.skru-btn-primary{background:linear-gradient(135deg,#51be89,#00875a);color:#fff;box-shadow:0 8px 24px rgba(81,190,137,.22);}
.skru-btn-primary::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(120deg,transparent,rgba(255,255,255,.28),transparent);transition:left .6s ease;}
.skru-btn-primary:hover{transform:translateY(-3px) scale(1.03);box-shadow:0 18px 48px rgba(81,190,137,.45);color:#fff;}
.skru-btn-primary:hover::before{left:100%;}
.skru-btn-secondary{background:rgba(81,190,137,.06);border:1.5px solid rgba(81,190,137,.35);color:#51be89;}
.skru-btn-secondary:hover{transform:translateY(-3px);background:rgba(81,190,137,.14);border-color:#51be89;color:#b8f0d4;box-shadow:0 12px 32px rgba(81,190,137,.2);}
.skru-bg-wrap{position:relative;overflow:hidden;}
.skru-bg-fx{position:absolute;inset:0;overflow:hidden;pointer-events:none;z-index:0;}
.skru-bg-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(81,190,137,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(81,190,137,.035) 1px,transparent 1px);background-size:60px 60px;animation:skru-grid-scroll 22s linear infinite;}
.skru-bg-orb{position:absolute;border-radius:50%;filter:blur(40px);}
.skru-orb1{width:700px;height:700px;left:-220px;top:5%;background:radial-gradient(circle,rgba(81,190,137,.13) 0%,transparent 62%);animation:skru-orb-drift 14s ease-in-out infinite;}
.skru-orb2{width:500px;height:500px;right:-130px;top:30%;background:radial-gradient(circle,rgba(81,190,137,.1) 0%,transparent 62%);animation:skru-orb-drift 11s ease-in-out infinite reverse;}
.skru-orb3{width:600px;height:600px;left:30%;bottom:-100px;background:radial-gradient(circle,rgba(0,135,90,.12) 0%,transparent 62%);animation:skru-orb-drift 16s ease-in-out infinite;}
.skru-particle{position:absolute;width:4px;height:4px;background:#51be89;border-radius:50%;box-shadow:0 0 12px #51be89;opacity:0;animation:skru-particle-rise 8s linear infinite;}
.skru-particle:nth-child(1){left:10%;animation-delay:0s;}
.skru-particle:nth-child(2){left:25%;animation-delay:1.2s;}
.skru-particle:nth-child(3){left:45%;animation-delay:2.8s;}
.skru-particle:nth-child(4){left:65%;animation-delay:4.1s;}
.skru-particle:nth-child(5){left:80%;animation-delay:5.5s;}
.skru-particle:nth-child(6){left:92%;animation-delay:6.7s;}
.skru-stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;max-width:1200px;margin:0 auto 70px;padding:0 20px;position:relative;z-index:2;}
@media(max-width:800px){.skru-stats-row{grid-template-columns:repeat(2,1fr);}}
@media(max-width:480px){.skru-stats-row{grid-template-columns:1fr;}}
.skru-stat-box{background:rgba(255,255,255,.03);border:1px solid rgba(81,190,137,.14);border-radius:16px;padding:28px 20px;text-align:center;backdrop-filter:blur(10px);transition:transform .4s,border-color .4s;}
.skru-stat-box:hover{transform:translateY(-4px);border-color:rgba(81,190,137,.35);}
.skru-stat-num{font-size:2.4em;font-weight:900;color:#51be89;line-height:1;}
.skru-stat-lbl{color:rgba(255,255,255,.5);font-size:.88em;margin-top:8px;line-height:1.4;}
.skru-section-hd{text-align:left;margin-bottom:44px;position:relative;z-index:2;}
.skru-section-hd h2{font-size:1.95em;font-weight:800;color:#fff;margin:0 0 8px;}
.skru-section-hd p{color:rgba(255,255,255,.5);font-size:.95em;margin:0;}
.skru-cards-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:20px;position:relative;z-index:2;}
@media(max-width:900px){.skru-cards-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:580px){.skru-cards-grid{grid-template-columns:1fr;}}
.skru-card{background:rgba(255,255,255,.028);border:1px solid rgba(81,190,137,.13);border-radius:20px;padding:32px 28px;position:relative;overflow:hidden;transition:transform .55s cubic-bezier(.25,.46,.45,.94),border-color .55s,background .55s,box-shadow .55s;display:flex;flex-direction:column;backdrop-filter:blur(10px);}
.skru-card:hover{transform:translateY(-8px) scale(1.025);border-color:rgba(81,190,137,.4);background:rgba(81,190,137,.036);box-shadow:0 28px 70px rgba(0,0,0,.5),0 0 0 1px rgba(81,190,137,.07);}
.skru-card-top{display:flex;align-items:center;gap:14px;margin-bottom:18px;}
.skru-icon{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,rgba(81,190,137,.14),rgba(81,190,137,.04));border:1px solid rgba(81,190,137,.24);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .3s,border-color .3s,box-shadow .3s;}
.skru-card:hover .skru-icon{background:linear-gradient(135deg,rgba(81,190,137,.26),rgba(81,190,137,.09));border-color:rgba(81,190,137,.48);box-shadow:0 4px 22px rgba(81,190,137,.22);}
.skru-card-title{font-size:1.18em;font-weight:800;color:#fff;margin:0;line-height:1.25;}
.skru-card-desc{color:rgba(255,255,255,.6);font-size:1em;line-height:1.76;margin-bottom:0;flex-grow:1;}
.skru-badge{position:absolute;top:-11px;left:22px;background:linear-gradient(135deg,#51be89,#00693d);color:#fff;font-size:10px;font-weight:800;padding:3px 12px;border-radius:20px;letter-spacing:.06em;}
.skru-divider{width:100%;height:1px;background:linear-gradient(90deg,transparent,rgba(81,190,137,.3),transparent);margin:72px 0 62px;position:relative;z-index:2;}
.skru-how-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:60px;position:relative;z-index:2;}
@media(max-width:800px){.skru-how-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:480px){.skru-how-grid{grid-template-columns:1fr;}}
.skru-step{background:rgba(255,255,255,.025);border:1px solid rgba(81,190,137,.1);border-radius:16px;padding:28px 22px;text-align:center;backdrop-filter:blur(10px);transition:transform .4s,border-color .4s;}
.skru-step:hover{transform:translateY(-4px);border-color:rgba(81,190,137,.3);}
.skru-step-num{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#51be89,#00875a);color:#fff;font-weight:900;font-size:1.15em;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;box-shadow:0 4px 16px rgba(81,190,137,.3);}
.skru-step-title{color:#fff !important;font-weight:700;font-size:1em;margin-bottom:8px;}
.skru-step-desc{color:rgba(255,255,255,.5);font-size:.88em;line-height:1.6;}
.skru-cta-box{background:linear-gradient(135deg,rgba(81,190,137,.12),rgba(0,135,90,.05));border:1px solid rgba(81,190,137,.28);border-radius:24px;padding:60px 40px;text-align:center;margin:40px 20px 80px;position:relative;z-index:2;overflow:hidden;}
.skru-cta-box::before{content:'';position:absolute;inset:0;background:radial-gradient(circle at 30% 50%,rgba(81,190,137,.08),transparent 60%);pointer-events:none;}
.skru-cta-box h2{font-size:2em !important;font-weight:900 !important;color:#fff !important;margin:0 0 14px !important;position:relative;z-index:1;}
.skru-cta-box p{color:rgba(255,255,255,.65) !important;font-size:1.05em !important;margin:0 0 32px !important;position:relative;z-index:1;}
.skru-cta-box .skru-btn{position:relative;z-index:1;}
@keyframes skru-grid-scroll{from{background-position:0 0}to{background-position:60px 60px}}
@keyframes skru-orb-drift{0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(40px,-30px) scale(1.08)}66%{transform:translate(-30px,20px) scale(.95)}}
@keyframes skru-particle-rise{0%{opacity:0;transform:translateY(100vh) scale(.4)}10%{opacity:1}90%{opacity:1}100%{opacity:0;transform:translateY(-10vh) scale(1.2)}}
html{scroll-behavior:smooth;}
</style>

[section bg_color="#0d0d0d" dark="true" padding="0"]
[row style="collapse" width="full-width"]
[col span__sm="12"]

<div class="skru-bg-wrap">
<div class="skru-bg-fx">
<div class="skru-bg-grid"></div>
<div class="skru-bg-orb skru-orb1"></div>
<div class="skru-bg-orb skru-orb2"></div>
<div class="skru-bg-orb skru-orb3"></div>
<div class="skru-particle"></div>
<div class="skru-particle"></div>
<div class="skru-particle"></div>
<div class="skru-particle"></div>
<div class="skru-particle"></div>
<div class="skru-particle"></div>
</div>

<div class="skru-ana-hero">
<h1>Your GA4 + Search Console.<br><span>In Plain English.</span></h1>
<p>Connect your Google account. Ask any question about your site. Get a clear, plain-English answer in under 60 seconds &mdash; powered by real data from Google Analytics 4 and Search Console.</p>
<div class="skru-btn-row">
<a href="https://analytics.seokru.com" class="skru-btn skru-btn-primary">Try It Free &rarr;</a>
<a href="#how-it-works" class="skru-btn skru-btn-secondary">See How It Works</a>
</div>
</div>

<div class="skru-stats-row">
<div class="skru-stat-box"><div class="skru-stat-num">&lt;60s</div><div class="skru-stat-lbl">Report generated<br>in under a minute</div></div>
<div class="skru-stat-box"><div class="skru-stat-num">11</div><div class="skru-stat-lbl">Preset report types<br>ready to run</div></div>
<div class="skru-stat-box"><div class="skru-stat-num">2-in-1</div><div class="skru-stat-lbl">GA4 + Search Console<br>joined in one report</div></div>
<div class="skru-stat-box"><div class="skru-stat-num">Free</div><div class="skru-stat-lbl">Free pilot &mdash;<br>no credit card</div></div>
</div>

<div class="skru-ana-section" style="position:relative;z-index:2;padding-bottom:60px;">

<div class="skru-section-hd">
<h2>Cross-Platform Reports &mdash; GA4 &times; Search Console</h2>
<p>Questions neither tool can answer alone. Answered in plain English.</p>
</div>

<div class="skru-cards-grid">

<div class="skru-card">
<span class="skru-badge">GA4 &times; GSC</span>
<div class="skru-card-top">
<div class="skru-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#51be89" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/><path d="M11 8v3l2 2"/></svg></div>
<h3 class="skru-card-title">Silent Winners</h3>
</div>
<p class="skru-card-desc">Pages ranking well in Search Console but barely getting clicks. Reveals title and intent gaps you can fix today for immediate CTR gains.</p>
</div>

<div class="skru-card">
<span class="skru-badge">GA4 &times; GSC</span>
<div class="skru-card-top">
<div class="skru-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#51be89" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
<h3 class="skru-card-title">Converting Queries Slipping</h3>
</div>
<p class="skru-card-desc">Your highest-converting landing pages matched against their Search Console rankings. Catches revenue risk before it hits your bottom line.</p>
</div>

<div class="skru-card">
<span class="skru-badge">GA4 &times; GSC</span>
<div class="skru-card-top">
<div class="skru-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#51be89" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg></div>
<h3 class="skru-card-title">Cannibalization Detector</h3>
</div>
<p class="skru-card-desc">Multiple URLs competing for the same query split your ranking power. We find the conflicts and tell you which URL should win.</p>
</div>

<div class="skru-card">
<span class="skru-badge">GA4 &times; GSC</span>
<div class="skru-card-top">
<div class="skru-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#51be89" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10"/><path d="M12 6v6l4 2"/><path d="M18 2v6h6"/></svg></div>
<h3 class="skru-card-title">Brand Rescue vs Real Growth</h3>
</div>
<p class="skru-card-desc">Is your overall traffic growing because of brand searches &mdash; or genuine non-brand SEO progress? We split the two and give you the honest answer.</p>
</div>

</div>

<div class="skru-divider"></div>

<div class="skru-section-hd">
<h2>Single-Source Preset Reports</h2>
<p>Deep dives into GA4 or Search Console independently.</p>
</div>

<div class="skru-cards-grid">

<div class="skru-card">
<div class="skru-card-top">
<div class="skru-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#51be89" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
<h3 class="skru-card-title">Content Decay</h3>
</div>
<p class="skru-card-desc">Which pages are losing traffic and by how much. Ranked by decline so you know where to focus your content refresh effort first.</p>
</div>

<div class="skru-card">
<div class="skru-card-top">
<div class="skru-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#51be89" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
<h3 class="skru-card-title">Striking-Distance Keywords</h3>
</div>
<p class="skru-card-desc">Keywords ranked position 4&ndash;20 with high impressions. These are your fastest wins &mdash; one content update away from page-one visibility.</p>
</div>

<div class="skru-card">
<div class="skru-card-top">
<div class="skru-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#51be89" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
<h3 class="skru-card-title">Conversion Leak</h3>
</div>
<p class="skru-card-desc">High-traffic pages that aren&rsquo;t converting. Identifies where your funnel is losing visitors before they take action.</p>
</div>

<div class="skru-card">
<div class="skru-card-top">
<div class="skru-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#51be89" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg></div>
<h3 class="skru-card-title">Keyword Rankings Pivot</h3>
</div>
<p class="skru-card-desc">Impression-weighted monthly average position for every query in your Search Console. Heatmap table. Filter by keyword. Export as PDF.</p>
</div>

<div class="skru-card">
<div class="skru-card-top">
<div class="skru-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#51be89" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8.56 2.75c4.37 6.03 6.02 9.42 8.03 17.72m2.54-15.38c-3.72 4.35-8.94 5.66-16.88 5.85m19.5 1.9c-3.5-.93-6.63-.82-8.94 0-2.58.92-5.01 2.86-7.44 6.32"/></svg></div>
<h3 class="skru-card-title">Brand vs Non-Brand</h3>
</div>
<p class="skru-card-desc">Split your Search Console queries into brand and non-brand. See whether your SEO effort is driving real non-brand growth or just brand recall.</p>
</div>

<div class="skru-card">
<div class="skru-card-top">
<div class="skru-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#51be89" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></div>
<h3 class="skru-card-title">Weekly Anomaly Scan</h3>
</div>
<p class="skru-card-desc">Automatically flags metrics that moved more than 20% this week across GA4 and Search Console. Know about traffic shifts before your client does.</p>
</div>

</div>

</div>

<a id="how-it-works" style="display:block;position:relative;top:-80px;visibility:hidden;"></a>

<div class="skru-ana-section" style="padding:40px 20px 0;">

<div class="skru-section-hd" style="text-align:center;">
<h2 style="text-align:center;">How It Works</h2>
<p style="text-align:center;">Four steps from zero to insight.</p>
</div>

<div class="skru-how-grid">
<div class="skru-step">
<div class="skru-step-num">1</div>
<div class="skru-step-title">Connect Google</div>
<div class="skru-step-desc">Sign in with Google OAuth. Read-only access &mdash; we can&rsquo;t modify anything in your account.</div>
</div>
<div class="skru-step">
<div class="skru-step-num">2</div>
<div class="skru-step-title">Select Your Site</div>
<div class="skru-step-desc">Pick your GA4 property and Search Console site from the dropdown. One-time setup.</div>
</div>
<div class="skru-step">
<div class="skru-step-num">3</div>
<div class="skru-step-title">Ask or Pick a Report</div>
<div class="skru-step-desc">Type a question in plain English or click any preset report card to run it instantly.</div>
</div>
<div class="skru-step">
<div class="skru-step-num">4</div>
<div class="skru-step-title">Read &amp; Share</div>
<div class="skru-step-desc">Get a plain-English report in under 60 seconds. Export as PDF or share a direct link.</div>
</div>
</div>

<div class="skru-cta-box">
<h2>Start reading your data in plain English.</h2>
<p>Free pilot. No credit card. GA4 + Search Console joined. Results in 60 seconds.</p>
<div class="skru-btn-row">
<a href="https://analytics.seokru.com" class="skru-btn skru-btn-primary">Connect Google &rarr;</a>
</div>
</div>

</div>

</div>

[/col]
[/row]
[/section]"""

def auth_header():
    token = base64.b64encode(f'{USER}:{PASS}'.encode()).decode()
    return {'Authorization': f'Basic {token}', 'Content-Type': 'application/json',
            'User-Agent': 'Mozilla/5.0 SeokruOAuthSetup/1.0', 'Accept': 'application/json'}

def api(method, url, body=None):
    data = json.dumps(body).encode('utf-8') if body else None
    req = urllib.request.Request(url, data=data, method=method, headers=auth_header())
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, json.loads(r.read())
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', errors='replace')

code, body = api('GET', f'{BASE}?slug=ai-analytics&_fields=id,link&per_page=5')
if code == 200 and body:
    pid = body[0]['id']
    code, body = api('POST', f'{BASE}/{pid}', {'content': CONTENT})
    if code == 200:
        print(f'Updated: {body["link"]}')
    else:
        print(f'FAILED {code}: {str(body)[:500]}')
else:
    print(f'not found: {code} {body}')
