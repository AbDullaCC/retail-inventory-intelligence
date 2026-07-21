# Shelfwise — pitch deck draft (v1)

> **Purpose:** the content draft we iterate on, then hand to Claude Code to
> generate slides. One `## Slide N` block = one slide. Each block has
> **On slide** (what's rendered — keep sparse and visual) and **Say** (speaker
> notes carrying the story; the slide is the backdrop, you are the pitch).
>
> **⟦REFRESH⟧ marks numbers that change daily** — re-quote them the morning of
> the pitch (`inventory:import-retail --fresh` → `forecast:run` →
> `chatbot:evaluate` → `inventory:insights`). Q&A prep lives in
> `Pitch-Defense-QA.md`; compact bullets in `Pitch-Judge-QA.md`.
>
> **Timing budget (total ~15–17 min):** Problem 2–3 · Research 3–4 · Solution 3
> · Live demo 4–5 · Business 2–3.
>
> **Slide-generation notes for Claude Code:** minimal text per slide (≤5 short
> bullets or one big visual), large stat callouts, brand = "Harbor" teal
> (`brand-*` tokens, see `Apps/Frontend/src/index.css`), Inter font, dark-on-light,
> no clip-art. Charts referenced below exist in the product — screenshot them
> rather than redrawing.

---

# 01 · THE PROBLEM (2–3 min)

## Slide 1 — Title

**On slide:**
- **Shelfwise**
- *Know what to reorder before you run out.*
- Name · date · committee

**Say (20s):** One sentence of who you are, then straight into the story — no
agenda slide.

---

## Slide 2 — Retail's two ways to lose money

**On slide (big stat layout):**
- **$1.77 T / year** — global cost of inventory distortion (IHL Group)
- split: **$1.2 T stockouts** (lost sales) · **$550 B overstocks** (frozen cash)
- Bottom line, large: **"Every retailer loses in both directions at once."**

**Say (60s):** Retail has two opposite failure modes, and every merchant lives
in both simultaneously: shelves that run empty — that's revenue walking out the
door — and shelves that never empty — that's working capital frozen in boxes.
Industry analysts price this at 1.7 trillion dollars a year. But the number
that made *us* pick this problem is on the next slide.

---

## Slide 3 — Tracking is solved. Deciding is not.

**On slide:**
- **~85%** of operators run inventory primarily on **spreadsheets** (inFlow 2026, n=400)
- Platforms answer: *"how many do I have?"*
- Nobody answers: *"what should I order — how much, by when, and why?"*
- **Affected: SME retailers** — too big to guess, too small for SAP IBP

**Say (60–75s):** Every platform — Shopify, Square — counts stock perfectly.
Yet 85% of operators still fall back to spreadsheets, because counting isn't
the job; *deciding* is. Enterprise planning suites solve the deciding — at
enterprise prices with dedicated demand planners. Small and mid-size merchants
are stuck in the gap. That's who we build for, and it's worth solving because
it's measured in cash: freed working capital and protected revenue, not
convenience.

---

# 02 · RESEARCH & VALIDATION (3–4 min)

## Slide 4 — How we validated (and what we haven't yet)

**On slide:**
- Market scan: trackers · SME stock apps · enterprise planning
- **2 years of a real retailer** — 1.07 M transactions (UCI Online Retail II)
- Forecasting evidence base: **M5 competition** findings
- Blind backtesting against our own baseline
- ⚠️ *Not yet: merchant interviews — pilots are step one (built the connector to make them cheap)*

**Say (60s):** Our validation was engine-first. We scanned the market, took two
full years of a real UK retailer's transactions — over a million rows — and
used the forecasting field's best public evidence, the M5 competition, to pick
our methods. The honest gap, and we'd rather say it than have you ask it: we
haven't interviewed merchants yet. We spent that time building a Shopify
connector so a pilot merchant can test on their own live data in minutes —
user validation is designed to be the cheapest possible next step, not an
afterthought.

---

## Slide 5 — The market gap

**On slide (spectrum / 2×2 visual):**
- Left: **Trackers** (Shopify admin, Square, inFlow) — *know your count*
- Middle: **SME stock apps** (Stocky, Inventory Planner) — *rules & thresholds*
- Right: **Enterprise planning** (SAP IBP, Blue Yonder) — *real science, enterprise price*
- Star on the empty slot: **decision intelligence at SME price + effort**

