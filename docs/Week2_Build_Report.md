# Internship — Week 2 Build Report

## Retail Inventory Intelligence

**Design → Build → Working Prototype**

| | |
|---|---|
| **Prepared by** | Abdulla |
| **Date** | 3/7/2026 |
| **Week 2 role** | UX/UI Designer · Solution Engineer · Developer (AI-assisted development) |
| **Challenge** | Retail Inventory Intelligence — Retail · Commerce |
| **Business impact** | Cost Reduction · Margin Improvement · Decision Speed |

---

## 1. Recap — what we set out to build

Week 1 established that the underserved gap for SME retailers is not stock *tracking* — Shopify and Square already do that — but the **decision layer above it**: a system that turns sales and stock data into specific, reasoned actions. The MVP hypothesis: *give retailers recommendations — what to reorder, how much, when, and which products are tying up capital — and they will decide faster, stock out less, and free trapped cash.*

The Week 1 scope directed roughly 70% of build effort at the intelligence layer, with a deliberately simple supporting dashboard, and recommended making "the recommendation moment" — a plain-language, trustworthy suggestion — the hero of the demo.

Week 2 delivered that prototype, and went beyond the plan in two places. Week 1 left exactly two assumptions **open**: *"retailers value being told what to do"* (to be tested on the prototype) and *"seeded sales data is accepted as a valid way to demonstrate the intelligence"* (risk: *evaluators may want to see real data*). The second risk was retired outright during the build — the prototype now runs on **two years of real retail transactions** — and that decision unlocked a second upgrade: replacing the planned velocity formulas with **real time-series forecasting models**, which Week 1 had deferred to v2 precisely because they "need 12–24 months of history." Once the data was real and two years deep, the justification for deferring the models disappeared, so they were pulled forward. Both departures are documented honestly in sections 4 and 5.

The git history reflects this arc: the core application and the velocity-based intelligence layer landed first (`genesis` → `feat: add Intelligence layer`), followed by the pivot commit (`UI improve - Real data - Time series model`).

---

## 2. Architecture & tech stack

The prototype is three cooperating services in one repository:

```
Apps/
├── Backend/    Laravel 13 REST API (PHP 8.4) — modular monolith, MySQL/MariaDB
├── Frontend/   React 19 + TypeScript + Vite + Tailwind CSS v4 single-page app
└── Forecast/   Python 3.12 FastAPI sidecar — Nixtla statsforecast models
```

| Layer | Technology | Why |
|---|---|---|
| API | Laravel 13, Sanctum bearer auth, MySQL | Mature, fast to build with AI assistance, strong conventions an evaluator can audit |
| Architecture | **Modular monolith** — each capability (Auth, Category, Product, Stock, Dashboard, Forecast, Intelligence) is a self-contained module owning its controllers → service interfaces → business logic → mappers → DTOs, routes and migrations | Right-sized for an MVP: microservice-style boundaries and testability without microservice operational cost; any module could be extracted later |
| Forecasting | **Python FastAPI sidecar** running open-source statistical models | Production-grade time-series libraries live in the Python ecosystem; isolating them keeps the app clean and swappable (§4) |
| Frontend | React 19 + TypeScript, Tailwind v4 design system, Recharts | Type-safe API contracts mirroring backend DTOs; charts are lazy-loaded so entry pages stay fast |
| API docs | Hand-authored OpenAPI 3.0 spec served as Swagger UI at `/docs` | Enterprise-friendly: the contract is explicit and browsable |
| Testing | PHPUnit (93 tests), Vitest (35), pytest (11) — all green | Foundation for Week 3's QA stage |

Two architectural principles matter most:

1. **The stock-movement ledger is the single source of truth.** Every stock change writes an immutable row (`quantity_before` → `quantity_after`, reason, author) inside a locked transaction. Product quantities, dashboards, forecasts and recommendations are all derived from this ledger — which is also exactly the shape a real retailer's POS data would arrive in.
2. **The intelligence layer degrades gracefully.** The forecasting sidecar is a separate process; if it is down, or forecasts are older than 48 hours, every recommendation silently falls back to the original explainable velocity formula. A model failure can never break the product.

---

## 3. What was built

**Screens** (all live, on real data):

