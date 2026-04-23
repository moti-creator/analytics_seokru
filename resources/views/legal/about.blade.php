@extends('legal.layout')
@section('title', 'About — SEOKRU Analytics')
@section('description', 'SEOKRU Analytics joins Google Analytics 4 and Search Console and answers questions in plain English.')

@section('content')

<h1>About SEOKRU Analytics</h1>

<p><strong>SEOKRU Analytics</strong> is a plain-English reporting layer on top of Google Analytics 4 and Google Search Console. It is operated by <a href="https://seokru.com" target="_blank" rel="noopener">SEOKRU</a>, a digital marketing agency.</p>

<h2>What it does</h2>
<p>You connect your Google account. We fetch your own data from the GA4 and GSC APIs (read-only) and turn it into reports that read like a human analyst wrote them. No dashboards to configure. No SQL. No pivot tables.</p>

<h2>Who it's for</h2>
<ul>
<li>Small business owners who want to know what their analytics actually mean</li>
<li>Marketing managers who need weekly summaries without logging into five tools</li>
<li>Agencies who want to send clients readable reports instead of PDFs full of charts</li>
</ul>

<h2>How it works</h2>
<ol>
<li>You sign in with Google (OAuth, read-only scopes for GA4 and Search Console)</li>
<li>You pick which GA4 property and/or Search Console site to analyze</li>
<li>You either ask a question in plain language or pick a preset report</li>
<li>We call the Google APIs, compute the math in our own code, and pass the numbers to a large language model to write the narrative</li>
<li>You get a readable report — in the browser, as a PDF, or via a Telegram bot (optional)</li>
</ol>

<h2>What data we use</h2>
<p>Read-only access to:</p>
<ul>
<li><code>analytics.readonly</code> — GA4 metrics (sessions, conversions, pages, sources)</li>
<li><code>webmasters.readonly</code> — Search Console queries, pages, impressions, clicks, position</li>
</ul>
<p>We do not write to, modify, or delete anything in your Google account. Full details in the <a href="/privacy">Privacy Policy</a>.</p>

<h2>Pricing</h2>
<p>The Service is currently a <strong>free pilot</strong>. If we add paid tiers later, existing pilot users will be notified and given reasonable time to decide.</p>

<h2>Contact</h2>
<p>Feature requests, bug reports, questions: <a href="mailto:info@seokru.com">info@seokru.com</a></p>

@endsection
