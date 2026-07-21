# Pitch defense — judge Q&A, full walkthrough

> **How to use this:** one Q&A per likely committee question, ordered by the five
> pitch sections. 🔥 marks the questions most likely to be asked aggressively —
> rehearse those answers out loud. Companion doc: `Pitch-Judge-QA.md` (compact
> bullets for quick glancing during the pitch itself).
>
> **Numbers below verified 15/7/2026** and the demo data shifts daily. The
> morning of the pitch: re-run `inventory:import-retail --fresh` +
> `forecast:run`, then `chatbot:evaluate` (should read ~12/12 asked) and
> `inventory:insights`, and refresh every number you plan to quote.

---

## 01 · The Problem

*What judges test here: is this a real, costed problem — or a solution looking
for a problem?*

**🔥 Q: How do you know this is a real problem and not something you assumed?**
- Two independent signals. Macro: IHL Group prices global inventory distortion
  (out-of-stocks + overstocks) at **~$1.7–1.77 trillion per year** — roughly
  $1.2T lost to stockouts and $550B locked in overstock.
- Micro: a 2026 survey of 400 operations professionals (inFlow) found **~85%
  still run inventory on spreadsheets** — and manual systems are their single
  most-complained-about pain point. Tracking is solved; *deciding* is not.
- And our own demo data proves it empirically: run the engine on 2 years of a
  real UK retailer's transactions and it finds dozens of products needing
  reorder alongside tens of thousands of dollars locked in overstock —
  in the same catalogue, at the same time.

**Q: Who exactly is affected?**
- SME retailers and e-commerce merchants: big enough to feel stockouts and
  overstock in cash terms, too small to run enterprise planning suites
  (SAP IBP, Blue Yonder). They live in the gap between "Shopify tells me my
  count" and "an ERP tells me what to do".

**Q: Why is it worth solving?**
- It's a *working capital* problem, not a bookkeeping problem. Every overstocked
  SKU is frozen cash; every stockout is lost revenue plus a customer who tried a
  competitor. For an SME, freeing even a few thousand dollars of dead stock is
  material.

**Q: Why did you choose this problem?**
- It's quantifiable (we can measure our own impact in currency), it's underserved
  at the SME price point, and it's a genuinely good fit for AI that must *earn
  trust* — every recommendation can be checked against reality, which shaped our
  whole verification approach.

**Q: Inventory optimization is decades old — what's actually new here?**
- The math isn't new; the *access* is. Reorder-point theory has lived in
  enterprise tools and textbooks. What's new: per-SKU statistical forecasting +
  explainable recommendations + a natural-language interface, at a price and
  onboarding effort (minutes, via Shopify) an SME can actually adopt.

---

## 02 · Research & Validation

*What judges test here: did you check your beliefs against reality — and will
you admit what you couldn't check?*

**🔥 Q: Did you interview any actual retailers?**
- Honest answer: **no — that's the biggest open gap**, and we say so on our own
  slide before you ask. Within the build window we prioritized making user
  validation *possible and cheap*: the Shopify connector exists precisely so a
  pilot merchant can test on their own live data in minutes, with zero data
  entry. That's the very next step, not an afterthought.
- What we validated instead was the *engine*: real transaction data, blind
  backtests, and a measurable accuracy edge — so when we do sit with merchants,
  we're testing the product, not the math.

**Q: What research did you do, concretely?**
- Market scan of the tooling landscape (below); analysis of a real 2-year retail
  dataset (1.07M transactions, UCI Online Retail II, CC BY 4.0); a forecasting
  literature check (M5 competition results guided the model choice); and
  quantitative self-validation via holdout backtesting.

**Q: Competitor / market analysis?**
- **Trackers** (Shopify admin, Square, inFlow, Sortly): tell you what you have.
- **SME stock apps** (Stocky, Inventory Planner, Cogsy): closest neighbours;
  mostly rule/threshold-based, forecast depth varies, no conversational layer.
- **Enterprise planning** (SAP IBP, Blue Yonder, o9): the real science, at a
  price and complexity SMEs cannot touch.
- Our slot: enterprise-style *decision* intelligence, SME-style price and
  onboarding, with explanations a non-analyst trusts.