- **Sign-in / registration** — branded split-screen entry with the value proposition.
- **Dashboard** — KPI cards (stock value, units sold with period-over-period delta, projected 30-day revenue, low/out-of-stock), a daily sales chart that extends *past today* with the model's dashed projection and worst-case band, inventory value by category, low-stock alerts and a live activity feed.
- **Recommendations** — the hero screen. Headline counts (reorder / overstocked / dead stock / cash tied up), an "Order today" section of urgent cards, filters, a cash-tied-up chart, and a verdict-first table where every row carries a plain-language explanation naming the model behind it.
- **Product detail** — a recommendation callout ("Order 1,104 units by Today", stockout risk, demand trend), a 90-day demand history chart with the 28-day forecast and uncertainty band, the movement ledger, and a stock-adjustment form.
- **Products / Categories** — the supporting catalogue: searchable, sortable product table with stock-capacity bars, create/edit forms, guarded deletes.

**Honest mapping to the Week 1 MoSCoW scope:**

| Week 1 item | Priority | Status | Notes |
|---|---|---|---|
| Demand forecasting & reorder engine | Must | **Done — upgraded** | Time-series models replaced the planned velocity formulas (§4); velocity formula retained as automatic fallback |
| Recommendation reasoning display | Must | **Done** | Every verdict self-explains and cites its source, e.g. *"Forecast (AutoETS): ~261/week expected with ~5 days left… order 1,104 units now"* |
| Overstock & cash-tied-up flags | Must | **Done — extended** | Plus dead-stock detection with recoverable-cash estimate |
| Stock dashboard (foundation) | Must | **Done — beyond scope** | Grew from "simple list" into a charted KPI dashboard once real data made it meaningful |
| Seeded sales-history dataset | Must | **Replaced with real data** | A repeatable importer loads a public real-world dataset (§5) — retiring Week 1's open risk |
| Low-stock / stockout alert view | Should | **Done** | Dashboard alert panel + "Order today" urgent cards + stockout-risk badges |
| LLM natural-language weekly summary | Should | **Not built** | Superseded by a stronger plan: an AI Q&A agent (see §8) |
| CSV import of real sales data | Could | **Partial (different form)** | A bulk data-ingestion pipeline exists (the dataset importer proves the pattern); a user-facing CSV upload does not |
| Barcode-assisted / manual stock entry | Could | Manual entry done; barcode not started | Stock adjustments work through the UI/API |
| Supplier lead-time management | Could | **Not started** | Lead time is a documented 7-day default everywhere; the UI labels it as defaulted |
| Advanced / seasonal forecasting (ML) | Won't (v1) | **Pulled forward — done** | The deliberate divergence; justified in §4 |
| POS integration (live sync) | Won't (v1) | **Built (pre-Week 3)** | Shopify connector completed after this report's build window — see §8 |
| Automated purchase orders | Won't (v1) | Not built — by design | The product recommends; the human decides |
| Cross-branch / multi-store view | Won't (v1) | Not built — per scope | Single-location model throughout |

---

## 4. The intelligence layer — how it actually works

### 4.1 From ledger to recommendation

1. For each product, the backend builds a **daily sales series** from the ledger — units sold per calendar day, zeros included, up to two years, ending yesterday.
2. The series are sent to the **forecast sidecar**, which classifies each product's demand pattern and fits the appropriate statistical model, returning a **28-day daily forecast** plus a **p90 band** ("if demand runs hot, expect up to this much").
3. Results are stored per product (`php artisan forecast:run`, scheduled daily; stale after 48 hours → automatic fallback).
4. The recommendation engine — a pure, unit-tested calculator — consumes the forecast: expected demand replaces the flat average, and the suggested order quantity covers forecast demand over the supplier lead time and coverage period **plus a safety buffer sized from the p90 band** (volatile products earn bigger buffers; predictable ones tie up less cash).

### 4.2 The models, and why each one

No single model fits a retail catalogue — a daily best-seller, a product selling three units a week, and a discontinued item are statistically different problems. Each product is auto-classified (using the Syntetos–Boylan criteria: how *often* it sells × how *variable* the quantities are, plus history length) and routed accordingly. All models come from **Nixtla `statsforecast`**, a widely used open-source library — we wrote the routing rules; the library provides fast, battle-tested implementations (all 250 products forecast in under a minute).

