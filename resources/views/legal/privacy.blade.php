@extends('legal.layout')
@section('title', 'Privacy Policy — SEOKRU Analytics')
@section('description', 'How SEOKRU Analytics handles your Google account data.')

@section('content')

<h1>Privacy Policy</h1>
<p class="meta">Last updated: April 23, 2026</p>

<p>SEOKRU Analytics ("we", "us", the "Service") is a free pilot tool operated by SEOKRU that connects to your Google Analytics 4 (GA4) and Google Search Console (GSC) accounts, fetches your own data, and generates plain-English reports. This policy explains what data we access, how we use it, how we store it, and your rights.</p>

<div class="callout">
<strong>Google API Services User Data Policy — Limited Use.</strong> Our use and transfer of information received from Google APIs adheres to the <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>, including the Limited Use requirements.
</div>

<h2>1. What Google data we access</h2>
<p>When you sign in with Google, we request the following read-only scopes:</p>
<ul>
<li><code>https://www.googleapis.com/auth/analytics.readonly</code> — read your GA4 properties, their names/IDs, and traffic metrics (sessions, users, conversions, pages, sources, etc.)</li>
<li><code>https://www.googleapis.com/auth/webmasters.readonly</code> — read your Search Console sites and query/page/impression/click/position data</li>
<li>Your Google profile email and user ID (basic identification, used to persist your session)</li>
</ul>
<p>We do <strong>not</strong> request write access. We cannot modify, delete, or create anything in your Google account.</p>

<h2>2. How we use your Google data</h2>
<p>We use the data you authorize us to access <strong>only</strong> to:</p>
<ul>
<li>Fetch metrics from GA4 and GSC APIs at your request (either when you click a report, ask a question, or schedule a recurring report)</li>
<li>Compute derived statistics (deltas, percentage changes, averages, rankings)</li>
<li>Generate plain-English narrative summaries of your data</li>
<li>Display the resulting report to you in the browser, as a PDF, or (optionally) via a Telegram bot you explicitly connect</li>
</ul>
<p>We do <strong>not</strong> use your Google data for advertising, profiling, cross-user analytics, training AI/ML models, or any purpose other than serving the specific report you requested.</p>

<h2>3. Third-party LLM providers</h2>
<p>To convert the numerical data we fetch into readable sentences, we send the <strong>computed metrics</strong> (numbers, labels, dates — not your Google credentials) to large-language-model APIs:</p>
<ul>
<li><strong>Groq</strong> (Llama 3.3 70B, hosted by Groq Inc.)</li>
<li><strong>Google Gemini</strong> (fallback)</li>
</ul>
<p>These providers process the request and return text. Per their respective terms, inputs are not used to train public models. We never send Google access tokens, refresh tokens, your email, or any personally-identifying credentials to these providers.</p>

<h2>4. What we store</h2>
<p>In our own database (hosted on Cloudways / Vultr):</p>
<ul>
<li>Your Google email and Google user ID (to identify your account)</li>
<li>An OAuth access token and refresh token (used to call Google APIs on your behalf)</li>
<li>Your selected GA4 property ID and GSC site URL</li>
<li>The reports you generate (title, metrics JSON, narrative HTML)</li>
<li>Queries you explicitly save</li>
<li>If you connect the Telegram bot: your Telegram chat ID and its binding to your account</li>
</ul>
<p>Responses from Google APIs are cached for up to 12 hours to reduce API calls and improve speed. Cached data is scoped to your connection and expires automatically.</p>

<h2>5. What we do NOT do</h2>
<ul>
<li>We do not sell, rent, or share your data with advertisers or data brokers</li>
<li>We do not use your Google data to train models (ours or anyone else's)</li>
<li>We do not read or show your data to human operators, except when you explicitly request support and we need to debug a specific issue you reported</li>
<li>We do not serve ads of any kind</li>
</ul>

<h2>6. Data retention and deletion</h2>
<p>You can revoke our access at any time by visiting <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">your Google Account permissions page</a> and removing "SEOKRU Analytics".</p>
<p>To delete your account and all associated data from our database, email <a href="mailto:info@seokru.com">info@seokru.com</a> with the subject "Delete my account". We will delete your connection record, tokens, reports, and saved queries within 7 days and confirm by email.</p>

<h2>7. Security</h2>
<p>All traffic to analytics.seokru.com is served over HTTPS. OAuth tokens are stored in our application database with filesystem-level access restricted to the application server. We do not log raw Google API responses in plain text.</p>

<h2>8. Children</h2>
<p>The Service is not directed at children under 13. We do not knowingly collect data from children.</p>

<h2>9. Changes to this policy</h2>
<p>We may update this policy as the product evolves. Material changes will be announced on this page with a new "Last updated" date. Continued use after changes constitutes acceptance.</p>

<h2>10. Contact</h2>
<p>Questions, data requests, or complaints: <a href="mailto:info@seokru.com">info@seokru.com</a></p>

@endsection
