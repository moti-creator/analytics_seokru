# SEOKRU Analytics — Positioning Draft

Written during the night — pick one, kill the rest.

## The core tension to resolve

Current tagline: **"Answers GA4 won't give you"**

Problem: GA4 *does* have a decent "Ask Analytics Intelligence" feature now, plus Gemini integration. Competitors (and skeptical prospects) can refute the claim in 10 seconds. The tagline picks a fight you can lose.

The real wedge is narrower and more defensible:

1. **Cross-platform joins** — GA4 + Search Console in one answer (GA4's AI cannot read GSC data)
2. **Shareable deliverable** — client/boss gets a URL + PDF, not a GA4 dashboard they won't open
3. **Plain English, no login** — works without the recipient having Google Analytics access
4. **Agency-ready** (future) — white-label, scheduled weekly digests

Positioning should lean on #1 and #2. #3 is the killer moat once Telegram/WhatsApp land.

---

## Headline candidates

### A. Data-angle (lean on the join)
- *GA4 + Search Console in one plain-English report.*
- *The report GA4's AI can't write — because it doesn't see Search Console.*
- *Join Google Analytics with Search Console. Get the answer.*
- *Your GA4 data, your Search Console data, one question at a time.*

### B. Deliverable-angle (lean on shareability)
- *Weekly SEO reports your clients actually read.*
- *Analytics reports in plain English. Shareable. Understandable. Done.*
- *Stop exporting GA4 screenshots. Send a link.*

### C. Outcome-angle (lean on insight)
- *Find the pages losing money to bad titles.*
- *The keywords you're losing. The pages that leak. The queries that mask decay.*
- *Your analytics, explained like your smartest analyst would.*

### D. Agency-angle (lean on white-label future)
- *White-label SEO reports for agencies. GA4 + GSC. Branded. Weekly.*
- *Give every client a weekly analytics summary. Without writing it.*

### E. Anti-positioning (pick a fight you can win)
- *GA4's AI is a chatbot. This is an analyst.*
- *Not another dashboard. An answer.*
- *Dashboards are for executives. This is for operators.*

### My pick

**Headline:** *GA4 + Search Console in one plain-English report.*
**Sub:** *Ask any question about your site's traffic. Our agent pulls the data from Analytics AND Search Console, computes the math, and writes the answer in plain English. In 60 seconds.*
**CTA:** *Ask your first question — free, no credit card.*

Why: defensible (GA4's AI truly can't do GSC), concrete (both products named), promise-bounded (60 sec), low-commitment (free).

---

## Hero textbox copy variants

Current placeholder is generic. Better:

### Option 1 (concrete examples baked in)
```
Ask anything. Example: "Which blog posts lost rank for their top
converting queries this month, and how much revenue did I lose?"
```

### Option 2 (invitation)
```
What do you want to know about your site this week?
```

### Option 3 (pain-led)
```
The question your analytics dashboard can't answer, in plain English.
```

My pick: **Option 1** — concrete example does more selling than abstract copy.

---

## Landing page structure (recommendation)

Current: hero textbox → cross-platform cards → single-source cards → footer.

Better sequence:

1. **Hero** — headline + sub + textbox with concrete-example placeholder + 2-3 clickable example chips
2. **"What makes this different"** — 3 columns:
   - *Joins GA4 + Search Console* (icon, one line)
   - *Plain English, not dashboards* (icon, one line)
   - *Shareable link + PDF* (icon, one line)
3. **Cross-platform reports** — the 4 purple cards (these are the moat)
4. **Single-source presets** — 5 blue cards (commodity, below the fold)
5. **Social proof** — testimonial or sample report screenshot (when available)
6. **Footer** — pilot badge, logout if logged in

The "3 columns" section is what currently sells you short. Adding it makes the differentiation explicit before a visitor has to click around to figure it out.

---

## Meta tags (for SEO / social previews)

```html
<title>SEOKRU Analytics — GA4 + Search Console reports in plain English</title>
<meta name="description" content="Ask any question about your site's traffic. We join Google Analytics 4 with Search Console data and answer in plain English. In 60 seconds.">
<meta property="og:title" content="GA4 + Search Console, in one plain-English report">
<meta property="og:description" content="The analytics report your dashboard can't write.">
```

---

## One-liners for outbound (cold email / DMs / LinkedIn)

Pick based on audience.

**To agency owners:**
> "Weekly SEO reports for your clients — GA4 + Search Console joined, plain English, white-label-ready. I'm piloting free with 5 agencies. Want in?"

**To in-house marketers:**
> "I built a tool that answers questions like 'which blog posts lost rank for their top converting queries' — joins GA4 + Search Console. Free pilot. Try it?"

**To founders:**
> "What if you could ask Google Analytics 'is my SEO actually working' and get an honest English answer instead of a dashboard? That's what I built. Pilot's free."

---

## What NOT to claim (yet)

- Don't promise: weekly email digests, Stripe billing, white-label — not built
- Don't claim: anomaly detection is "better than GA4" — GA4's is fine, ours is complementary
- Don't claim: specific ROI numbers — no case studies yet
- Don't claim: enterprise/SOC2/GDPR — pilot, not ready

---

## Next content work (suggested order)

1. Replace landing hero copy with pick above
2. Add the "3 columns" differentiation section
3. Write 1 blog post: *"The 4 analytics reports GA4's AI cannot write"* (SEO for "GA4 Gemini limitations", "GA4 + GSC combined report", etc.)
4. Record a 60-second Loom demo of the Ask flow
5. First 10 outbound DMs to agencies using the pitch above

---

## Open questions to resolve before paid tier

- Who is the primary buyer — agency owner, in-house marketer, or founder? Pricing differs wildly.
- Is the agency white-label angle a *positioning* play or a *product* play?
- At what volume of reports does the Gemini cost become a gross-margin problem?
- Is "Ask anything" actually more valuable than presets, or does the free-form tab just look impressive while most usage is preset?

Track in metrics from pilot: ratio of ask:preset usage, repeat usage per user, save-query adoption.