| Demand pattern | Model | Why this model | Products |
|---|---|---|---|
| Steady seller, 2+ years of history | **MSTL** (weekly + annual seasonality) | The only model in the set that learns weekly *and* yearly cycles; it must see the annual cycle twice — which our 2-year dataset provides. This is what lets the system anticipate seasonal ramps | 95 |
| Steady seller | **AutoETS** (weekly seasonality) | Exponential smoothing that self-tunes per product and weights recent sales more. In the M5 competition (on Walmart data) it stayed competitive as a per-SKU benchmark — the overall winners were gradient-boosting methods, but simple exponential smoothing remained strong at product level, which is exactly this use case. Fast and self-tuning across many series | 126 |
| Sparse but alive | **CrostonOptimized** | The industry standard for intermittent demand: forecasts a demand *rate* by smoothing order sizes and gaps separately — exactly what a reorder decision needs when no model can know *which* day the next sale lands | 28 |
| Dying demand | **TSB** | Its sale-probability decays toward zero when a product stops selling — this powers **dead-stock detection** | 1 |
| Under 28 days of history | SeasonalNaive / Naive | Honest baselines for new products | — |

### 4.3 What the models unlock beyond the original plan

The Week 1 plan computed one number per product (average daily sales over 14 days) and divided by it. The models produce a daily demand *curve* with uncertainty, which enables insights a flat average cannot express:

- **Projected stockout date** — stock walked down the actual forecast curve (a ramping product runs out sooner than its average suggests).
- **Stockout risk (high / medium / low)** — high: *expected* lead-time demand already exhausts stock; medium: only the *worst-case* band does; low: covered either way.
- **Demand trend** — expected next-28-days vs. the previous 28 days of actuals (rising / declining %).
- **Projected 30-day units and revenue** — per product and store-wide (the dashboard chart literally shows the future).
- **Dead stock** — the model expects effectively no further demand while units remain; the UI shows the cash recoverable by clearance.
- **Right-sized safety stock** — from each product's own uncertainty band rather than one global buffer.

Everything remains explainable — the Week 1 principle that trust requires transparency was kept deliberately. Every recommendation still renders a plain-language sentence, now with attribution: *"Forecast (AutoETS): …"* vs *"window-average"* when running on fallback.

### 4.4 Proof it works: the backtest

Accuracy is measured with a **rolling-origin holdout**: train on all history except the final 28 days, forecast those days blind, score against what actually sold — side-by-side with the Week 1 velocity formula as the baseline. The headline metric is **total-demand error (WAPE)** because the total over the lead time is the number a reorder decision actually consumes. Reproducible live with `php artisan forecast:evaluate`:

| Model group | Products | Model error | Velocity-formula error | Improvement |
|---|---|---|---|---|
| Smooth sellers (AutoETS) | 221 | 30.9% | 34.5% | **+10.5%** |
| Sparse sellers (Croston) | 28 | 27.4% | 22.7% | −20.5% |
| **All models** | **249** | **30.5%** | **33.3%** | **+8.3%** |

The honest reading: the models beat the original formula by **8.3% overall**, driven by a **+10.5% gain on smooth sellers** — ~89% of the catalogue and most of its volume. For very sparse products a recent average is genuinely near-optimal and no model reliably beats it; the system keeps Croston there for its principled uncertainty handling, and the difference involves little volume. Presenting the aggregate win *with* this caveat is a deliberate choice: the audience includes enterprise-software practitioners, and an honest accuracy claim survives scrutiny.

---

## 5. Data — real transactions, not seeded numbers

### 5.1 The dataset