**Say (45s):** Position the three clusters, then the gap: enterprise-grade
*decisions* — forecast-driven, explained — at a price and onboarding effort an
SME will actually adopt. That empty slot is Shelfwise.

---

## Slide 6 — What the data proved (and disproved)

**On slide (three verdict rows):**
- ✅ Models beat the naive average: **+8.3% overall, +10.5% on steady sellers** (28-day blind holdout)
- ❌ *"Models always win"* — sparse items only match an average → we fall back honestly
- ❌ *"The LLM can do math"* — caught it mis-adding → **all arithmetic moved server-side**

**Say (75s):** Three findings. First, our forecasting beats the formula every
spreadsheet uses — validated on a 28-day blind holdout the models never saw.
Second, a disproof: on very sparse items, nothing beats a simple average — so
instead of pretending, we fall back and label it. Third, my favorite: we
stress-tested our AI assistant and caught it adding numbers wrong. The fix
wasn't a better prompt — we removed arithmetic from its job entirely; every
total is computed by the tested engine, the AI only reports it. Our validation
didn't just confirm the design — it *changed* it. (This is the "demonstrate
your thinking" moment — land it.)

---

# 03 · THE SOLUTION (3 min)

## Slide 7 — Shelfwise: the decision layer

**On slide (product flow, one line):**
- **Connect → Forecast → Decide → Ask**
- Per product, every night: one of four verdicts —
  **Reorder** (how much, by when) · **Overstock** (cash to free) · **Dead stock** · **Healthy**
- Every verdict ships **with its reasoning**
- MVP scope cut on purpose: no POs, single store, lead time defaulted (disclosed)

**Say (60s):** Shelfwise imports your sales history, forecasts every product
nightly, and turns forecasts into verdicts a merchant can act on — what to
order, how much, by when, and where cash is stuck. Every number shows its
reasoning, because a recommendation a merchant can't interrogate is a
recommendation they won't trust. And we cut scope deliberately — purchase
orders, multi-location, supplier data are v2 — the MVP is the *decision*,
end to end.

---

## Slide 8 — The user journey: minutes, not migrations

**On slide (4-step timeline):**
1. Paste two values from Shopify admin (~5 min)
2. History backfills → forecasts run (automatic)
3. One screen: **"Order these today — here's why"**
4. Ask the assistant anything about your stock

**Say (45s):** Onboarding is paste-two-values. The connector backfills up to
two years of order history, forecasts run, and the merchant lands on one screen
that already contains the week's ordering decisions. No data entry, no
spreadsheet import, no consultant. Time-to-first-value is under an hour.

---

## Slide 9 — Design decisions we'll defend

**On slide (4 rows, decision → why):**
- **Classical stats, not deep learning** → M5 evidence; runs nightly on a $5 VPS; explainable
- **AI narrates, never computes** → every number from the tested engine, with citations
- **Immutable movement ledger** → every figure on screen is auditable
- **Graceful degradation** → forecasting dies → honest fallback formula, labelled

**Say (60s):** Four decisions define the product. We use per-SKU statistical
models because the public evidence says they win at this scale — and they run
on a five-dollar server, no GPU, ever. The AI is an access layer: it can only
read from the same services as the screens and never does its own math. Every
quantity traces back to an append-only ledger. And if the forecasting service
dies, nothing breaks — recommendations fall back to an explainable formula and
say so. I'll prove that last one live in a minute.

---

# 04 · LIVE PROTOTYPE (4–5 min)

## Slide 10 — Demo (one slide, then the product)

**On slide:**
- **"Everything you're about to see is real."**
- 1.07 M real transactions · 2 years · real seasonality
- (Disclosed: costs & categories synthesized — dataset has none)
- ⟦REFRESH⟧ tonight's catalogue: **276 products · ~$943 k stock value**

**Say — demo choreography (4 min, rehearsed):**
1. **Dashboard (30s):** stock value, needs-reorder ⟦REFRESH: 51 / 39 urgent⟧,
   the reorder-alerts panel — point out dashboard and Recommendations always
   agree because both read the same engine.
2. **Recommendations (75s) — the money shot:** the four verdict cards; click
   Reorder → "order today" strip; open one product's reasoning; then Overstock
   → "cash to free" ⟦REFRESH: ~$25 k⟧.
