# Addendum to paste into www.seokru.com

Required to pass Google OAuth verification for SEOKRU Analytics app.

---

## PART A — Add to www.seokru.com/privacy

Add this as a new section, ideally near the top or after the existing "Google Analytics" mention:

---

### SEOKRU Analytics App (analytics.seokru.com) — Google API Data

SEOKRU operates a separate application at **analytics.seokru.com** ("the App") which connects to users' own Google Analytics 4 and Google Search Console accounts via Google OAuth. This section describes how that App handles Google user data.

**Google API Services User Data Policy — Limited Use.** SEOKRU's use and transfer of information received from Google APIs to any other app adheres to the [Google API Services User Data Policy](https://developers.google.com/terms/api-services-user-data-policy), including the Limited Use requirements.

**Scopes requested.** When a user signs in to the App with Google, we request the following read-only scopes:

- `https://www.googleapis.com/auth/analytics.readonly` — read the user's GA4 properties and traffic metrics
- `https://www.googleapis.com/auth/webmasters.readonly` — read the user's Search Console sites and query/page/impression/click/position data
- Basic profile email and user ID (to identify the session)

We do not request write access. We cannot modify, delete, or create anything in the user's Google account.

**How we use Google data.** We fetch data from the Google APIs on demand when the user clicks a report or asks a question, compute derived statistics (deltas, percentage changes, rankings) in our application, and display the result to the user as a report, a PDF, or a message to their Telegram bot (if they explicitly connected it). We do not use Google data for advertising, cross-user analytics, model training, or any purpose other than serving the specific report the user requested.

**Third-party LLM providers.** To convert the numbers we fetch into readable sentences, we send computed metrics (numbers, labels, dates — never OAuth tokens or personally-identifying credentials) to large-language-model APIs: Groq (Llama 3.3 70B) and Google Gemini. These providers return text. Per their terms, inputs are not used to train public models.

**What we store.** In our own database: the user's Google email and user ID, an OAuth access token and refresh token, the user's selected GA4 property ID and Search Console site URL, generated reports, and explicitly saved queries. Google API responses are cached for at most 12 hours per user connection.

**Revoke and delete.** Users can revoke the App's access at any time at [https://myaccount.google.com/permissions](https://myaccount.google.com/permissions). To request full deletion of all App data associated with their account, users email **info@seokru.com** with subject "Delete my account"; we will delete tokens, connection records, and reports within 7 days and confirm.

**Sharing.** We do not sell, rent, or share Google user data with advertisers or data brokers. We do not use Google data to train ML models. We do not allow humans to read Google user data except with explicit user consent for support cases, for security investigations, or to comply with law.

For App-specific questions: **info@seokru.com**.

---

## PART B — Add to www.seokru.com/terms

Add this as a new section (e.g., after "Professional Services"):

---

### SEOKRU Analytics App

SEOKRU operates a separate free pilot application at **analytics.seokru.com** which connects to users' own Google Analytics 4 and Google Search Console accounts via Google OAuth to generate plain-English reports.

Use of the App is subject to these Terms together with the supplementary terms published on the App itself. By connecting a Google account to the App, the user warrants they have authority to grant access to the Google properties connected, and agrees to comply with Google's Terms of Service for GA4 and Search Console.

The App is provided "as is" during the pilot. Reports include narrative text produced by AI models and may contain errors; users should verify critical numbers against the source Google tools before making business decisions. SEOKRU's liability for the App is limited as described in the Limitation of Liability section above.

---

## After pasting

Once PART A is live at www.seokru.com/privacy and PART B is live at www.seokru.com/terms, you can use these as the OAuth verification URLs:

- **Privacy policy URL:** `https://www.seokru.com/privacy`
- **Terms of service URL:** `https://www.seokru.com/terms`
- **Homepage URL:** `https://analytics.seokru.com`
- **Authorized domains:** `seokru.com` (covers both www.seokru.com and analytics.seokru.com)

The analytics.seokru.com/privacy and /terms pages we built can stay as secondary/internal backups — they don't hurt.