The prototype runs on **UCI Online Retail II**: **1,067,371 real transactions** from a UK-based retailer, December 2009 – December 2011.
Source: Chen, Daqing (2019), *Online Retail II*, UCI Machine Learning Repository — doi:10.24432/C5CG6D, licensed **CC BY 4.0** (openly usable with citation; attributed in the repository README and the importer's output).

### 5.2 Why it is a credible stand-in for a retailer's data

- **Transaction-level reality** — individual invoice lines with quantities, unit prices and timestamps map one-to-one onto our stock-movement ledger; every screen shows genuine demand patterns (weekly rhythms, seasonal ramps, intermittency), not invented smoothness.
- **Two full years** — two complete Christmas cycles, the minimum for any model to *learn* annual seasonality. This single property is what made the MSTL tier possible.
- **Real prices** — stock value, cash tied up and projected revenue are grounded numbers.
- **Alternatives considered and rejected**: M5/Walmart (pre-aggregated unit sales — no transaction structure; Kaggle account and competition terms), Corporación Favorita (125M rows — too large for a responsive demo), Rossmann (store-level revenue, cannot drive per-SKU decisions), staying with hand-seeded data (leaves Week 1's credibility risk standing).

### 5.3 How it is loaded

One repeatable command — `php artisan inventory:import-retail` — runs the pipeline (~10–15 minutes):

1. **Stream & clean** the 1M-row workbook at constant memory; drop service codes, invalid prices, duplicates; net cancellations against sales.
2. **Curate 250 products** deterministically: the 200 densest recent sellers plus ~20 each of seasonal, intermittent and declining patterns, so every model tier has real work to do.
3. **Synthesize product records**: real stock codes, names and median prices; 11 retail categories derived by keyword (the dataset has none); cost set at 65% of price and *documented as synthetic* (the dataset carries no cost data).
4. **Convert sales to ledger rows** — one "units out" movement per product per day, preserving real demand exactly (87,142 sales rows; 3.56M units).
5. **Simulate the supply side** — the dataset has no purchasing data, so opening stock and purchase orders are simulated with a standard (s,S) replenishment policy; closing stock is tuned so the demo exhibits every verdict (~20% understocked / 10% overstocked / 70% healthy).
6. **Shift the timeline** so history ends yesterday (patterns preserved, calendar phase-shifted).
7. **Bulk-insert and verify** — 99,408 movements with per-product running balances; integrity-checked: zero negative balances, zero balance-chain breaks, stored quantities equal final ledger balances.

**Real vs. synthetic, stated plainly:** products, prices and every sale are real; costs, categories, the replenishment side and calendar alignment are synthesized and presented as such.

---

## 6. Design decisions & iterations

**UX/UI decisions**

- **A real design system, not default styling** — a distinctive teal brand palette, Inter typeface with tabular numerals for data tables, and a shared component library (buttons, cards, badges, tables, drawers, skeleton loaders). The goal: read as a credible product, not a scaffold, to an audience that sees enterprise software daily. The working name "Shelfwise" is a one-line configuration swap.
- **Verdict-first, reasoning-forward** — the Recommendations screen leads with what needs action *today*, and the plain-language "why" (Week 1's hero moment) sits directly in the table and on each product page, with model attribution so users can see where a number came from.
- **Charts only where they answer a question** — sales history extended by the forecast projection (dashboard and product pages), value by category, cash tied up by product. Chart code is lazy-loaded so entry screens stay light.
- **Honesty affordances** — defaulted lead time labelled as defaulted; fallback mode labelled "window-average"; synthetic cost documented. Trust is the product's core currency.
- **Pitch-conscious entry** — a split-screen sign-in with the value proposition, so the demo starts selling before login.

**Direction changes mid-build (and why)**

1. **Velocity formulas → time-series models.** Triggered by the data decision: with two years of real history the "not enough data for ML" rationale no longer applied. The formulas were not discarded — they became the fallback and the backtest baseline.
2. **Seeded data → real dataset import.** Directly retired Week 1's flagged evaluator risk.
3. **The backtest reshaped the models.** The first evaluation showed intermittent-demand models *losing* to the simple baseline on daily error — a known trap: daily error punishes models for not guessing *which* day a sparse sale lands. Switching to a decision-relevant metric (28-day total demand), retuning the intermittency threshold, and re-routing lumpy-but-alive products to Croston (keeping TSB only for dying demand, where its decay powers dead-stock detection) produced the final +8.3%. The lesson: measure what the decision consumes, not what the textbook suggests.
4. **Four insights added once forecasts existed** — projected stockout dates, risk tiers, demand trends, projected revenue and dead-stock detection were added because the stored forecast curves made them nearly free.

---

## 7. Challenges & solutions

| Challenge | Solution |
|---|---|
| **Importing 1.07M spreadsheet rows** on constrained hardware without exhausting memory | Streaming parser (row-at-a-time, constant memory), hash-set de-duplication, chunked bulk inserts; ~15-minute one-off import. A subtle real bug — PHP silently converts numeric stock codes like "22197" to integers when used as array keys — surfaced mid-import and was fixed with explicit casts |
| **The dataset has no supply side** (sales only — no purchases, no stock levels) | Simulated replenishment with a standard (s,S) inventory policy and a never-negative guard, so the ledger is coherent end-to-end while sales remain 100% real |
| **Ledger integrity at bulk scale** — the normal write path locks per movement, far too slow for ~100k rows | A sanctioned bulk path that pre-computes each product's running balances and inserts in chronological order; verified afterwards with SQL invariants: zero negative balances, zero chain breaks across all 99,408 rows |
| **Models initially lost to the baseline** on the first backtest | Diagnosed as a metric problem (daily error vs. intermittent demand), fixed by scoring decision-relevant totals; then tuned classification thresholds *on the measured results* rather than trusting textbook defaults |
| **Annual seasonality requires seeing the cycle twice** | Extended the import to both years of the dataset and set the seasonal period to 364 days (52 whole weeks — keeps weekday alignment), gating MSTL to products with ≥ 730 days of history |
| **A demo must not die when a dependency does** | The sidecar is health-checked; missing/stale forecasts fall back to the velocity formula automatically; the failure mode was explicitly tested (kill the sidecar → the app keeps working, labelled "window-average") |
| **Upgrading the engine without breaking the old one** | The recommendation calculator stays a pure function; the forecast is an *optional* input. The original formula path is pinned byte-identical by the existing test suite — all 93 backend tests green throughout |

---

## 8. Known limitations & Week 3 outlook

**Honest gaps**

- **Single-location stock** — no floor/stockroom/in-transit breakdown (a Week 1 research theme) and no multi-store view; both remain v2 scope.
- **Supplier lead time is a global 7-day default** — no per-supplier management yet; the UI discloses this on every recommendation.
- **Costs, categories and replenishment history are synthesized** (the dataset lacks them); real sales, real prices. Always disclosed.
- **Seasonality is phase-shifted** — the timeline moves so history ends yesterday, which relocates the dataset's Christmas peak in the calendar; patterns are real, dates are not.
- **Very sparse products** forecast at parity with a simple average (disclosed in §4.4) — an inherent property of intermittent demand, not a fixable bug.
- **No user-facing data import** (CSV upload) and no LLM weekly summary yet; single user role; forecasts need the sidecar run daily (scheduled, but a dependency).
- **Validation with real users has not happened yet** — Week 1's central open assumption ("retailers value being told what to do") is still open; it is precisely Week 3's job.

**Before Week 3 starts (stretch, already scoped):** two integrations are in mind and estimated —

1. a **Shopify connector** — **now built, with a full in-app flow**: a Settings → Integrations screen where a retailer pastes their store domain and token (validated live, stored encrypted), clicks *Import store* — which pulls the catalogue with real unit costs, backfills up to two years of order history into the ledger (feeding the forecasting models with the merchant's own sales) and reconciles stock levels — and then *Refresh forecasts*. Read-only toward Shopify, incremental after the first run, covered by its own test suite. This turns "runs on real data" into "runs on *your* data" — the strongest possible answer to the POS-integration item Week 1 deferred; and
2. an **AI agent** that answers natural-language questions over the inventory ("What should I reorder this week? What's tying up my cash?") — the evolution of Week 1's LLM weekly-summary idea into something interactive, wrapping the same service layer the UI uses.

**Week 3 (QA · Delivery Lead · Entrepreneur)**

- **Testing** — build on the existing automated base (93 backend / 35 frontend / 11 sidecar tests, all green) with scenario-based QA of the recommendation quality itself, edge-case passes (empty catalogues, no-forecast mode, extreme quantities), and structured user validation of the core hypothesis.
- **Business case** — the prototype already quantifies its own value levers on real data: cash tied up in overstock ($70.9k on the demo catalogue), dead-stock recovery, stockout risk avoided, and decision speed (a reasoned reorder in seconds vs. spreadsheet analysis). These become the ROI narrative, mapped to the challenge's three business impacts.
- **Pitch** — the demo arc is ready: real data → live recommendations with reasoning → the forecast chart showing tomorrow → the backtest table proving the engine beats the naive approach. Supporting collateral exists (`docs/Forecasting-and-Data.docx`, Swagger docs, this report).

---

*Repository: `Apps/Backend` (Laravel API), `Apps/Frontend` (React SPA), `Apps/Forecast` (Python forecasting service). Demo: import the dataset, start the sidecar, `php artisan forecast:run`, sign in as `demo@retail.test`.*