**🔥 Q: Which of your assumptions were validated — and which were disproved?**
- **Validated:** statistical models beat the naive 14-day average — **+8.3%
  overall, +10.5% on steady sellers** (~89% of volume) on a 28-day blind
  holdout. Also validated: real data breaks naive importers — our first import
  surfaced 5 real bugs (negative-stock chains, duplicate SKUs…) that synthetic
  demo data would never have caught.
- **Disproved:** "models always win." On very sparse items they only match a
  simple average — intermittent demand is irreducibly hard. We kept the honest
  fallback and we disclose it.
- **Disproved:** "the LLM can do the math." Early stress tests caught the
  assistant mis-adding rows — so we moved every aggregate server-side and the
  model now only *reports* computed numbers. (Great war story — tell it.)

**Q: Your dataset is a 2009–2011 UK gift wholesaler — how is that representative?**
- It's not meant to represent every retailer; it's meant to be *real*: real
  seasonality, real product churn, real intermittent demand — the failure modes
  synthetic data never has. Demand-pattern classification (smooth / intermittent
  / lumpy) transfers across retail verticals even when the products don't.
- And the Shopify connector means any pilot runs on the merchant's *own* data —
  representativeness solves itself at onboarding.

**Q: How big is the market?**
- We deliberately don't quote a made-up TAM. Directionally: millions of SME
  merchants on platforms like Shopify alone, sitting inside that $1.7T
  distortion figure. At a $49–99/month price point, a four-digit customer count
  is a real business — the constraint is distribution, not market size.

---

## 03 · The Solution

*What judges test here: did you make deliberate decisions, or just build what
was fun?*

**Q: What exactly is the MVP?**
- Import real sales history (Shopify or dataset) → nightly per-SKU forecasts →
  four verdicts per product (reorder / overstock / dead stock / healthy) with
  suggested quantity, order-by date and shown reasoning → dashboards + an AI
  assistant that answers questions over the same engine. Deliberately excluded:
  purchase orders, suppliers, multi-location, multi-user roles.

**Q: Walk me through the user journey.**
- Connect store (paste 2 values, minutes) → history backfills → forecasts run →
  the merchant lands on one screen that says *"order these today, this much,
  here's why"* → asks the assistant follow-ups in plain language → acts in
  their existing purchasing process. Time-to-first-value: under an hour.

**Q: Your screens show "days of cover" — so the decision is just stock ÷ average demand?**
- No — that's the *display* summary. The decision walks the model's
  **day-by-day curve**: the reorder trigger fires when the projected stockout
  day lands inside the 7-day lead + 3-day safety window, and the order-by date
  is that stockout day minus the lead time.
