# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A retail **inventory management** MVP split into two apps under `Apps/`:

- `Apps/Backend` — Laravel 13 REST API (PHP 8.4), Sanctum bearer-token auth, MySQL/MariaDB.
- `Apps/Frontend` — React 19 + TypeScript + Vite + Tailwind CSS v4 SPA, consuming the API.

The backend is a **modular monolith**: business capabilities are independent modules under `Apps/Backend/app/Modules/<Module>/`, each owning a full vertical slice of layers.

## Commands

All backend commands run from `Apps/Backend`, frontend from `Apps/Frontend`.

```bash
# Backend
php artisan serve                                   # http://127.0.0.1:8000
php artisan migrate --seed                          # migrate + seed demo catalogue
php artisan test                                    # full suite (PHPUnit, in-memory SQLite)
php artisan test --filter=test_stock_in_increases   # single test by method name
php artisan test tests/Feature/Stock/StockServiceTest.php  # single file
./vendor/bin/pint                                   # format (Laravel Pint)

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

A module (`app/Modules/<Module>/`) contains: `Controllers/`, `Requests/`, `Services/` (+ `Services/Contracts/`), `DTOs/`, `Mappers/`, `Models/`, `Providers/`, `Routes/api.php`, and (where it has tables) `Database/Migrations/` + `Database/Factories/`. Modules: `Shared`, `Auth`, `Category`, `Product`, `Stock`, `Dashboard`.

### How modules are wired (read these to understand the big picture)

- **`bootstrap/providers.php`** registers every module's `*ServiceProvider`.
- Each provider extends `Shared\Providers\ModuleServiceProvider`, **binds its `Service` interface to its implementation** in `register()`, and in `boot()` calls `loadApiRoutes()` (wraps the module's `Routes/api.php` in the `/api` prefix + `api` middleware) and `loadMigrationsFrom()`. So routes and migrations are per-module, not central. `routes/api.php` only holds `/api/ping`.
- **`Shared\Http\ApiResponse`** defines the response envelope: `{ data, message? }` for items, `{ data, meta }` for paginated lists, `{ message }` for bare messages. DTOs implement `JsonSerializable`, so controllers return them directly through `ApiResponse`.
- **`Shared\Exceptions\DomainException`** carries an HTTP status; `bootstrap/app.php` renders it (and `ModelNotFoundException`) to JSON for `api/*` routes. Throw it from services for business-rule violations (e.g. `InsufficientStockException` → 422, category-has-products → 409).

### Cross-module + domain rules that aren't obvious

- **Stock is the single source of truth for on-hand quantity.** Product attributes (`ProductService`) and stock (`StockService`) are separate: `ProductData` deliberately has **no `quantity`** field. All quantity changes go through `StockService::adjust`, which runs in a `DB::transaction` with `lockForUpdate`, writes an immutable `stock_movements` ledger row (`quantity_before`/`quantity_after`), and rejects going below zero.
- `ProductService::create` with an opening quantity **delegates to `StockService`** to record an opening movement (Product module depends on Stock module; no container cycle because `StockService` only uses the Product *model*, not `ProductService`).
- The `User` model lives in **`Auth/Models/User.php`** (not `app/Models`); `config/auth.php` points there.

### Conventions

- `declare(strict_types=1)` everywhere **except Controllers** — controllers take route IDs as `int $id` and rely on PHP's loose coercion of numeric route params (routes use `->whereNumber(...)`). Do not add strict_types to controllers.
- Models use Laravel 13 PHP attributes (`#[Fillable([...])]`, `#[Hidden([...])]`) and a `casts()` method; modular models override `newFactory()` to point at their module factory.
- Mappers are constructor-injected (e.g. `ProductMapper` depends on `CategoryMapper`); resolve services/mappers via the container (`app(...)`), not `new`, in app code.

## Frontend architecture

- `src/lib/api.ts` — single axios instance: request interceptor attaches the bearer token from `localStorage`; response interceptor clears it and redirects to `/login` on 401. `apiErrorMessage()` extracts the first validation error / message.
- `src/api/*.ts` — one typed module per backend resource; `src/types.ts` mirrors the backend DTO JSON (snake_case keys).
- `src/context/AuthContext.tsx` — holds the session, bootstraps from a persisted token via `/auth/me`; `ProtectedRoute` gates the authenticated routes; `Layout` is the app shell. Routes are declared in `App.tsx` (React Router v7, declarative `<Routes>`).
- `src/components/ui.tsx` — the shared Tailwind primitives (Button, Input, Modal, Card, Badge, Pagination, etc.). Reuse these rather than re-styling.

### TypeScript build constraints (will fail `tsc -b`)

`tsconfig.app.json` sets `verbatimModuleSyntax` (use `import type` for type-only imports), `noUnusedLocals`/`noUnusedParameters`, and `erasableSyntaxOnly` (**no TS `enum`s** — use string-literal unions / const objects). Test files (`src/**/*.test.ts(x)`, `src/test/`) are excluded from the production build and run only under Vitest.

## Environment gotchas

- The Windows **C: drive is space-constrained**; npm's cache is set to `D:/npm-cache`. If installs fail with `ENOSPC` or deps silently don't install (npm may exit 0 anyway), check C: free space and `npm cache clean --force`.
- CORS is configured in `config/cors.php` for the Vite origin (`http://localhost:5173`); the API is stateless bearer-token (no cookies), so `supports_credentials` is false.
