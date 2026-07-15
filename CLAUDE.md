# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A retail **inventory intelligence** MVP ("Shelfwise") split into three apps under `Apps/`:

- `Apps/Backend` — Laravel 13 REST API (PHP 8.4), Sanctum bearer-token auth, MySQL/MariaDB.
- `Apps/Frontend` — React 19 + TypeScript + Vite + Tailwind CSS v4 SPA, consuming the API.
- `Apps/Forecast` — Python 3.12 FastAPI sidecar running Nixtla `statsforecast` models; **stateless** (Laravel sends daily series, sidecar returns forecasts — no DB access from Python).

The backend is a **modular monolith**: business capabilities are independent modules under `Apps/Backend/app/Modules/<Module>/`, each owning a full vertical slice of layers.

## Commands

All backend commands run from `Apps/Backend`, frontend from `Apps/Frontend`, sidecar from `Apps/Forecast`.

```bash
# Backend
php artisan serve                                   # http://127.0.0.1:8000
php artisan migrate --seed                          # migrate + seed small demo catalogue
php artisan inventory:import-retail                 # import real 2-year retail history (needs the
                                                    #   UCI Online Retail II xlsx in storage/app/private/datasets;
                                                    #   --fresh truncates catalogue tables first; ~10-15 min)
php artisan shopify:sync                            # pull products/orders/inventory from a connected Shopify
                                                    #   store (needs SHOPIFY_SHOP_DOMAIN + SHOPIFY_ADMIN_TOKEN;
                                                    #   first run backfills order history, then incremental)
php artisan forecast:run                            # refresh per-product forecasts (sidecar must be running)
php artisan forecast:evaluate                       # holdout backtest: model WAPE vs legacy 14d-average
php artisan inventory:insights                      # per-product verdict table (model column included)
php artisan chatbot:evaluate                        # golden-question scorecard for the AI assistant: asks the
                                                    #   live model, checks answers vs DB-computed ground truth
                                                    #   (needs GEMINI_API_KEY + MySQL; --list to preview, --only=key)
php artisan test                                    # full suite (PHPUnit, in-memory SQLite)
php artisan test --filter=test_stock_in_increases   # single test by method name
php artisan test tests/Feature/Stock/StockServiceTest.php  # single file
./vendor/bin/pint                                   # format (Laravel Pint)

# Forecast sidecar (Python 3.11/3.12; venv at Apps/Forecast/.venv)
.venv\Scripts\activate && uvicorn app.main:app --host 127.0.0.1 --port 8100
pytest                                              # sidecar tests (classification + API contract)

# Frontend
npm run dev          # http://localhost:5173
npm run build        # tsc -b && vite build  (typecheck + bundle)
npm test             # Vitest single run   (test:watch for watch mode)
npx vitest run src/lib/format.test.ts        # single test file
npm run lint         # oxlint
```

Tests use SQLite `:memory:` (configured in `phpunit.xml`) — they do **not** touch the MySQL dev DB. The dev server requires a running MySQL/MariaDB (e.g. XAMPP) with an `inventory` database. Demo login: `demo@retail.test` / `password`.

## Backend architecture — the layering is the point

Every module enforces this one-directional flow; **do not skip layers**:

```
Controller (thin: validate via FormRequest, build input DTO, return DTO)
  → Service interface (Services/Contracts/*)        ← controllers depend on this, never the impl
    → Service implementation (business logic, transactions, orchestration)
      → Eloquent Model (persistence)
      → Mapper (the ONLY place Model ⇄ DTO conversion happens)
    → returns a DTO (immutable, extends Shared\DTOs\BaseData, serialised to JSON)
```

A module (`app/Modules/<Module>/`) contains: `Controllers/`, `Requests/`, `Services/` (+ `Services/Contracts/`), `DTOs/`, `Mappers/`, `Models/`, `Providers/`, `Routes/api.php`, and (where it has tables) `Database/Migrations/` + `Database/Factories/`. Modules: `Shared`, `Auth`, `Category`, `Product`, `Stock`, `Dashboard`, `Shopify`, `Forecast`, `Intelligence`, `Chatbot`.

### How modules are wired (read these to understand the big picture)