- Live example from the demo data (as of 18/7 — reverify after refresh):
  *Vintage Union Jack Bunting* shows **12 days of average-rate cover** — a flat
  rule calls it healthy — but its demand is front-loaded and the curve runs the
  57 units out around day 10, inside the reorder window. A flat average would
  have missed the order window; the curve catches it (3 such products in the
  current dataset, and the row's reasoning explains itself: *"the daily curve
  projects a stockout around …"*).
- The same math in reverse prevents over-ordering: back-loaded demand doesn't
  fire the trigger early. Both directions are pinned by unit tests.

**🔥 Q: Why classical statistical models and not deep learning / an LLM forecasting?**
- Evidence: the M5 forecasting competition showed per-series statistical methods
  remain competitive at product level; DL earns its cost on huge hierarchies,
  not 250-SKU catalogues.
- Economics: our whole model suite retrains nightly on a 2-vCPU VPS — no GPU,
  ever. Explainability: "AutoETS with weekly seasonality" can be shown to a
  merchant; a transformer cannot.
- The LLM never forecasts and never does arithmetic — it *narrates* numbers the
  tested engine computed. That division of labour is the design decision we're
  proudest of.

**Q: Why add an AI chatbot at all — why not just rely on the existing screens? Isn't it a gimmick?**
- It's the access layer, not the intelligence. Merchants don't think in filters
  and columns; they think "what should I order this week?" and "how much cash is
  stuck?". The assistant answers those over the exact same service layer as the
  screens — same numbers, with citations. If it were a gimmick we couldn't
  verify it; we verify it nightly against the database (see §04).
- The screens answer the questions we *predicted* and built a view for. The
  assistant answers the **long tail we didn't predict** — "compare my two
  best sellers", "what changed since last week in kitchenware?" — questions
  that would each otherwise cost a new screen, a new filter, or an export to
  Excel. One conversational layer covers them all at zero new data-risk,
  because it can only read what the screens already read.

**Q: Why standalone instead of a Shopify app?**
- Shopify is one *connector*, not the platform bet: the engine also serves
  merchants on Square/WooCommerce/CSV. Embedding as a Shopify app is a
  distribution decision we'd take later — the architecture (API + SPA) makes the
  UI portable.

**Q: Architecture — and why?**
- Laravel **modular monolith** (one deployable, module-per-capability with
  enforced boundaries — swap-ready without microservice overhead), React SPA,
  and a **stateless Python sidecar** for the models (right tool for the
  ecosystem: Nixtla statsforecast). The sidecar owns no data — Laravel sends
  series, gets forecasts back — so it can die without data loss and scale
  horizontally.
- Single source of truth: an **immutable stock-movement ledger**; every quantity
  on screen is derivable from it. 210+ automated tests across the three apps.

**Q: Why can't the user filter by custom date ranges — e.g. sales of a product from day X to day Y?**
- Deliberate decision-first scope. The fixed windows (7/30/90 days) answer the
  *recurring decision* questions — is demand rising, what sold this month —
  and the models already consume the **full two years of history** on their
  own; the merchant never has to pick a range for the intelligence to work.
  A free-form date-range explorer is a *reporting* interaction — closer to the
  spreadsheet workflow we're replacing than to the decision we're selling.
- Nothing in the system blocks it: the ledger is day-granular, and the trends
  API already accepts **any** window size — the three preset buttons are a UI
  choice, not a data limit. From/to pickers are a small query change, slated
  with the v2 reporting features (alongside CSV/PO exports).
- Partial coverage exists today: the assistant can answer range-flavoured
  questions from the daily series, and per-product movement history is fully
  browsable.

**Q: Can a merchant ask expected sales for a custom future window — "next 4 days", or "the second week of next month"?**
- Short windows: **yes, today, in plain language** — the assistant pulls the
  product's day-by-day forecast and answers "expected to sell in the next 4
  days" directly (this exact question was part of our live testing). The
  product page also charts the entire daily forecast curve with its
  uncertainty band, so any future window is *visible*.
- The screens headline the **decision horizons** on purpose — 7 days (lead
  time), 14-day coverage, 28/30-day projections — because those are the
  windows the reorder math actually consumes.
- A precise number for an arbitrary far-out week isn't served yet: the
  assistant's tool condenses the forecast (next-7 daily + 28-day total) to
  keep the AI payload small — a token-budget choice, **not a data limit**;
  forecasts are stored as a full daily series per product. Accepting a
  from/to window with a server-computed sum is a one-parameter tool change,
  slated for v2.

**Q: What trade-offs did you consciously make?**
- Supplier lead time defaulted to 7 days (no per-supplier data yet) — disclosed
  on every recommendation. Single store, single location. No purchasing module —
  we advise, the merchant executes. Chat responses are non-streaming (kept the
  demo infra simple). Free-tier LLM for the demo, paid tier for production.
  Each of these is a v2 line item, not an accident.

---

## 04 · Live Prototype

*What judges test here: is it real, is it robust, and do you know where the
bodies are buried?*

**🔥 Q: How did you verify the chatbot answers correctly?**
- Three layers. **(1) Machinery:** 51 automated tests on the assistant alone —
  provider quirks, tool validation, orchestration, failure paths.
  **(2) Differential testing:** a golden-question harness
  (`chatbot:evaluate`) computes ground truth *from the database at runtime*,
  asks the live model the same ~12 judge-style questions, and
  tolerance-matches the prose answer against the computed truth. Plus a manual
  14-question stress test — 12/14 digit-exact on first ask.
- **(3) Grounding by architecture:** the model can only state numbers returned
  by 8 fixed read-only tools that wrap the same tested services as the screens;
  every answer stores and displays its tool citations.
- The 2 stress-test misses were **our tool bugs, not hallucinations** — found
  *because* of citations, fixed same day, each fix now a guardrail. We don't
  claim "always right"; we claim grounded, auditable, and it says "I don't
  know" rather than guessing.

**Q: Can it hallucinate a number or corrupt my data?**
- It has no database access and no write tool exists to call — read-only by
  construction, not by policy. Tested traps: fictional product → "not found";
  "place an order" → refuses and explains it can only advise; off-topic →
  declines.

**🔥 Q: What about prompt injection?**
- Worst case is bounded: the tools can only *read* what that authenticated user
  already sees on screen, and there is nothing to write, delete or email. An
  injected prompt could at most produce a wrong sentence — mitigated by
  citations and the golden harness. No cross-tenant data exists in the demo
  scope; tenant scoping is a stated precondition for multi-tenant production.

**🔥 Q: How is merchant data kept secure when it's sent to a third-party AI? Is that GDPR-compliant?**
- **Roles:** the merchant is the data controller; Shelfwise the processor;
  Google the sub-processor for LLM inference only.
- **Mechanics:** the browser never talks to Google — all LLM calls are
  server-to-server over TLS, the API key lives only in server config (never
  shipped to the client), and the merchant's IP is never exposed to the
  provider.
- **Data minimization (verified in code):** chatbot payloads carry product and
  inventory figures only — no shop name, no owner identity, no account data, no
  end-customer data. The platform itself stores **no consumer PII at all** —
  the Shopify import takes products, prices and quantities, never customer
  records.
- **Demo vs production:** the free Gemini tier may use inputs to improve
  Google's products (incl. human review) — that's why the demo runs *only* on a
  public CC-BY dataset. Production uses the **paid tier, where Google
  contractually does not train on prompts**; the EEA/UK effectively requires
  the paid tier anyway. We'd execute Google Cloud's standard Data Processing
  Addendum and name Google as sub-processor in our own DPA with merchants; EU
  data-residency endpoints exist on the paid platform.
- **User rights & retention:** chat history lives in *our* database and is
  erased with the account (cascade delete). Per-conversation delete/export
  endpoints and a chat retention window (auto-purge after N days) are named
  roadmap items — we say that honestly rather than pretending.
- **No Art. 22 issue:** the assistant only advises; a human makes every order
  decision.
- **Endgame:** the LLM sits behind a single interface — swappable for a
  self-hosted model so data never leaves the customer's infrastructure at all.

**Q: What does the AI cost, and can users abuse it?**
- Paid tier ≈ **half a cent per question**; a heavy merchant is $8–15/month
  *worst case*, typically under $2 — priced into the plan or gated premium.
- Abuse is bounded end-to-end: auth required → 30 messages/hour/user → capped
  message size, history window, tool rounds and output tokens. The hard
  financial ceiling is platform-side: a GCP budget cap + quota ceiling means we
  cannot be billed past a number we set. A per-merchant monthly meter is a
  small addition (token columns on the existing message table).

**Q: Is the demo data real?**
- Sales and prices: real (1.07M transactions, 2 years, UK retailer — UCI Online
  Retail II, CC BY 4.0). Costs, categories and the purchasing side are
  synthesized because the dataset has none — disclosed on the slide. The
  Shopify flow is live-verified against a real store.

**Q: What happens if the forecasting service dies right now?**
- Kill it — seriously, it's demo-able. Every recommendation silently falls back
  to an explainable moving-average formula, labelled as fallback. No error, no
  blank screen. Graceful degradation was a hard requirement from day one.

**Q: Is that model (Gemini Flash-Lite) production-grade? Why not a bigger one?**
- We benchmarked the need, not the badge: the assistant's job is tool selection
  + reading structured data + short summaries — our stress test passed 12/14
  first-ask on Flash-Lite, and both misses were tool bugs. The premium model
  costs ~6× for no measured gain (and was *less* available in our testing).
- The model is one env var; the harness re-runs the same golden set against any
  candidate. That's how we'd decide an upgrade: evidence, not vibes.

**Q: The app felt slow for a moment — why?**
- Dev-machine configuration, not architecture: the demo laptop runs a debugger
  extension and no opcode cache, and the dev server is single-threaded. Any
  production PHP-FPM host removes all three. The heavy work (forecasting) is a
  nightly batch users never wait on.

**Q: What would you improve next in the product itself?**
- Real supplier lead times + purchase-order export (turn advice into a
  one-click action), per-conversation privacy controls, streaming chat UX, and
  pilot-merchant feedback loops on the recommendation thresholds.

---

## 05 · Business Value & Next Steps

*What judges test here: does this deserve to exist commercially, and do you
know what you don't know?*

**Q: What's the business model?**
- SaaS subscription, $49–99/month per store (SME willingness-to-pay territory),
  AI assistant included in upper tiers or metered. Infra cost per merchant is
  cents; the AI worst case is dollars — gross margin stays >90% at list price.

**Q: What's the measurable impact for a merchant?**
- On the demo catalogue alone: tens of thousands of dollars in overstock cash
  identified, ~50 products flagged before stockout, dead stock with recoverable
  value listed. The honest framing: *if the engine frees even 5% of a typical
  SME's locked inventory cash, it pays for itself for years.*

**🔥 Q: Why wouldn't Shopify just build this? / What's your moat?**
- Honestly: no deep technical moat at MVP stage — the moat candidates are
  **trust and workflow depth**: shown reasoning, verified accuracy, and
  eventually supplier/PO integration that platforms treat as an afterthought.
  Plus platform-independence: Shopify will never build the tool that also runs
  your Square location. If a platform ships a clone, that validates the market;
  we'd compete on decision quality and focus.

**Q: Who are the first customers?**
- Pilot cohort: 5–10 Shopify SMEs (the connector makes onboarding zero-effort),
  ideally 500–5,000 SKUs, where the pain is sharpest and the data is richest.
  Success metric per pilot: cash freed + stockouts avoided within 60 days.

**Q: Roadmap — what would you do with another 3–6 months?**
- **Month 1–2:** merchant pilots (the validation we owe); supplier lead times +
  MOQ/pack-size constraints; per-merchant AI usage metering and daily caps.
- **Month 3–4:** purchase-order generation/export; multi-location; GDPR
  productionization (paid tier, DPA, retention windows, EU residency).
- **Month 5–6:** multi-tenant SaaS hardening (tenant scoping is the real work —
  compute already scales); streaming chat; self-host LLM option for
  privacy-sensitive customers.

**Q: What does it cost to run at, say, 100 merchants?**
- Forecasting for 100 tenants × 500 SKUs ≈ 3–4 CPU-hours nightly — a couple of
  modest VPSs; the models are stateless and parallel. First real bottleneck is
  the database, not the models. AI: usage-based, bounded per merchant. No GPU
  at any scale we can foresee.

---

## Curveballs (asked from left field — often by the SAP judge)

**🔥 Q: SAP IBP does ML demand sensing — why would anyone use your classical models?**
- Different customer, same lesson. IBP is priced and staffed for enterprises;
  our users have no demand planner. And the M5 competition's uncomfortable
  finding was that well-tuned statistical baselines are hard to beat at SKU
  level — we chose the method that wins *at our scale and cost envelope*, and
  we can prove its edge on demand with a live backtest (`forecast:evaluate`).

**Q: Your safety stock is simplistic — where are service levels, MOQs, multi-echelon?**
- Correct, and disclosed. The buffer is quantile-based (p90 demand gap), which
  is a service-level *proxy*; formal service-level targets, MOQs and pack sizes
  are named v2 items. Multi-echelon is out of scope for single-location SMEs —
  by the time a customer needs it, they've outgrown our segment.

**Q: What if Google deprecates the model or 10×es the price?**
- The provider sits behind one interface (`LlmClientInterface`); the model name
  is an env var; the golden harness re-certifies any replacement in minutes —
  including a self-hosted open-weights model. We treat the LLM as a commodity
  part, deliberately.

**Q: Everything here is whole units — what about goods sold by weight or length (grams, cm, litres)?**
- Deliberate MVP scope: our beachhead is e-commerce SMEs, and that world sells
  *eaches* — Shopify itself tracks inventory as integer units (a coffee shop
  sells "250 g bag" as a discrete SKU, not loose grams). Weigh-and-price retail
  (butcher, fabric, bulk foods) is POS-centric — that segment needs a POS
  connector before units-of-measure even become the constraint.
- Architecturally it's a contained change, not a redesign: the decision math is
  **already continuous** — velocity is a float, the forecasting models output
  continuous quantities, and the intelligence layer never rounds intermediate
  values (rounding is display-only, by codified rule). Supporting kg/cm means a
  `unit_of_measure` field, switching the quantity columns from integer to
  decimal, and formatting/validation — the ledger, forecasts and reorder logic
  work identically.

**Q: Forecasts go stale after 48 hours — why?**
- A freshness contract: better to fall back to an honest moving average than to
  present a two-week-old forecast as current. Production runs the refresh
  nightly on a scheduler, so staleness never triggers; the rule exists for the
  failure case.

**Q: Why PHP/Laravel — is that a serious stack in 2026?**
- Boring-technology bet: mature ORM/auth/queue ecosystem, one deployable,
  cheap hosting, huge hiring pool — and the numerical work lives in Python
  where the science libraries are. The stack matches the team and the segment;
  novelty is spent where it pays (the intelligence), not the plumbing.

**Q: One-person team — can you actually deliver this?**
- The build so far *is* the evidence: three apps, 210+ tests, a live Shopify
  integration and a verified AI layer in four weeks. The discipline that made
  that possible (module boundaries, tests, honest scope cuts) is the same
  discipline that scales the team later.

---

## Round 2 — questions we were actually asked (with measured evidence, 20/7)

> Coaching note a judge gave us, worth keeping: **"delivery is 80% of selling."**
> The committee scores the *performance* — confidence, story, demo smoothness —
> more than the substance behind it. Rehearse the choreography and the Q&A out
> loud; this document is the script, not a reference to read from.

**Q: What billing / delivery model is planned? Self-hosting?**
- Planned model: **hosted SaaS, monthly subscription per store**, tiered by SKU
  count and connectors — our segment (SMB e-commerce) does not self-host.
  Pilots run free on our hosting; self-hosting stays *possible* (the whole
  product is one VM: Laravel+MySQL, the Python sidecar, a static SPA) for the
  odd customer with data-residency demands, but it is not the business.
- Unit economics are the point of the classical-models bet: COGS per store is
  hosting pennies plus rate-limited flash-tier LLM tokens for chat.

**Q: You said the models run on a $5 server — prove it.**
- Measured 20/7 on the demo dataset: a **full forecast refresh of 254 SKUs
  takes ~168 s end-to-end** (Laravel + HTTP + model fitting: 134 AutoETS,
  87 MSTL, 28 Croston, 4 SeasonalNaive, 1 TSB) with a **sidecar peak of
  79 MB RAM**, pure CPU, no GPU. That is one nightly batch job a 1 vCPU / 1 GB
  VPS (the $5/mo tier) runs with an order of magnitude of headroom — the
  marginal forecasting cost per store per night is a fraction of a cent.

**Q: Where does the test data come from?**
- **Real retail history**: UCI Online Retail II (CC BY 4.0) — two years of
  actual transactions from a UK online giftware retailer. The importer curates
  ~250 SKUs and streams ~100k ledger rows, date-shifted to end yesterday.
- Honest split, said out loud in the demo: **sales and returns are real; the
  supply side is simulated** (the dataset has no purchasing data, so the
  importer replays an (s,S) replenishment policy), and costs/categories are
  synthesized. Attribution is documented in the repo.
- Second data path: a live Shopify dev store, imported through the connector —
  proving the pipeline works on data we didn't curate.

**Q: Performance — switching pages takes up to 3+ seconds.**
- Diagnosed with measurements, and it is the **dev harness, not the data
  model**. Three compounding dev-only factors: (1) `php artisan serve` on
  Windows is a *single-worker* server, and the dashboard fires three API calls
  in parallel — measured: three requests that cost 0.6–1.2 s each serialize
  into a **4.0 s wall-time burst**; (2) **Xdebug is loaded** in the dev PHP —
  disabling it alone cut the heavy endpoints ~40% (dashboard summary 1.2–2.1 s
  → ~870 ms); (3) no opcache in that SAPI, so every request re-boots the
  framework from the Windows filesystem — even a trivial authenticated
  endpoint costs ~470 ms of pure overhead.
- The engine itself is O(products) with **three SQL queries total** (no N+1):
  the full 276-product analysis returns in ~835 ms *including* all the dev
  overhead above.
- Production posture: Linux, php-fpm/Octane workers, opcache → 10–50 ms
  framework overhead and true request parallelism; plus a response cache for
  the recommendations payload (it only changes when movements or forecasts
  change). Demo-morning mitigation: start the API with `XDEBUG_MODE=off`.

**Q: What is the AI's system prompt? How was it tested?**
- It's ~30 lines, versioned in code (`Chatbot/Support/SystemPrompt.php`) — we
  can put it on screen. It fixes a **strictly read-only persona**, bans
  invented numbers ("every figure must come from a tool result"), forces
  product-name resolution through `find_product`, sets a tool budget, and
  constrains the output format.
- Tested at three levels: (1) **`chatbot:evaluate`** — a golden-question
  harness that asks the *live* model through the real orchestrator and scores
  each answer against ground truth computed from the same services the tools
  wrap; run before every demo. (2) The regular test suite fakes the LLM and
  pins the orchestrator loop: tool-call sequences, failure payloads, the
  forced-text fallback. (3) The strongest guarantee doesn't rely on the prompt
  at all: **the tool registry only wraps read-only service interfaces**, so
  even a fully jailbroken prompt has no write path. Prompt = answer quality;
  architecture = safety.

**Q: How is the connector implemented? Standardized? Can third parties build one?**
- Today: **one first-party connector** (Shopify, GraphQL Admin API, custom-app
  token) — there is no connector SDK yet and we don't claim one.
- What is already generic, and what a second connector reuses wholesale: an
  idempotency map (external id ↔ product), a sync-state watermark for
  incremental pulls, first-run order backfill with computed opening balances, a
  closing inventory-reconcile pass, and a **single write path through the stock
  service** — connectors never touch tables directly. The dataset importer is
  the proof-of-pattern: effectively a "file connector" feeding the same ledger.
- Third parties integrate **today via the documented REST API** (products,
  stock adjustments — the same API the SPA uses, bearer-token auth, OpenAPI
  spec at `/docs`). A formal plug-in SDK is deliberately post-pilot: we'd
  rather ship WooCommerce/CSV ourselves first and extract the contract from
  two real implementations instead of designing it speculatively.

---

## Own these gaps before they're asked (say them on your own slide)

1. **No merchant interviews yet** — engine validated, users not; pilots are
   step one and the connector was built to make them cheap.
2. **Supplier lead times defaulted** (7 days) — disclosed on every
   recommendation.
3. **Sparse/lumpy items** forecast no better than an average — inherent to
   intermittent demand; we fall back honestly.
4. **Single store, single location, single user role** today.
5. **Chat retention/erasure endpoints** are roadmap (account deletion already
   cascades).
6. **Demo runs the free LLM tier** — public data only; production plan is the
   paid tier + DPA (see GDPR answer).

---

## Demo-day insurance (morning-of, ~25 min)

**Do NOT `inventory:import-retail --fresh`** — it wipes the staged catalogue
(hand-tuned verdict examples + a natural-looking activity feed) for 15 risky
minutes. The staged DB is the demo asset; only refresh what goes stale:

```bash
php artisan forecast:run                      # fresh forecasts (sidecar on :8100) — 48h staleness rule!
php artisan chatbot:evaluate                  # once only (spends LLM quota); expect 0 FAIL
php artisan inventory:insights                # refresh any number you quote
```

Then make it fast (measured 20/7 — see the performance answer in Round 2):

```powershell
php artisan config:cache; php artisan route:cache   # cut framework boot (undo: config:clear)
$env:XDEBUG_MODE='off'; php artisan serve            # Xdebug off ≈ 40% faster endpoints
```

> ⚠️ **While the config cache exists, NEVER run `php artisan test`** — the
> cache overrides phpunit's sqlite override and the suite runs against (and
> WIPES) the real MySQL database. This happened on 21/7 and cost a full
> re-import. The test suite now hard-refuses to run when it detects a
> non-sqlite connection, but the rule stands: `config:clear` before any tests,
> re-cache after.

- Add 3–5 small movements via the Stock screen so Recent activity shows today.
- Warm the pages: click Dashboard → Recommendations → a product once before
  presenting; the committee only sees warm loads.
- Backup plan if wifi/LLM dies: the whole product minus chat works offline;
  chat failure degrades to a friendly error, screens stay correct.
- Killing the sidecar live is a *feature demo*, not a risk — rehearse it.
- Numbers in this doc were true on 15/7/2026: 276 products, ~$943k stock value,
  51 reorder (39 urgent), 57 overstock, 6 dead stock, backtest +8.3%/+10.5%.
  **Requote after the morning refresh.**
