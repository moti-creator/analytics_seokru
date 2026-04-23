# SEOKRU Analytics — Project Context

Laravel SaaS pilot. GA4 + GSC → plain-English reports via LLM. Free pilot/MVP phase.

## Stack

- Laravel 10.50.2, PHP 8.3.30
- Google OAuth via `laravel/socialite` (GA4 Data API, GA4 Admin, GSC)
- LLM: **Groq (Llama 3.3 70B) primary**, Gemini fallback
- Charts: QuickChart.io (image URLs, works in web + PDF)
- PDF: `barryvdh/laravel-dompdf`
- Email: `resend/resend-laravel` (installed, not wired up yet)
- Telegram Bot API (code done, needs `php artisan telegram:set-webhook` + env vars to activate)
- Cache: file driver, 12h per connection+type+date

## Paths

- **Local dev**: `C:/Users/Lenovo/Documents/Claude/Projects/seokru/analytics_pilot`
- **Cloudways prod**: `/home/325771.cloudwaysapps.com/qzyqpaznzq/public_html`
- **Domain**: analytics.seokru.com
- **Repo**: https://github.com/moti-creator/analytics.seokru.com

## SSH / Deploy

```bash
ssh master_vultr_ath@104.238.128.199
# SSH key alias: cc1
cd /home/325771.cloudwaysapps.com/qzyqpaznzq/public_html
git pull && php artisan view:clear && php artisan route:clear
# only if config changed:
php artisan config:clear
```

Laravel config is NOT cached in prod — `env()` reads direct.

## Env vars (Cloudways `.env`)

- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI=https://analytics.seokru.com/auth/google/callback`
- `GROQ_API_KEY` (gsk_...) — primary LLM
- `GEMINI_API_KEY` — fallback
- `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, `TELEGRAM_BOT_USERNAME` — not set yet
- `RESEND_API_KEY` — not set yet

## Architecture

### Flow (unified single-screen on `/`)

3 states on landing page, driven by `$conn` + `$hasProperty`:
1. Not logged in → big "Connect Google" CTA, cards grayed
2. Logged in, no property → big orange property picker box, cards still grayed
3. Property selected → textbox + cards live, small prop switcher in status bar

### Services

- `GoogleService` — OAuth token refresh + GA4/GSC data fetchers. `fetchGscQueryPivot()` does paginated query×date pull for Keyword Rankings reports.
- `GroqService` — OpenAI-compatible wrapper. Null-safe config. Supports tool calling.
- `GeminiService::raw()` — prefers Groq if key present, falls back to Gemini.
- `AgentService` — dual-backend function-calling agent. Maintains parallel Gemini + OpenAI histories. Switches backend on 429.
- `ReportBuilder` — 11 report types. `build()` wrapped in `Cache::remember()` 12h. Pre-computes deltas in PHP so LLM doesn't hallucinate math. `injectCharts()` for QuickChart images.
- `TelegramService` — HTML cleaning, auto-chunking at 4000 chars, separate sendPhoto for charts.

### Report types (TYPES array in ReportBuilder)

Cross-platform (GA4 × GSC):
- `silent_winners`, `converting_queries`, `cannibalization`, `brand_rescue`

Single-source:
- `content_decay`, `striking_distance`, `conversion_leak`, `anomaly`, `brand_split`
- `keyword_rankings` (GSC web), `keyword_rankings_news` (GSC news) — pivot table, landscape A4 PDF

### Key routes

- `GET /` → `ReportController@landing`
- `POST /ask/start` → save prompt + route through OAuth/property gates
- `GET /generate/{type}` → `generateDirect` (cards on landing)
- `GET /r/{slug}` / `/r/{slug}/pdf`
- `GET /auth/google` → Socialite redirect (accepts `?tg_token` for Telegram binding)
- `POST /webhooks/telegram` → bot webhook

### Session persistence

- `remember_connection` cookie (30d) + `RestoreConnection` middleware rehydrates session

## Common tasks

### Add new report type
1. Add key to `ReportBuilder::TYPES` (+ `PREBUILT_TYPES` if preset card)
2. Add `fooReport($g)` method returning `['type','title','metrics','narrative']`
3. Add switch case in `build()`
4. Add card in `resources/views/landing.blade.php`

### Debug LLM call
- Check `storage/logs/laravel.log` — GroqService + Gemini both log errors
- `config('services.groq.key')` returns null if env var missing → GroqService handles gracefully now

### PDF issues
- `report.blade.php` has `$isPdf` flag for compact layout
- Landscape A4 for keyword_rankings variants (see `ReportController::pdf`)

## User/business context

- Target: small businesses + agencies
- User (Moti) is in Israel — Stripe problematic, uses upay.co.il
- Declined (do not suggest): Google Ads integration, WhatsApp bot, agency white-label
- Tagline: "GA4 + Search Console in one plain-English report"

## Known pending

- Deploy Telegram bot (code ready, needs BotFather + env vars)
- Rotate exposed Groq key
- Sample/demo report without login
- Weekly scheduled reports (cron)
- Loading spinner during generation
- Email delivery wired up
- Payment flow (upay.co.il, not Stripe)

## Gotchas

- `config()` returns null for missing keys, ignoring default param. Use `config('x.y') ?? 'default'` or `env()` with default in config file.
- LLM output sometimes has backslash-escaped URLs (`\/path`) — `report.blade.php` does `str_replace('\\/', '/')`
- Pre-compute ALL deltas in PHP before LLM call — prompt says "USE THEM EXACTLY", prevents math hallucination
- Cache key includes date, so stale responses drop daily automatically
