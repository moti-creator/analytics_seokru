"""Create www.seokru.com/services/ai-analytics/ product page via WP REST API."""
import json, urllib.request, urllib.error, base64

USER = 'n8n_seokru'
PASS = 'gkef aSUL Cg8f nvDl Rhz6 d1gm'
BASE = 'https://www.seokru.com/wp-json/wp/v2/pages'
PARENT_ID = 1487  # /services/

CONTENT = """<h2>Your GA4 + Search Console data. In plain English. In 60 seconds.</h2>
<p>SEOKRU Analytics is a free tool that connects to your Google Analytics 4 and Google Search Console accounts and turns raw data into reports you can actually read — no dashboards, no SQL, no pivot tables.</p>
<p><a href="https://analytics.seokru.com" style="display:inline-block;background:#1a73e8;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:1.05rem">Try It Free &rarr;</a></p>

<h2>What it does</h2>
<p>You connect your Google account (read-only OAuth). We fetch your GA4 and Search Console data, compute the math, and write a human-readable report in under a minute. No setup. No configuration.</p>

<h3>Ask anything in plain language</h3>
<p>Type a question like <em>"Which blog posts lost the most organic traffic last month?"</em> or <em>"Are my non-brand keywords growing?"</em> and get a straight answer with supporting data.</p>

<h3>Or pick a preset report</h3>
<ul>
<li><strong>Silent Winners</strong> &mdash; pages ranking well but barely clicked. Title and intent gaps.</li>
<li><strong>Content Decay</strong> &mdash; pages losing traffic over time, ranked by decline.</li>
<li><strong>Striking-Distance Keywords</strong> &mdash; queries at position 4&ndash;20 ready to push to page 1.</li>
<li><strong>Converting Queries Slipping</strong> &mdash; revenue pages losing Google rank.</li>
<li><strong>Cannibalization Detector</strong> &mdash; multiple URLs fighting for the same query.</li>
<li><strong>Conversion Leak</strong> &mdash; high-traffic pages that aren&rsquo;t converting.</li>
<li><strong>Brand vs Non-Brand Split</strong> &mdash; is brand traffic masking non-brand decay?</li>
<li><strong>Weekly Anomaly Scan</strong> &mdash; metrics that moved more than 20% this week.</li>
<li><strong>Keyword Rankings Pivot</strong> &mdash; impression-weighted monthly position table, all your queries.</li>
</ul>

<h2>Why GA4 + Search Console together?</h2>
<p>GA4 tells you what users did on your site. Search Console tells you what they searched for. Joined together, you can answer questions neither tool can answer alone &mdash; like which high-ranking pages have the worst conversion rate, or which queries drive traffic but never lead to a sale.</p>
<p>Most tools show you one or the other. SEOKRU Analytics joins both and writes the answer in plain English.</p>

<h2>How it works</h2>
<ol>
<li>Sign in with Google &mdash; read-only access to your GA4 and Search Console</li>
<li>Select your property and site from the dropdown</li>
<li>Ask a question or click a preset report card</li>
<li>Get a plain-English report in under 60 seconds</li>
<li>Export as PDF or share a link</li>
</ol>

<h2>Powered by AI &mdash; grounded in your real data</h2>
<p>We fetch the raw numbers from Google&rsquo;s APIs, compute all the deltas and rankings in our own code, then pass the results to an LLM to write the narrative. The AI writes English &mdash; it doesn&rsquo;t do the math. That means no hallucinated percentages, no made-up trends.</p>

<h2>Free pilot</h2>
<p>SEOKRU Analytics is currently free to use. No credit card. No trial period. We&rsquo;re validating the product with real users before adding paid features. Early users get grandfathered pricing when we launch paid tiers.</p>
<p><a href="https://analytics.seokru.com" style="display:inline-block;background:#1a73e8;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:700;font-size:1.05rem">Connect Google &rarr;</a></p>

<h2>Privacy &amp; data handling</h2>
<p>Read-only access only. We cannot modify, delete, or create anything in your Google account. OAuth tokens are stored securely on our servers and used only to fetch your data on demand. Full details in our <a href="/legal/privacy/">Privacy Policy</a>.</p>"""

def auth_header():
    token = base64.b64encode(f'{USER}:{PASS}'.encode()).decode()
    return {
        'Authorization': f'Basic {token}',
        'Content-Type': 'application/json',
        'User-Agent': 'Mozilla/5.0 SeokruOAuthSetup/1.0',
        'Accept': 'application/json',
    }

def api(method, url, body=None):
    data = json.dumps(body).encode('utf-8') if body else None
    req = urllib.request.Request(url, data=data, method=method, headers=auth_header())
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            return r.status, json.loads(r.read())
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode('utf-8', errors='replace')

# Check if page already exists
code, body = api('GET', f'{BASE}?slug=ai-analytics&parent={PARENT_ID}&_fields=id,slug,link')
if code == 200 and body:
    print(f'Page already exists: {body[0]["link"]} (id={body[0]["id"]})')
else:
    code, body = api('POST', BASE, {
        'title': 'AI Analytics — GA4 + Search Console Reports in Plain English',
        'slug': 'ai-analytics',
        'parent': PARENT_ID,
        'status': 'publish',
        'content': CONTENT,
        'excerpt': 'Connect GA4 and Search Console. Ask questions in plain English. Get a report in 60 seconds. Free pilot — no credit card.',
    })
    if code == 201:
        print(f'Created: {body["link"]}')
    else:
        print(f'FAILED {code}: {str(body)[:400]}')
