# Google OAuth Verification — Prep Kit

Everything you need to publish the app to production and pass Google OAuth verification.

---

## Prerequisites to deploy FIRST

1. `git pull` on Cloudways to get `/privacy`, `/terms`, `/about` live:
   ```bash
   cd /home/325771.cloudwaysapps.com/qzyqpaznzq/public_html
   git pull && php artisan view:clear && php artisan route:clear
   ```
2. Verify these URLs return 200:
   - https://analytics.seokru.com/
   - https://analytics.seokru.com/about
   - https://analytics.seokru.com/privacy
   - https://analytics.seokru.com/terms

---

## Step 1 — Google Cloud Console: OAuth consent screen

Go to: https://console.cloud.google.com/apis/credentials/consent

### Basic info to paste

| Field | Value |
|---|---|
| **App name** | SEOKRU Analytics |
| **User support email** | info@seokru.com |
| **App logo** | Upload 120×120 PNG (see §Logo below) |
| **App domain — homepage** | https://analytics.seokru.com |
| **App domain — privacy** | https://www.seokru.com/privacy (after pasting PART A from PRIVACY_ADDENDUM.md) |
| **App domain — terms** | https://www.seokru.com/terms (after pasting PART B from PRIVACY_ADDENDUM.md) |
| **Authorized domains** | `seokru.com` (covers both www. and analytics. subdomains) |
| **Developer contact email** | info@seokru.com |

### Logo

You need a 120×120 PNG, square, no rounded corners. If you don't have one ready, make a quick one with the SEOKRU blue + an "SA" monogram, or use any brand asset. Save as `logo-120.png` and upload.

---

## Step 2 — Scopes

Two "restricted" scopes require justification:

### `https://www.googleapis.com/auth/analytics.readonly`

**Why needed (paste into justification box):**
> SEOKRU Analytics reads the user's own Google Analytics 4 metrics (sessions, users, page views, conversions, traffic sources, page paths) in order to generate plain-English reports summarizing the user's website performance. This scope is read-only; we do not modify, create, or delete any data in the user's Analytics account. The scope is essential because GA4 is one of the two primary data sources the product is built around; without it, we cannot compute page-level traffic trends, conversion analysis, or traffic-source breakdowns that make up the core reports (Content Decay, Conversion Leak, Silent Winners, Brand vs Non-Brand, and others).

**How the data is displayed to the user (paste):**
> Data is fetched on demand when the user clicks a report card or asks a question. The raw metrics are processed in our application (to compute deltas, percentage changes, rankings) and displayed in tabular form together with a narrative summary on analytics.seokru.com, or exported as a PDF the user downloads, or delivered to the user's Telegram chat if they explicitly connected the optional bot.

### `https://www.googleapis.com/auth/webmasters.readonly`

**Why needed:**
> SEOKRU Analytics reads the user's own Google Search Console data (queries, pages, impressions, clicks, position) to generate plain-English reports summarizing the user's search performance. This scope is read-only; we do not submit sitemaps, modify site settings, or change anything in Search Console. The scope is essential because Search Console is the second primary data source the product is built around; without it, we cannot compute keyword-level reports (Striking-Distance Keywords, Cannibalization Detector, Keyword Rankings Pivot, Brand vs Non-Brand) or the cross-platform reports that join Search Console queries with Analytics conversion data.

**How the data is displayed:** (same as above — swap "Analytics" → "Search Console")

---

## Step 3 — Demo video (YouTube unlisted)

Google requires a ~2-minute video showing:
1. The OAuth consent screen (with scopes visible)
2. The user approving scopes
3. The app using the data (showing a generated report)
4. Where privacy policy is linked

### Script for your screen recording

