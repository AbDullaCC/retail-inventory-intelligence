# Judge Q&A — pitch bullet points

> Full judge-simulation version (every question + rehearsed answer, ordered by
> the five pitch sections, incl. GDPR): `Pitch-Defense-QA.md`.

> Numbers below verified on 11/7/2026. The demo data shifts daily — the morning
> of the pitch run `php artisan chatbot:evaluate` (the golden-question scorecard:
> asks the live assistant ~12 judge questions and checks every answer against the
> database) plus `inventory:insights`, and update anything you'll quote.

---

## Product & market

**Q: Shopify/Square already track inventory — what do you add?**
- They track; we *decide*. The gap is the decision layer: what to reorder, how much, when, and where cash is stuck — with the reasoning shown.
- Demo moment: the Recommendations screen — a reasoned order quantity in one glance.

**Q: What's the business impact, in numbers?**
- On the demo catalogue: ~$24.5k cash locked in overstock identified, 59 products flagged for reorder before stockout, dead stock flagged with recoverable value.
- Three levers: fewer stockouts (protect revenue), less overstock (free cash), decisions in seconds instead of spreadsheet hours.

**Q: Who is this for?**
- SME retailers: big enough to feel stockouts/overstock, too small for enterprise planning tools (SAP IBP et al.).

---

## Data & forecasting

**Q: Is this real data?**
- Yes — 1.07M real transactions from a UK retailer, 2 full years (UCI Online Retail II, CC BY 4.0).
- Honest footnote: sales/prices real; costs, categories and the purchasing side synthesized (the dataset has none) — always disclosed.

**Q: How does the forecasting work?**
- Each product is auto-classified by its demand pattern and routed to the right statistical model: steady sellers → AutoETS/MSTL (weekly + annual seasonality), sparse sellers → Croston, dying items → TSB (powers dead-stock detection).
- Runs nightly on CPU; all ~250 products in under a minute on a laptop.

**Q: How accurate is it? / How do you know?**
- Backtest on a blind 28-day holdout: **8.3% better** than the baseline formula overall, **+10.5%** on steady sellers (~89% of volume).
- Honest caveat: very sparse items match a simple average at best — inherent to intermittent demand, and we say so.
- Reproducible live: `php artisan forecast:evaluate`.

**Q: Why not deep learning?**
- Per-SKU statistical models are competitive at product level (M5 competition lesson), explainable, and run on a $5 VPS. DL adds cost and opacity, not accuracy, at this scale.

**Q: What if the forecasting service dies?**
- Nothing breaks — every recommendation silently falls back to an explainable moving-average formula, labelled as such. (Demo-able: kill the sidecar live.)

---

## Shopify connector

**Q: Does it work with real store data?**
- Yes — live-verified against a real Shopify store: paste domain + token in the UI, it imports the catalogue, backfills up to 2 years of order history into the ledger, reconciles stock, and forecasts *the merchant's own sales*.
- Read-only toward Shopify; token stored encrypted; incremental after first sync.

**Q: How hard is onboarding a merchant?**
- Minutes, no terminal: create a custom app in Shopify admin, paste two values, click Import, click Refresh forecasts.

---

## AI assistant

**Q: How did you verify the chatbot answers correctly?**
- Three layers: 42 automated tests on the machinery; **differential testing** — 20+ live questions checked against direct database queries (12/14 stress-test answers exact to the digit); and grounding by architecture.
- The 2 misses were bugs in *our* tool layer (not model hallucination) — found via the source citations, fixed same day, each fix became a guardrail.
- We do **not** claim "always correct" — we claim: every number comes from the same tested engine as the screens, every answer cites its sources, and it says "I don't know" instead of guessing.

**Q: Can it hallucinate or corrupt data?**
- It has no database access — only 8 fixed **read-only** tools wrapping the same services the UI uses; it cannot contradict the screens and there is no write tool to call.
- Tested traps: nonexistent product → "not found"; "place an order" → refuses; off-topic → declines.

**Q: What does the AI cost to run?**
- ~half a cent per question (paid tier). Heavy merchant ≈ $8–15/month worst case, typical <$2 — priced into the plan or gated behind a premium tier.
- Exploitation-proof by bounds: auth required, 30 msgs/hour/user, capped message size, context, tool rounds and output.

**Q: Is merchant data safe? Does Google train on it?**
- Demo (free tier): public dataset only — Google may use free-tier data, which is why real merchants require the **paid tier, where Google contractually does not train on prompts**.
- Queries are pseudonymous: no shop name, owner, or account identity is ever sent — only the question + inventory figures. The system holds no consumer PII at all.
- Endgame option: the LLM sits behind one interface — swappable to a self-hosted model so data never leaves the customer's infrastructure.

---

## Operations & scale

**Q: What are the server requirements?**
- Ordinary: no GPU, ever. 2 vCPU / 4 GB VPS covers a small retailer end-to-end; forecasting is a nightly batch (users never wait on it).
- Measured: full catalogue forecasts in <1 min on a 6-core laptop.

**Q: Can it scale to many merchants (multi-tenant)?**
- Compute is the easy part — forecasting is stateless and parallel (100 tenants × 500 SKUs ≈ 3–4 CPU-hours nightly). The real work is data-model tenant scoping; the database is the first bottleneck, not the models. (Documented in the README sizing section.)

**Q: Tech stack — and why?**
- Laravel modular monolith (module-per-capability, swap-ready boundaries), React SPA, Python FastAPI sidecar for the models. Right-sized for an MVP: microservice discipline without microservice cost. 200+ automated tests across the three apps.

---

## Honest gaps (own them before they're asked)

- **Supplier lead times** are a 7-day default (no per-supplier data yet) — disclosed on every recommendation; v2.
- **Single location, single store** per install today.
- **User validation** with real retailers hasn't happened yet — the Shopify connector exists precisely to run that test on a merchant's own data.
- Sparse-demand items forecast no better than a simple average (disclosed; inherent).

---

*Deeper material: `Week2_Build_Report.md` (architecture, backtest), `Week3_Build_Report.md` (connector, live bugs), `Forecasting-and-Data.docx` (models explainer), `Apps/README.md` (sizing & deployment).*