3. **Product detail (30s):** history + dashed forecast + worst-case band —
   "this is what the merchant never has: the future on the same chart as the past."
4. **AI assistant (75s):** ask live: *"What should I reorder first and why?"*
   then *"How much cash is stuck in overstock?"* — show the **source chips** on
   the answer. Then the trap, proudly: *"Order 50 units for me"* → it refuses:
   read-only by construction.
5. **Optional kill shot (30s, if time):** stop the sidecar, refresh
   Recommendations → everything still works, labelled *fallback*. "Failure was
   a design requirement."
- **Backup:** if wifi/LLM dies, chat degrades to a friendly error and every
  screen stays correct — the demo continues without the assistant.

---

## Slide 11 — How we know it's right

**On slide (verification pyramid):**
- **210+ automated tests** across 3 apps (51 on the assistant alone)
- **Blind backtest:** +8.3% vs baseline (chart from `forecast:evaluate`)
- **Golden harness:** ~12 judge-style questions asked to the *live* AI every
  demo morning, graded against the database — ⟦REFRESH: last run 9 ✅ / 0 ❌⟧
- We don't claim "always right" — we claim **grounded, cited, and it says "I don't know"**

**Say (60s):** The question every AI demo deserves: how do you know it's not
making things up? Three layers — unit tests on the machinery; a blind backtest
for the forecasts; and a golden-question harness that computes ground truth
from the database, asks the live model the same questions a judge would, and
grades the answers. We ran it this morning. And when our stress tests *did*
find two wrong answers, the citations traced them to tool bugs — not
hallucinations — fixed the same day. Next improvements: streaming responses,
per-conversation retention controls, supplier-aware recommendations.

---

# 05 · BUSINESS VALUE & NEXT STEPS (2–3 min)

## Slide 12 — The value, in currency

**On slide (three columns):**
- **Free cash:** ⟦REFRESH: ~$25 k⟧ overstock identified in one catalogue
- **Protect revenue:** ⟦REFRESH: 51⟧ products flagged before stockout
- **Time:** ordering decisions in seconds, not spreadsheet hours
- Price: **$49–99 / month** · AI cost per merchant: **< $2 typical, $8–15 worst case** → >90% gross margin

**Say (45s):** The business case is the merchant's own balance sheet: if the
engine frees even five percent of locked inventory cash, it pays for itself for
years. At $49–99 a month, serving costs are cents of infrastructure and at most
a few dollars of bounded AI usage — margins stay above ninety percent.

---

## Slide 13 — Next 3–6 months

**On slide (timeline, 3 blocks):**
- **M1–2:** merchant pilots (the validation we owe) · supplier lead times & MOQs · AI usage metering
- **M3–4:** purchase-order export · multi-location · GDPR productionization (paid LLM tier + DPA + retention)
- **M5–6:** multi-tenant SaaS · streaming chat · self-hosted LLM option

**Say (45s):** With another three to six months: first, pilots — five to ten
Shopify merchants, success measured in cash freed within sixty days. Then the
features that turn advice into action — real lead times, purchase orders. Then
scale: multi-tenant hardening, and for privacy-sensitive customers, a
self-hosted model so data never leaves their infrastructure.

---

## Slide 14 — Close

**On slide (one line, huge):**
- **"Tracking is solved. We built the deciding."**
- shelfwise · [contact]

**Say (30s):** Merchants already know what they have. Shelfwise tells them what
to do about it — grounded, explained, and verified against reality every single
morning. Thank you — happy to take the hard questions.

---

## Appendix slides (build but keep hidden — pull up only if asked)

- **A1 — Architecture diagram:** Laravel modular monolith · React SPA · stateless
  Python sidecar · immutable ledger (from `Apps/README.md`).
- **A2 — Backtest table:** `forecast:evaluate` output, WAPE per demand class.
- **A3 — GDPR & data protection:** roles (controller/processor/sub-processor),
  no consumer PII stored, pseudonymous AI payloads (verified), demo=free tier on
  public data / production=paid tier + DPA, self-host endgame.
- **A4 — AI cost & abuse bounds:** per-question cost, rate limits, caps, GCP
  budget ceiling.
- **A5 — Honest gaps:** no interviews yet · lead time defaulted · sparse-item
  limits · single store · chat retention roadmap.
