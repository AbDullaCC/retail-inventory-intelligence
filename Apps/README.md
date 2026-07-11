# Shelfwise — Retail Inventory Intelligence

A retail **inventory intelligence** application:

- **Backend** — Laravel 13 REST API, built as a **modular monolith** with a strict layered
  architecture (**Controller → Service → Business Logic → Mapper → DTO**, over per‑module Eloquent Models).
- **Frontend** — React 19 + TypeScript + Vite + Tailwind CSS v4 SPA that consumes the API.
- **Forecast sidecar** — a small Python **FastAPI** service running
  [Nixtla statsforecast](https://github.com/Nixtla/statsforecast) time-series models
  (AutoETS, MSTL, Croston, TSB) that power demand forecasts and reorder intelligence.
- **Auth** — Laravel Sanctum bearer tokens.
- **Database** — MySQL / MariaDB.

The app manages **Categories**, **Products** (with SKU, pricing and stock levels), **Stock
movements** (an audited in/out/adjustment ledger), a **Dashboard** of inventory KPIs with
charts, and model-driven **Recommendations** (reorder points, stockout risk, demand trends,
dead-stock detection and projected revenue).

---

## Architecture

Each business capability is an independent **module** under `Backend/app/Modules/<Module>/`.
A module owns *all* of its layers, its routes, its migrations and a service provider — so it is
cohesive and could later be extracted into a separate service.

```
HTTP (JSON)
   │
   ▼
Controller            thin — validates via FormRequest, builds an input DTO, returns a DTO
   │  (depends on the Service interface, never the implementation)
   ▼
Service (interface)   the contract for the module's use-cases
   │
   ▼
Business Logic        the concrete service: rules, transactions, orchestration
   │   ├── Models      Eloquent persistence (per module)
   │   └── Mapper      the ONLY place Model ⇄ DTO translation happens
   ▼
DTO                   immutable, framework-agnostic value object → serialised to JSON
```

### Module layout (every module mirrors this shape)

```
Backend/app/Modules/
├── Shared/                 # cross-cutting: BaseData (DTO), PaginatedData, ApiResponse, DomainException, ModuleServiceProvider
├── Auth/                   # register / login / logout / me  (owns the User model)
├── Category/               # full CRUD vertical slice (+ Database/Migrations, Database/Factories)
├── Product/                # CRUD + filtering/pagination; delegates opening stock to the Stock module
├── Stock/                  # adjust / history / recent; Enums/StockMovementType, Exceptions/InsufficientStockException
├── Dashboard/              # read-only aggregation across the other modules (+ /dashboard/trends time series)
├── Shopify/                # connector: pulls products/orders/inventory from a Shopify store (GraphQL Admin API)
│   ├── Support/            # ShopifyClient (GraphQL transport, throttle retry)
│   ├── Services/           # ShopifySyncService (import, history backfill, reconciliation)
│   └── Console/            # `shopify:sync`
├── Forecast/               # owns product_forecasts; talks to the Python sidecar
│   ├── Services/           # ForecastRunner (write side), ForecastReader (read side), DemandSeriesBuilder
│   ├── Console/            # forecast:run (refresh forecasts) · forecast:evaluate (holdout backtest)
│   └── DTOs/Mappers/Controllers/Routes/Database
├── Intelligence/           # read-only analytics: reorder/overstock recommendations
│   ├── Services/           # ReorderCalculator (pure) + IntelligenceService (reads Product/Stock + Forecast reader)
│   ├── Support/            # ReorderConfig (named, tunable constants)
│   ├── Console/            # `inventory:insights` table command
│   └── DTOs/Mappers/Controllers/Routes
└── Chatbot/                # AI assistant: read-only Q&A over the other modules' read services
    ├── Services/           # ChatService (threads + rate limit), ChatOrchestrator (tool loop), Llm/GeminiClient
    ├── Services/Tools/     # 7 read-only tools wrapping the existing service interfaces
    └── DTOs/Requests/Controllers/Routes/Database
```

Modules are registered in `Backend/bootstrap/providers.php`. Each `*ServiceProvider`
**binds** its `Service` interface to its implementation and **loads** its own
`Routes/api.php` (under `/api`) and `Database/Migrations`.

### Tech stack

| Layer     | Tech                                                                 |
|-----------|----------------------------------------------------------------------|
| Backend   | PHP 8.4, Laravel 13, Laravel Sanctum, MySQL/MariaDB, openspout       |
| Frontend  | React 19, TypeScript, Vite, Tailwind CSS v4, React Router 7, axios, Recharts, react-hot-toast, lucide-react, Inter/JetBrains Mono (self-hosted) |
| Forecast  | Python 3.11/3.12, FastAPI, uvicorn, Nixtla statsforecast, pandas     |

---

## Prerequisites

- PHP **8.2+** with `pdo_mysql`, Composer
- Node **18+** and npm
- Python **3.11/3.12** (for the forecast sidecar)
- A running **MySQL / MariaDB** (e.g. XAMPP)

---

## Setup

### 1. Database

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 2. Backend (`Backend/`)

```bash
cd Backend
composer install
cp .env.example .env          # preconfigured for mysql / database "inventory"
php artisan key:generate
php artisan migrate --seed     # tables + small demo catalogue
php artisan serve              # http://127.0.0.1:8000
```

### 3. Real retail data (recommended for the full experience)

The demo seed is tiny. To load **two years of real retail sales history**
(UCI *Online Retail II*, ~250 curated products, ~100k ledger rows):

```bash
# download https://archive.ics.uci.edu/static/public/502/online+retail+ii.zip (43.5 MB)
# unzip and place online_retail_II.xlsx at Backend/storage/app/private/datasets/
php artisan migrate:fresh
php artisan inventory:import-retail        # ~10-15 min (streams 1.07M xlsx rows)
```

### 4. Forecast sidecar (`Forecast/`)

```bash
cd Forecast
py -3.12 -m venv .venv
.venv\Scripts\activate                     # source .venv/bin/activate on unix
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 8100
```

Then refresh forecasts (re-run daily, or schedule `forecast:run`):

```bash
cd Backend
php artisan forecast:run                   # ~1-3 min for 250 products
php artisan forecast:evaluate              # optional: holdout backtest vs the legacy formula
```

The app works without the sidecar — recommendations fall back to the
14-day-average formula until forecasts exist.

### 5. Optional: connect a Shopify store

Instead of (or alongside) the dataset, the app can run on a real Shopify store's data —
entirely from the UI (**Settings → Integrations** in the app):

1. In the Shopify admin: **Settings → Apps and sales channels → Develop apps → Create an app**.
2. Grant the Admin API scopes `read_products`, `read_inventory`, `read_orders`
   (add `read_all_orders` for order history older than 60 days), install the app
   and copy the Admin API access token.
3. In the app, open **Integrations**, paste the store domain + token and click
   **Connect** (the token is validated live and stored encrypted).
4. Click **Import store** — the first sync imports the catalogue (with real unit
   costs), **backfills up to two years of order history into the ledger** and
   reconciles stock levels; later syncs are incremental. Then click
   **Refresh forecasts** to model the store's own sales.

The same flow is scriptable via `SHOPIFY_SHOP_DOMAIN`/`SHOPIFY_ADMIN_TOKEN` in
`Backend/.env` + `php artisan shopify:sync` + `php artisan forecast:run`.
A free [Shopify development store](https://shopify.dev/docs/api/development-stores)
works for demos. The connector is read-only toward Shopify — it never writes back.

### 6. Optional: enable the AI assistant

The floating chat button (bottom-right, on every screen) answers natural-language
questions — *"What should I reorder this week?"*, *"How much cash is stuck in
overstock?"* — by calling the app's own read services (strictly read-only, so it
can never contradict the screens or change data).

1. Create a **free** Gemini API key at <https://aistudio.google.com/apikey>.
2. Set `GEMINI_API_KEY=<your key>` in `Backend/.env`.

Without a key the rest of the app is unaffected; the assistant simply explains
it isn't configured. Note the free tier's daily request cap, and that Google
may train on free-tier traffic — fine for demo data, not for production use.

### 7. Frontend (`Frontend/`)

```bash
cd Frontend
npm install
cp .env.example .env           # VITE_API_URL=http://127.0.0.1:8000/api
npm run dev                    # http://localhost:5173
```

### Demo login

```
email:    demo@retail.test
password: password
```

---

## API reference

> **Interactive docs (Swagger UI):** with the backend running, open
> **http://127.0.0.1:8000/docs**. The raw OpenAPI 3.0 spec is at `/docs/openapi.json`.

All routes are prefixed with `/api`. Every route except `ping`, `auth/register` and `auth/login`
requires `Authorization: Bearer <token>`.

| Method | Endpoint                                   | Description                          |
|--------|--------------------------------------------|--------------------------------------|
| GET    | `/ping`                                    | Health check (public)                |
| POST   | `/auth/register` · `/auth/login`           | → `{ token, user }`                  |
| POST   | `/auth/logout`                             | Revoke current token                 |
| GET    | `/auth/me`                                 | Current user                         |
| GET    | `/dashboard/summary`                       | KPIs + low-stock + recent movements  |
| GET    | `/dashboard/trends?days&product_id`        | Zero-filled daily in/out series + category values (charts) |
| GET    | `/categories` (+ POST/GET/PUT/DELETE `{id}`)| Category CRUD (409 if it still has products) |
| GET    | `/products` (+ POST/GET/PUT/DELETE `{id}`) | Product CRUD — `search, category_id, low_stock, is_active, sort_by, sort_dir, per_page, page` |
| POST   | `/products/{id}/stock-adjustments`         | Adjust stock — `type` (`in`/`out`/`adjustment`), `quantity`, `reason` |
| GET    | `/products/{id}/stock-movements`           | Paginated movement history           |
| GET    | `/stock-movements?limit=N`                 | Recent movements across all products |
| GET    | `/intelligence/recommendations`            | Model-driven recommendations + aggregates |
| GET    | `/products/{id}/recommendation`            | Recommendation for a single product  |
| GET    | `/products/{id}/forecast`                  | 90d daily actuals + 28d forecast with p90 band (charting) |
| GET    | `/forecast/summary`                        | Store-wide daily demand projection + projected 30d revenue |

### Response envelope

```jsonc
{ "data": { /* ... */ }, "message": "Optional message." }                       // single resource
{ "data": [ /* ... */ ], "meta": { "total": 17, "per_page": 10, /* … */ } }     // paginated list
{ "message": "Human-readable error." }                                          // error
{ "message": "...", "errors": { "field": ["..."] } }                            // validation (422)
```

---

## Business rules

- **Stock is only ever changed through the Stock module** (`POST /products/{id}/stock-adjustments`),
  so every change writes an immutable `stock_movements` row (`quantity_before`/`quantity_after`)
  inside a DB transaction with a row lock.
- A stock `out`/`adjustment` that would drive quantity below zero is rejected (`422`).
- A category that still has products cannot be deleted (`409`).
- Creating a product with an opening `quantity` records an "Opening stock" movement.

---

## Demand forecasting & inventory intelligence

Forecasting is split across two pieces:

**The Python sidecar** (`Forecast/`) is stateless: Laravel sends each product's zero-filled
daily sales series (built from the `stock_movements` ledger) and receives a 28-day daily
forecast with a p90 band. Each series is classified by its demand pattern
(Syntetos–Boylan ADI × CV², plus history length) and routed to the right model:

| Pattern | Model |
|---|---|
| Steady seller, 2+ years of history | **MSTL** (weekly + annual seasonality) |
| Steady seller | **AutoETS** (weekly seasonality) |
| Sparse but alive (sells < every 2nd day) | **CrostonOptimized** |
| Dying demand (long silent tail) | **TSB** (probability decays → dead-stock detection) |
| Too little history | SeasonalNaive / Naive |

**The Laravel Forecast module** stores one forecast row per product (`forecast:run`,
scheduled daily) and exposes read models. The **Intelligence** module feeds the stored
forecast into the pure `ReorderCalculator`: velocity becomes the model's expected daily
demand, the suggested order covers the forecast demand plus a safety buffer sized from
the p90 band, and forecast-only insights light up — **projected stockout date** (walking
stock down the daily curve), **stockout risk** (high/medium/low vs the p90 band),
**demand trend** (next 28 days vs previous 28), **projected 30-day units/revenue** and
**dead stock** (model expects ≈ no further demand). Products without a fresh forecast
(> 48h old) fall back to the original 14-day-average formula — the app never breaks when
the sidecar is down.

`php artisan forecast:evaluate` backtests the models against a 28-day holdout of the real
sales history and prints total-demand WAPE vs the legacy formula (the models beat it by
~8% overall, ~10% on smooth sellers, on the demo dataset). `php artisan inventory:insights`
prints the per-product verdict table.

---

## Demo data

Sales history is imported from a **real retail dataset**:

> Chen, Daqing (2019). **Online Retail II**. UCI Machine Learning Repository.
> https://doi.org/10.24432/C5CG6D — licensed CC BY 4.0.

`php artisan inventory:import-retail` streams the raw workbook (1,067,371 transactions from
a UK retailer, Dec 2009 – Dec 2011), curates ~250 SKUs across demand patterns, derives
categories by keyword, and converts sales into the `stock_movements` ledger (one `out` row
per SKU per day, net of returns). Because the dataset has no supply side, **costs (65% of
median price), categories and replenishment purchase orders are synthesized** via an (s,S)
policy simulation — closing stock levels are tuned so the demo shows every verdict
(~20% understocked / ~10% overstocked / ~70% healthy). The whole timeline is shifted so
history ends yesterday; weekly/seasonal *patterns* are preserved but phase-shifted against
the calendar.

---

## Testing

**Backend** — PHPUnit, in-memory SQLite (`phpunit.xml`): `cd Backend && php artisan test`
covers pure units (mappers, `ReorderCalculator` in both fallback and forecast modes),
service logic (stock math, forecast runner with faked HTTP, staleness fallback, dashboard
trends aggregation) and HTTP feature tests (auth, CRUD, adjustments, recommendation +
forecast endpoints).

**Frontend** — Vitest + Testing Library: `cd Frontend && npm test` covers formatters,
trend helpers, the API error extractor, query-param cleaning, `StockStatusBadge` and the
recommendation presentation map.

**Sidecar** — pytest: `cd Forecast && pytest` covers demand-pattern classification and the
API contract (shapes, intervals, non-negativity).

---

## Project structure

```
Apps/
├── Backend/     # Laravel API (modular monolith)
│   └── app/Modules/{Shared,Auth,Category,Product,Stock,Dashboard,Forecast,Intelligence}/
├── Forecast/    # Python FastAPI + statsforecast sidecar
│   └── app/{main,schemas,classify,forecaster}.py
└── Frontend/    # React SPA
    └── src/{api,components,components/ui,components/charts,context,lib,pages}/
```
