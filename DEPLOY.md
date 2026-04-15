# Deployment Runbook

Written during the overnight batch. Morning deploy checklist.

## This session's commits

Latest first. Cherry-pick what to deploy.

| Commit | What it does | Migration? | Env change? |
|---|---|---|---|
| `9d6b83f` Telegram scaffold | Bot → agent pipe | Yes (`chat_bindings`) | Yes (3 vars) |
| `3ded7ac` Delta pre-compute | Fix LLM math hallucination | No | No |
| `5b314c8` Saved/recent + 4 cross-platform reports | Feature batch | Yes (`saved_queries`) | No |
| `1f4206b` Landing hero + remember cookie | UX + persistence | No | No |

## Safe deploy-all (Cloudways SSH)

```bash
cd ~/applications/qzyqpaznzq/public_html
git pull
php artisan migrate --force
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

Everything before Telegram works with zero env changes.

## Telegram activation (optional, after deploy)

Only needed if you want to turn on the Telegram bot today.

### 1. Create bot via BotFather
- Telegram → search `@BotFather`
- `/newbot` → name → username (must end in `bot`)
- Copy the token it gives you

### 2. Add to `.env` on Cloudways
```
TELEGRAM_BOT_TOKEN=123456:ABC-DEF_your_token_here
TELEGRAM_WEBHOOK_SECRET=<run: php -r "echo bin2hex(random_bytes(16));">
TELEGRAM_BOT_USERNAME=your_bot_name_bot
```

### 3. Set the webhook
```bash
php artisan telegram:set-webhook
```
Should print `"ok":true`. If it errors, check:
- Cloudways domain has valid HTTPS (yes, `analytics.seokru.com` does)
- `APP_URL=https://analytics.seokru.com` is set in `.env`

### 4. Test
- Message your bot: `/start`
- Click the "Connect Google" link
- Return to Telegram, ask: "top pages last 7 days"

## Rollback individual commit

If any commit breaks prod:
```bash
git revert <commit-hash>
git push origin main
# then on Cloudways:
git pull
php artisan config:clear
```

Migrations can be rolled back with `php artisan migrate:rollback --step=1` but only if that's the latest migration.

## Env vars reference (full)

Required (existing):
- `APP_URL=https://analytics.seokru.com`
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- `GEMINI_API_KEY`, `GEMINI_MODEL=gemini-2.5-flash-lite`
- `SESSION_LIFETIME=43200`
- DB credentials (Cloudways-managed)

Optional (per feature):
- `RESEND_API_KEY` — email delivery (not active yet)
- `TELEGRAM_BOT_TOKEN` — enables Telegram bot
- `TELEGRAM_WEBHOOK_SECRET` — webhook signature check
- `TELEGRAM_BOT_USERNAME` — enables "Open bot" button on /telegram-connected

## Quick smoke test after deploy

1. Visit `https://analytics.seokru.com` — landing loads
2. Click a preset → OAuth → connect → generate. Check narrative has no contradictions.
3. Try Ask with "Compare mobile vs desktop conversion last 7 days vs previous 7 days" — verify all pct_change values match the raw numbers.
4. On /ask, save a query, refresh, verify chip appears. Click chip → textarea fills. × → deletes.
5. If Telegram deployed: `/start` in bot → connect → ask.

## Known limitations

- Gemini free tier = 20 req/day. Agent can burn several per query if the loop runs.
- Telegram messages >4000 chars auto-chunk but lose formatting coherence across chunks.
- Chart images go to Telegram as separate photos *before* the text — ok but not ideal ordering.
- Cross-platform reports assume both GA4 property + GSC site are selected. If only one, they'll fail silently.