> "Hi, this is SEOKRU Analytics — a tool that reads your Google Analytics 4 and Search Console data and produces plain-English reports.
>
> I'm on analytics.seokru.com. I click **Connect Google**.
>
> I see the Google OAuth consent screen. It requests two read-only scopes: Google Analytics read-only, and Search Console read-only. I click Allow.
>
> I'm back on the app. I select my GA4 property and my Search Console site from the dropdowns.
>
> Now I can click a report card — let's pick **Content Decay**. The app fetches my Analytics data via the API, computes page-level traffic changes, and displays a summary like this one — with a table of the top declining pages and a written explanation.
>
> I can also ask a question in plain English — 'which keywords are ranking between position 4 and 20?' — and the app runs the matching Search Console query and answers.
>
> At the bottom of every page you can see **Privacy** and **Terms** links. The privacy policy explains exactly what data we read, how we use it, and that we comply with the Google API Services Limited Use policy.
>
> That's the full product. Thanks."

### Tips
- Use Loom, OBS, or QuickTime
- Show the URL bar so the domain is visible
- Upload to YouTube as **Unlisted**
- Paste the URL into the verification form

---

## Step 4 — Publish App button

In Google Cloud Console → OAuth consent screen → click **PUBLISH APP**.

Status goes from "Testing" → "In production". You'll then be prompted to start **verification**.

## Step 5 — Verification form

Google will email info@seokru.com. Respond from that inbox.

### Key questions and ready-to-paste answers:

**Q: Will your application be used by users outside of your Google Workspace domain?**
> Yes. The application is designed for any Google user with a GA4 property or Search Console site.

**Q: Will you store user data on your servers?**
> Yes. We store the OAuth access token and refresh token (used to call Google APIs on the user's behalf), the user's Google email and user ID, their selected GA4 property ID and GSC site URL, and the reports they generate. This is disclosed in our Privacy Policy at https://analytics.seokru.com/privacy §4.

**Q: How will you ensure user data is kept safe?**
> All traffic to analytics.seokru.com uses HTTPS with TLS 1.2+. OAuth tokens are stored in a MySQL database on a Cloudways-managed Vultr instance with filesystem-level access restricted to the application process. Google API responses are cached for at most 12 hours and scoped per user connection. We do not log raw API responses in plain text. We do not share data with third parties except LLM providers (Groq, Gemini) which receive only computed numerical metrics and labels, never OAuth tokens or PII.

**Q: Limited Use affirmation:**
> We affirm that our use and transfer of information received from Google APIs to any other app will adhere to the Google API Services User Data Policy, including the Limited Use requirements. Specifically: (1) we use Google user data only to provide or improve user-facing features that are prominent in the requesting application's user interface; (2) we do not transfer Google user data to third parties except as necessary to provide or improve those features, to comply with law, or as part of a merger/acquisition (with user notice); (3) we do not use Google user data for serving advertisements; (4) we do not allow humans to read Google user data except with explicit user consent for specific messages, for security investigations, to comply with law, or where the data has been aggregated and anonymized.

---

## Step 6 — Security assessment (CASA)

For restricted scopes, Google **may** require a CASA (Cloud Application Security Assessment) Tier 2 audit — costs $2k-$15k. This is the main blocker.

**Current Google policy (2024-2025):** For apps that only use restricted scopes to access the user's own data (not data about other users), CASA is often NOT required as long as you pass the verification review. Your app fits that profile — you only ever read the signed-in user's own GA4 and GSC data.

If Google does require CASA:
- They'll send instructions
- You can request a Self-Assessment Questionnaire first (free, faster)
- Only escalate to paid audit if they push back

---

## Timeline expectation

- Testing mode → Publish: **instant**
- Email back from Google verification team: **1-3 weeks**
- Clarifications back-and-forth: **1-2 more weeks**
- Approval: **4-8 weeks total** typical
- During this time: app works, but users see "unverified app" warning

---

## What to do RIGHT NOW

1. Deploy: `git pull` on Cloudways
2. Visit https://analytics.seokru.com/privacy + /terms + /about — confirm they render
3. Create a 120×120 PNG logo (or send me what you have and I'll help)
4. Go to Google Cloud Console → OAuth consent screen → fill in the fields from §Step 1 above
5. Click **Publish App**
6. When Google emails, use the answers in §Step 5
7. Record the demo video with the script in §Step 3 when asked

---

## Contact email alias

Google expects `info@seokru.com` to be monitored. Make sure it forwards to your inbox — verification emails will arrive there.