- **`bootstrap/providers.php`** registers every module's `*ServiceProvider`.
- Each provider extends `Shared\Providers\ModuleServiceProvider`, **binds its `Service` interface to its implementation** in `register()`, and in `boot()` calls `loadApiRoutes()` (wraps the module's `Routes/api.php` in the `/api` prefix + `api` middleware) and `loadMigrationsFrom()`. So routes and migrations are per-module, not central. `routes/api.php` only holds `/api/ping`.
- **`Shared\Http\ApiResponse`** defines the response envelope: `{ data, message? }` for items, `{ data, meta }` for paginated lists, `{ message }` for bare messages. DTOs implement `JsonSerializable`, so controllers return them directly through `ApiResponse`.
- **API docs** are spec-first in `app/Support/OpenApi/OpenApiSpec.php` (a hand-authored OpenAPI 3.0 array), served as Swagger UI at `/docs` and JSON at `/docs/openapi.json` (`DocsController`, `routes/web.php`). When you add/change an endpoint, update this spec — it is not generated from code.
- **`Shared\Exceptions\DomainException`** carries an HTTP status; `bootstrap/app.php` renders it (and `ModelNotFoundException`) to JSON for `api/*` routes. Throw it from services for business-rule violations (e.g. `InsufficientStockException` → 422, category-has-products → 409).

### Cross-module + domain rules that aren't obvious

- **Stock is the single source of truth for on-hand quantity.** Product attributes (`ProductService`) and stock (`StockService`) are separate: `ProductData` deliberately has **no `quantity`** field. All quantity changes go through `StockService::adjust`, which runs in a `DB::transaction` with `lockForUpdate`, writes an immutable `stock_movements` ledger row (`quantity_before`/`quantity_after`), and rejects going below zero.
- `ProductService::create` with an opening quantity **delegates to `StockService`** to record an opening movement (Product module depends on Stock module; no container cycle because `StockService` only uses the Product *model*, not `ProductService`).
- The `User` model lives in **`Auth/Models/User.php`** (not `app/Models`); `config/auth.php` points there.
- **The `Forecast` module owns forecasting state**: the `product_forecasts` table (one row per product, replaced on each `forecast:run`), `DemandSeriesBuilder` (zero-filled daily `out` series from the ledger — the only ledger→calendar translation), `ForecastRunner` (chunked HTTP to the sidecar via `config/services.php` `forecast` block, env `FORECAST_SERVICE_URL`) and `ForecastReader` (**staleness policy lives here alone**: rows older than 48h are treated as absent). The sidecar contract and model-selection rules (Syntetos–Boylan + MSTL tier for 730+ days of history; Croston vs TSB split on a dying-tail heuristic) are documented in `Apps/Forecast/README.md`. Graceful degradation is a hard requirement: sidecar down or stale forecasts → everything falls back to the window-average formula.
- **The `Intelligence` module is read-only analytics** — it owns **no tables/models**; it reads forecasts through `ForecastReaderInterface` (cross-module dependency mirroring Product→Stock). `ReorderCalculator` (in `Intelligence/Services/`) is a **pure, framework-free** function of `(snapshot, ReorderConfig, ?ForecastSnapshot)` — snapshots are passed in, never fetched. With a snapshot: velocity = the model's expected daily demand, suggested qty = forecast demand over lead+coverage plus a p90-gap safety buffer, and the forecast-only fields light up (`projected_stockout_date`, `stockout_risk`, `demand_trend_pct`, `projected_*_30d`, and the fourth verdict `dead_stock`). Without one (fallback): sales velocity = sum of **`out`** movement quantities over the last `VELOCITY_WINDOW_DAYS` ÷ window (`in`/`adjustment` ignored) — this path must stay byte-identical (existing tests pin it). There is **no supplier-lead-time field**, so lead time is always defaulted (`ReorderConfig::DEFAULT_LEAD_TIME_DAYS = 7`); unit cost is `product.cost` with a null fallback. All tunables are named constants on `Intelligence/Support/ReorderConfig.php`. `php artisan inventory:insights` prints the per-product table. **Intermediate maths are never rounded — rounding is display-only** (the reasoning strings and the frontend).
- **The `Shopify` module is the store connector** (UI at `/integrations` + endpoints `GET shopify/status`, `POST shopify/connect` (validates the token live, stores it in `shopify_connections` with an `encrypted` cast), `POST shopify/sync`, `DELETE shopify/connection`; plus `POST forecast/run` in the Forecast module so the whole flow works without a terminal). Credentials resolve **DB-connection first, then SHOPIFY_* env** (empty strings count as unset). `shopify:sync` pulls a store via the **GraphQL Admin API** (custom-app token in `config/services.php` `shopify` block; REST is closed to new apps). `shopify_product_maps` keys idempotency (one product per variant); `shopify_sync_states` holds the order watermark — **null watermark = first run**, which backfills order history as backdated bulk ledger rows with a computed opening balance (opening = Shopify stock + units sold, so the chain ends at the real on-hand and never goes negative; only products with an empty ledger are backfilled). Incremental runs write through `StockService::adjust` then backdate; drift conflicts are skipped and fixed by the closing inventory-reconcile pass (`adjustment` movements). `ShopifyClient` retries THROTTLED GraphQL responses with backoff.
- **The `Chatbot` module is the AI assistant** (floating button → drawer in the frontend `Layout`; endpoints `GET chat/threads`, `GET chat/threads/{id}`, `POST chat/messages`). It is a **strictly read-only** LLM layer: `ChatOrchestrator` runs a Gemini function-calling loop (≤ `max_tool_iterations`, then a forced-text call, then a deterministic fallback) over a fixed `ToolRegistry` of 8 tools that wrap the **existing read service interfaces** (Dashboard/Intelligence/Product/Forecast/Stock) — no new business logic, results capped by `ToolResultTruncator`. Tool failures (wrong id, etc.) are returned to the *model* as `{error}` payloads, never thrown. `GeminiClient` (config `services.chatbot`, env `GEMINI_API_KEY`, default model `gemini-3.1-flash-lite`) owns every provider quirk: restricted schema subset (strip `additionalProperties`/`$schema`/`$defs`), raw-part echo for 3.x `thoughtSignature`s, empty `args` list→object normalisation, thought-part exclusion from answer text, 429 backoff, verbatim error surfacing as 503. `ChatService` persists `chat_threads`/`chat_messages` in **two transactions around a transaction-free LLM loop** (user message must survive provider failure) and rate-limits per user/hour. In tests, fake Gemini with `Http::fake(['*generateContent' => …])` — glob keys, **never regex** (regex keys silently hit the real API).
- **Demo data is real retail history**: `inventory:import-retail` (in `app/Console/Commands/`, helpers in `database/seeders/RetailDataset/`) streams the UCI Online Retail II workbook, curates ~250 SKUs, and **bulk-inserts backdated ledger rows directly** (bypassing `StockService::adjust` — the only sanctioned bypass) with per-product `quantity_before/after` chains and a whole-day date shift so history ends yesterday. Sales are real; costs/categories/replenishment are synthesized (attribution: CC BY 4.0, see Apps/README.md "Demo data").

### Conventions

- `declare(strict_types=1)` everywhere **except Controllers** — controllers take route IDs as `int $id` and rely on PHP's loose coercion of numeric route params (routes use `->whereNumber(...)`). Do not add strict_types to controllers.
- Models use Laravel 13 PHP attributes (`#[Fillable([...])]`, `#[Hidden([...])]`) and a `casts()` method; modular models override `newFactory()` to point at their module factory.
- Mappers are constructor-injected (e.g. `ProductMapper` depends on `CategoryMapper`); resolve services/mappers via the container (`app(...)`), not `new`, in app code.

## Frontend architecture

- `src/lib/api.ts` — single axios instance: request interceptor attaches the bearer token from `localStorage`; response interceptor clears it and redirects to `/login` on 401. `apiErrorMessage()` extracts the first validation error / message.
- `src/api/*.ts` — one typed module per backend resource; `src/types.ts` mirrors the backend DTO JSON (snake_case keys).
- `src/context/AuthContext.tsx` — holds the session, bootstraps from a persisted token via `/auth/me`; `ProtectedRoute` gates the authenticated routes; `Layout` is the app shell. Routes are declared in `App.tsx` (React Router v7, declarative `<Routes>`).
- `src/components/ui/` — the shared design system (barrel `index.ts`; all `../components/ui` imports resolve here). Primitives: Button, Input/Select/Textarea/Checkbox, Field, Card, Badge (**tone keys `gray|green|red|amber|indigo` are a public contract** — `lib/recommendation.ts` tests pin them), Modal/Drawer/ConfirmDialog, Table/THead/TBody/TH/TD/Pagination, StatCard (+Sparkline), PageHeader, SegmentedControl, Tooltip, CapacityBar, Avatar, Skeleton family, EmptyState. Reuse these rather than re-styling.
- Design tokens live in `src/index.css` `@theme` — brand = "Harbor" teal scale (`brand-*`), semantic `success/warning/danger/info` scales, `--shadow-card/pop`, Inter Variable + JetBrains Mono (self-hosted via @fontsource, no CDN). **Never use raw `indigo-*` classes.** Brand name/tagline: `src/lib/brand.ts` (one-line swap).
- `src/components/charts/` — Recharts components (default exports, ALWAYS lazy-loaded via `React.lazy` + `<Suspense fallback={<ChartSkeleton/>}>` so auth/catalog pages don't pay the bundle cost): MovementsTrendChart (accepts an optional forecast `projection`), CategoryValueChart, CashTiedUpChart, HistoryForecastChart (history + dashed forecast + p90 band).

### TypeScript build constraints (will fail `tsc -b`)

`tsconfig.app.json` sets `verbatimModuleSyntax` (use `import type` for type-only imports), `noUnusedLocals`/`noUnusedParameters`, and `erasableSyntaxOnly` (**no TS `enum`s** — use string-literal unions / const objects). Test files (`src/**/*.test.ts(x)`, `src/test/`) are excluded from the production build and run only under Vitest.

## Environment gotchas

- The Windows **C: drive is space-constrained**; npm's cache is set to `D:/npm-cache`. If installs fail with `ENOSPC` or deps silently don't install (npm may exit 0 anyway), check C: free space and `npm cache clean --force`.
- CORS is configured in `config/cors.php` for the Vite origin (`http://localhost:5173`); the API is stateless bearer-token (no cookies), so `supports_credentials` is false.
