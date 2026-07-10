# Internship — Week 3 Build Report

## Retail Inventory Intelligence

**From "runs on real data" to "runs on *your* data"**

| | |
|---|---|
| **Prepared by** | Abdulla |
| **Date** | 10/7/2026 |
| **Week 3 role** | Integration Engineer · QA · Developer (AI-assisted development) |
| **Challenge** | Retail Inventory Intelligence — Retail · Commerce |
| **Business impact** | Cost Reduction · Margin Improvement · Decision Speed |

---

## 1. Recap — where Week 2 left off

Week 2 closed with a working prototype: two years of real retail transactions in the ledger, a time-series forecasting engine that beats the baseline formula by 8.3% on a reproducible backtest, and a recommendation screen that explains every verdict in plain language. Its report scoped two stretch items on the way to Week 3: a **Shopify connector** (then freshly built in its first form) and an **AI agent** for natural-language questions over the inventory.

The Week 1 plan cast Week 3 as QA · Delivery Lead · Entrepreneur. The week that actually happened was a close relative of that plan, with one deliberate shift of emphasis: instead of QA-ing the prototype only against itself, the week's central effort was **proving the product against the outside world** — completing the Shopify connector, giving it a full in-app flow, connecting it to a real store, and fixing every defect that live usage surfaced. That *is* QA, of the most unforgiving kind: five real bugs were found and fixed that the automated test suite — all green throughout — could never have caught (§4). The remaining Week 3 threads: hardening the demo-data workflow around the connector (§5), a data-driven UX iteration on the hero screen (§6), and starting the AI assistant's implementation (§8).

---

## 2. Why the Shopify connector matters

Week 1 deferred POS integration to v2; Week 2 retired the "seeded data" credibility risk by importing a real public dataset. The connector completes that arc. In the pitch, "the system runs on real retail data" is a good answer; *"paste your store's credentials and it runs on **your** data — your products, your costs, your sales history, your stock levels"* is the answer that ends the discussion. It also converts the forecasting engine from a demo asset into a merchant asset: the backfilled order history is exactly the daily-demand series the models train on, so a newly connected store gets model-grade recommendations on its own sales from day one.

---

## 3. What was built — the connector, end to end

A new `Shopify` module in the modular monolith (same controller → service → mapper → DTO layering as every other module), talking to Shopify's **GraphQL Admin API** (the REST API is closed to new apps), and **strictly read-only toward Shopify** — the product pulls; it never writes to the merchant's store.

**The in-app flow — no terminal, no config files.** A *Settings → Integrations* screen where a retailer:

1. **Connects** — pastes their store domain and admin token. The token is validated with a live call before anything is saved, and stored **encrypted at rest**. Clear error states distinguish "wrong credentials" from "Shopify unreachable".
2. **Imports the store** — one click pulls the catalogue (with real unit costs — improving margin numbers over the demo dataset's synthesized costs), backfills the store's **order history** into the movement ledger, and reconciles on-hand stock levels.
3. **Refreshes forecasts** — a second click sends the merchant's own sales history through the forecasting sidecar. Both long-running actions were also given API endpoints so the whole journey works from the UI.
4. **Disconnects** — removes the credentials while keeping the imported data.

**Engineering decisions worth recording:**

- **Backfill that cannot corrupt the ledger.** On first sync, historical orders become backdated ledger rows, and each product's opening balance is *computed* (current Shopify stock + units sold since) so every balance chain ends at the store's true on-hand quantity and can never go negative.
- **Incremental and idempotent afterwards.** A sync watermark plus a variant→product map means re-running sync — or clicking the button five times — imports nothing twice; later syncs write through the standard locked stock-adjustment path.
- **Self-reconciling.** Every sync ends with an inventory-reconcile pass: any drift between the ledger and Shopify's counts is recorded as an explicit, auditable adjustment movement rather than silently overwritten.
- **Rate-limit aware.** The API client detects Shopify's throttling responses and retries with backoff.
- **Graceful degradation, as everywhere.** Store connected but Shopify down → clear error, app fully functional. No store connected → the demo dataset flow is unchanged.

---

## 4. Proving it live — five bugs the test suite could not catch

The connector shipped with a dedicated automated suite (16 tests) running against a faked Shopify API — backfill balance math, idempotent re-syncs, conflict handling, throttle retries, credential validation. All green. Then it was connected to a **real Shopify development store**, and reality disagreed five times. Every defect was diagnosed, fixed, and — where the lesson generalized — pinned with a regression test the same day:

| # | Symptom in live use | Root cause | Fix |
|---|---|---|---|
| 1 | Connecting failed instantly: *"Invalid variables parameter"* | PHP serializes an empty array as a JSON *list*, but GraphQL requires the `variables` field to be an *object* — a faked API accepts both | Omit the field entirely when empty |
| 2 | 23 of 26 products silently skipped on import | Real (dev-store) catalogues often have variants **without SKUs**; the importer treated no-SKU as unimportable | Derive a stable fallback SKU from Shopify's own variant ID |
| 3 | Forecast refresh crashed after a live sale | A product whose *first-ever* sale happened **today** produces an empty daily-history series (series end yesterday); the sidecar rightly rejects empty input | The series builder skips such products until their first full day of history exists; the UI explains the wait |
| 4 | Clicking *Sync now* repeatedly re-imported the same order — sales triple-counted | Shopify's order search is **minute-granular**, so the incremental watermark re-matched orders at the boundary | Ledger-level dedupe: an order line is only written if no movement for that order exists |
| 5 | After a demo-dataset re-import: *"No query results for model Product 289"* | The dataset importer truncates the catalogue with FK checks disabled, which **bypasses cascade deletes** — the connector's variant→product map survived, pointing at dead product IDs | Two-sided fix: the importer's `--fresh` now resets connector state (keeping credentials), *and* every sync self-heals by pruning orphaned mappings before use — plus a regression test reproducing the exact scenario |

The transferable lesson, stated for the record: **contract tests prove your logic; only live integration proves the integration.** Bugs 1–2 were properties of the real API and real merchant data that no self-authored fake would reproduce; bugs 3–5 emerged from *sequences of real user behaviour* (selling something mid-demo, mashing a button, refreshing the dataset) that unit-level thinking does not generate.

**End state, verified live:** store connected through the UI → 26 products imported with real costs → full order history backfilled into the ledger → 16 stock levels reconciled → forecasts refreshed on the merchant's own sales — all through the interface, and repeatable from scratch (disconnect → dataset re-import → reconnect → sync) without manual cleanup.

---

## 5. Hardening the data workflow

Bug #5 above doubled as a workflow problem: the demo dataset and a connected store now share one ledger, so refreshing one must not strand the other. The import command was made connector-aware, and the pre-demo ritual is now three deterministic steps — **re-import the dataset → *Sync now* → *Refresh forecasts*** — each idempotent, each safe to repeat. The failure modes discovered this week (stale mappings, re-imported orders, empty series) all have either an automatic self-heal or an explicit, tested error path.

---

## 6. UX iteration — the hero screen, second pass

With real usage came real feedback: the Recommendations screen — the product's hero — had grown cluttered. The redesign was driven by the live data itself; the decisive number: the demo catalogue has **45 urgent products**, and the old layout rendered 45 individual "Order today" cards before the table even began.

- **The KPI cards now *are* the filter** — click *Need reorder / Overstocked / Dead stock / Healthy* to focus the list; each card carries its money context ("$49.5K tied up", "$34 recoverable"). The duplicate filter counts are gone.
- **"Order today" became a capped strip** — the five most critical products as compact rows (runs-out date, order quantity, risk), with a link to the full urgent list, instead of an unbounded card grid.
- **Severity-first ordering** — the table previously sorted alphabetically, burying urgent items mid-list; it now leads with what needs action soonest, and six columns are click-sortable.
- **Columns adapt to the view** — the reorder view drops the cash column, the cash views drop the order columns, so no view is half dashes.
- **Search** across name, SKU and category; friendlier dates; whole-row click-through to the product.

---

## 7. Quality status

| Suite | Tests | Status |
|---|---|---|
| Backend (PHPUnit) | **112** (473 assertions) — up from 93, including 16 dedicated Shopify tests | ✅ green |
| Frontend (Vitest) | **39** — up from 35 | ✅ green |
| Forecast sidecar (pytest) | **11** | ✅ green |

The two byte-identical guarantees from Week 2 still hold and are still pinned by tests: the fallback velocity formula is unchanged, and the ledger's integrity invariants (no negative balances, no chain breaks) survived the addition of a second bulk-write path (the Shopify backfill).

---

## 8. In progress — the AI assistant

The second stretch item from Week 2's report is now **in implementation and expected to finish next week**: an AI assistant that answers natural-language questions over the merchant's inventory — *"What should I reorder this week?"*, *"Where is my cash stuck?"*, *"What's the risk on my best-sellers?"*.

The design principle is the same one that shaped the forecasting layer: **the intelligence wraps the existing, tested service layer — it does not invent new data paths.** The assistant is read-only, grounds every answer in the same recommendation and forecast data the screens render (so it can never contradict the UI), and cites the products it reasons about. It lands as its own module in the modular monolith, consistent with everything else in the codebase.

---

## 9. Known gaps & Week 4 outlook

**Honest gaps, carried forward**

- **User validation of the core hypothesis** ("retailers value being told what to do") remains open — the Shopify flow makes that test *possible* on a merchant's own data, but it has not been run with real users yet.
- **Single-location stock**, **global 7-day lead-time default** (still disclosed on every recommendation), and the demo dataset's **synthesized costs/categories** all remain v2 scope — though a connected Shopify store now supplies *real* unit costs, softening the cost caveat where it matters.
- The connector is scoped to one store per install, and initial backfill length is bounded by Shopify's order-history access.
- Very sparse products still forecast at parity with a simple average — an inherent property of intermittent demand, disclosed since Week 2.

**Week 4**

1. **Finish the AI assistant** and integrate it into the UI.
2. **Structured QA passes** on the full journey — empty catalogue, no-forecast mode, connected-store and demo-data permutations.
3. **Business case & pitch** — the ROI narrative now has its strongest exhibit: the live arc *connect a real store → its history becomes forecasts → forecasts become reasoned orders* — demonstrated end-to-end in minutes, on the audience's own terms ("this could be your store").

---

*Repository: `Apps/Backend` (Laravel API), `Apps/Frontend` (React SPA), `Apps/Forecast` (Python forecasting service). Demo: import the dataset, start the sidecar, sign in as `demo@retail.test` — or connect a Shopify store from Settings → Integrations.*
