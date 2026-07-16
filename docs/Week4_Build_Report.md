# Internship — Week 4 Build Report

## Retail Inventory Intelligence

**From "read the dashboard" to "ask the dashboard" — and prove every answer**

| | |
|---|---|
| **Prepared by** | Abdulla |
| **Date** | 15/7/2026 |
| **Week 4 role** | AI Engineer · QA · Entrepreneur (AI-assisted development) |
| **Challenge** | Retail Inventory Intelligence — Retail · Commerce |
| **Business impact** | Decision Speed · Trust in AI · Pitch Readiness |

---

## 1. Recap — where Week 3 left off

Week 3 closed with the product proven against the outside world: a Shopify
connector live-verified against a real store, five live-only defects fixed, and
the AI assistant *"in implementation, expected to finish next week."* Week 4
kept that promise — and then spent most of its energy on the harder half of the
promise: not building an assistant, but **proving its answers are correct**.
The week's arc: the assistant delivered end to end (§2), deliberately attacked
with judge-style questions until it broke (§3), every failure converted into a
guardrail, and finally instrumented with an automated evaluation harness that
re-certifies it against the database before every demo (§4). Around the
assistant: the dashboard was rewired so every surface of the product quotes the
same intelligence (§5), the decision screen took a third UX pass (§6), and the
pitch collateral was written (§8).

---

## 2. The AI assistant, delivered

A new `Chatbot` module in the modular monolith (same layering as every other
module) plus a chat panel in the SPA — a floating button that opens a drawer
anywhere in the app, with suggested prompts, conversation history, and
**source citations on every answer**.

The design principle promised in Week 3's report survived contact with
implementation: **the assistant wraps the existing, tested service layer — it
does not invent new data paths.** Concretely:

- **Eight fixed read-only tools**, each a thin wrapper over a service the
  screens already use — store overview, recommendations, product search,
  per-product verdicts and forecasts, top sellers, sales trends, recent
  movements. There is no write tool to call: the assistant is read-only *by
  construction*, not by instruction.
- **The model narrates; the engine computes.** Every figure in an answer comes
  back from a tool; aggregates (totals, rankings) are computed server-side and
  handed to the model pre-calculated (§3 explains why that rule exists).
- **Grounded and auditable.** Each assistant message stores which tools it
  consulted; the UI renders them as citation chips, so any answer can be traced
  to the same data the screens render — the assistant cannot contradict the UI.
- **Bounded.** Authentication required, 30 messages/user/hour, capped message
  size, history window, tool iterations and output tokens. Tool errors are fed
  back to the model as data (it apologises and retries differently) rather than
  crashing the chat; provider failure degrades to a clear error while the rest
  of the product keeps working — graceful degradation, as everywhere.
- **Provider-agnostic.** The LLM (currently Gemini, free tier for development)
  sits behind a single interface; the model name is an environment variable.
  Swapping providers — or self-hosting for privacy-sensitive customers — is a
  config change, re-certified in minutes by the harness in §4.
- **Privacy by data minimization.** Tool payloads carry product and inventory
  figures only — no shop identity, no account data, and the platform holds no
  consumer PII at all. Demo development runs exclusively on the public dataset.

---

## 3. Breaking it on purpose — five defects adversarial use surfaced

The assistant shipped with a dedicated automated suite (51 tests — provider
quirks, tool validation, orchestration, failure paths). All green. Then it was
interrogated the way a committee would — 14 judge-style questions checked
digit-by-digit against direct database queries, plus ordinary daily use — and
reality disagreed five times:

| # | Symptom in live use | Root cause | Fix |
|---|---|---|---|
| 1 | Multi-product questions returned an "I gathered data but could not compose an answer" fallback | The model's hidden reasoning consumed the output-token budget, and the tool budget was too small for discovery + per-product forecasts | Larger output/tool budgets; prompt rule to batch same-tool calls; richer recommendation rows so fewer calls are needed |
| 2 | *"Top 5 sellers last 7 days"* answered confidently — and wrongly (a 174-unit product ranked above a 4,046-unit one) | No ranking tool existed, so the model *improvised* by summing a 20-row recent-movements sample | A dedicated `get_top_products` tool (SQL does the ranking); the movements tool now warns it must never be used for ranking |
| 3 | *"How much cash is in overstock?"* drifted \$852 from the true total | The model added 16 row values itself — LLMs are unreliable calculators | The tool now returns a server-computed total; the prompt instructs the model to quote, not add |
| 4 | *"What's most urgent?"* returned the **least** urgent product | An inverted sort comparator in the recommendations tool | Comparator rewritten (urgent first, shortest cover first) and pinned by tests |
| 5 | *"rabit nite light"* (typos) → "product not found" | Exact substring search has no fuzz | Per-word fallback search + a prompt rule to retry once with a cleaned-up query |

Two lessons, stated for the record. First — the Week 3 lesson generalizes:
**contract tests prove the machinery; only adversarial live use proves the
product.** Second, and more important for the pitch: **all five defects were in
our tool layer or configuration — none were hallucinations.** The citations
made each wrong answer traceable in minutes, every fix became a permanent
guardrail, and the final stress-test score was 12 of 14 answers exact on first
ask, 14 of 14 after the fixes.

---

## 4. Proving answers continuously — the golden-question harness

Fixing five defects raises the obvious question: how do we know tomorrow's
data doesn't surface a sixth? Week 4's answer is `php artisan
chatbot:evaluate` — a **differential-testing harness** that runs before every
demo:

1. For each of ~12 judge-style golden questions (*"What is my inventory
   worth?"*, *"What needs reordering?"*, *"What did product X sell?"*, a
   hallucination trap, a write-request trap…), the harness first computes the
   **ground truth directly from the database** through the same tested services.
2. It then asks the **live assistant** the same question — real model, real
   tools — and grades the prose answer against the computed truth with
   tolerance-aware matching.
3. Questions whose precondition doesn't hold (no fresh forecast, not enough
   recent sales) are **skipped with a stated reason** rather than faked.

First live run: **9 passed · 0 failed · 3 skipped** — and the skips were the
harness working as designed: they flagged that the demo dataset had gone stale
(history ends at import time), which is precisely the check that protects demo
morning. The same command doubles as an A/B instrument: point it at a different
model via one environment variable and compare scorecards before paying for a
bigger brain.

---

## 5. One truth on every screen — the dashboard rewire

Live testing exposed a consistency defect worth its own section: the
dashboard's "low stock" alert was driven by a **manual per-product reorder
level** — a field that defaults to zero for every Shopify-synced product,
silently killing the alert for exactly the merchants the product targets. Worse,
it created two parallel truths: the dashboard could say "2 low stock" while the
Recommendations engine said "51 need reorder".

The fix: the dashboard alert is now **driven by the intelligence engine** —
same verdicts, same counts, same top-urgent items as the Recommendations page
and the AI assistant. The manual field survives only as an optional per-product
minimum (relabelled "Min stock level"), useful for brand-new products with no
sales history — the one case demand models cannot cover. Dashboard,
Recommendations, and assistant now agree to the digit, verified live.

---

## 6. UX iteration — the decision screen, third pass

Week 3's redesign made the Recommendations screen readable; Week 4's pass made
it **actionable**, again driven by live review:

- **"This week's order plan"** — the missing headline insight: one strip
  totalling the reorder view (*N products · units · estimated cost · projected
  30-day revenue protected*), turning a table of advice into a purchase
  decision.
- **Export CSV** — the order plan downloads as a spreadsheet-ready file
  (severity-ordered, costs included), meeting merchants inside the tool 85% of
  them actually use.
- **Most-urgent-first, actually** — the "Order today" strip was showing the
  first five urgent products *alphabetically*; it now leads with the soonest
  stockout.
- **The fallback state announces itself** — if forecasts are missing or stale,
  a banner explains what degraded and offers a one-click **Refresh forecasts**
  (previously: two silently dash-filled columns and a subtle header note).
- **Pagination** — the table renders 25 rows per page (the analysis still
  computes over everything; summaries and exports ignore paging), keeping the
  DOM bounded for larger catalogues.
- Polish: extreme trend chips capped at ±500%, chart-tooltip contrast fixed,
  dashboard panels aligned to equal lengths.

---

## 7. Quality status

| Suite | Tests | Status |
|---|---|---|
| Backend (PHPUnit) | **165** (605 assertions) — up from 112; 51 dedicated to the assistant | ✅ green |
| Frontend (Vitest) | **47** — up from 39 | ✅ green |
| Forecast sidecar (pytest) | **11** | ✅ green |

Beyond the suites: the golden harness (§4) adds a live end-to-end layer no unit
test can provide. One diagnosis worth recording: the app's perceived slowness on
the demo machine was root-caused to development configuration — a debugger
extension loaded into every request, the opcode cache disabled, and the
single-threaded dev server serializing parallel requests. Production hosting
(PHP-FPM) removes all three; no application code was at fault.

---

## 8. Pitch readiness

Week 4 also produced the pitch's paper trail, aligned to the committee's
five-section format:

- **A full judge-defense Q&A** (~40 questions with rehearsed answers): problem
  evidence, validation honesty, architecture trade-offs, AI verification, GDPR
  and third-party-AI data security, cost control, curveballs (units of measure,
  model deprecation, "why not SAP IBP"), and an owned-gaps list.
- **A slide-by-slide deck draft** (14 slides + 5 hidden appendix slides) with
  speaker notes, demo choreography, and markers for every number that must be
  re-verified on pitch morning.
- **Sizing & operations documentation**: no-GPU server requirements,
  multi-tenant scaling outlook, AI cost bounds per merchant.
- **A deterministic demo-morning ritual**: re-import → refresh forecasts →
  run the golden harness → requote the day's numbers.

---

## 9. Known gaps & Week 5 outlook

**Honest gaps, carried forward**

- **User validation remains the biggest open item** — the connector makes
  merchant pilots cheap, but none have run yet; stated on our own slide.
- The assistant runs on the **free LLM tier for development** (public dataset
  only); production requires the paid tier (no training on prompts, DPA) — a
  scripted pitch answer, not a surprise.
- **Chat retention/erasure endpoints** are roadmap (account deletion already
  cascades); per-merchant AI usage metering is a small planned addition.
- Single store, single location, defaulted lead times, sparse-item forecast
  parity — all disclosed since Weeks 2–3.

**Week 5 — the pitch**

1. **Build the slides** from the deck draft; screenshot the live product.
2. **Rehearse the defense Q&A** — especially the five hardest questions
   (interviews, moat, AI trust, GDPR, "why not deep learning").
3. **Run the ritual, then present** — fresh data, fresh forecasts, a green
   harness scorecard, and a demo that can survive the wifi dying.

---

*Repository: `Apps/Backend` (Laravel API), `Apps/Frontend` (React SPA),
`Apps/Forecast` (Python forecasting service). Demo: import the dataset, start
the sidecar, sign in as `demo@retail.test` — or connect a Shopify store from
Settings → Integrations. Ask the assistant: "What should I reorder this week?"*
