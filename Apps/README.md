# Retail Inventory — MVP

A small retail **inventory management** application:

- **Backend** — Laravel 13 REST API, built as a **modular monolith** with a strict layered
  architecture (**Controller → Service → Business Logic → Mapper → DTO**, over per‑module Eloquent Models).
- **Frontend** — React 19 + TypeScript + Vite + Tailwind CSS SPA that consumes the API.
- **Auth** — Laravel Sanctum bearer tokens.
- **Database** — MySQL / MariaDB.

The app manages **Categories**, **Products** (with SKU, pricing and stock levels), **Stock
movements** (an audited in/out/adjustment ledger) and a **Dashboard** of inventory KPIs.

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
│   ├── Controllers/        # AuthController
│   ├── Requests/           # RegisterRequest, LoginRequest
│   ├── DTOs/               # RegisterData, LoginData, AuthTokenDTO, UserDTO
│   ├── Mappers/            # UserMapper
│   ├── Models/             # User
│   ├── Services/           # AuthService + Contracts/AuthServiceInterface
│   ├── Providers/          # AuthServiceProvider (bindings + routes)
│   └── Routes/             # api.php
├── Category/               # full CRUD vertical slice (+ Database/Migrations, Database/Factories)
├── Product/                # CRUD + filtering/pagination; delegates opening stock to the Stock module
├── Stock/                  # adjust / history / recent; Enums/StockMovementType, Exceptions/InsufficientStockException
└── Dashboard/              # read-only aggregation across the other modules
```

Modules are registered in `Backend/bootstrap/providers.php`. Each `*ServiceProvider`:
- **binds** its `Service` interface to its business-logic implementation, and
- **loads** its own `Routes/api.php` (under `/api`) and `Database/Migrations`.

### Tech stack

| Layer     | Tech                                                                 |
|-----------|----------------------------------------------------------------------|
| Backend   | PHP 8.4, Laravel 13, Laravel Sanctum, MySQL/MariaDB                  |
| Frontend  | React 19, TypeScript, Vite, Tailwind CSS v4, React Router 7, axios, react-hot-toast, lucide-react |

---

## Prerequisites

- PHP **8.2+** with `pdo_mysql`, Composer
- Node **18+** and npm
- A running **MySQL / MariaDB** (e.g. XAMPP)

---

## Setup

### 1. Database

Create the database (XAMPP MariaDB shown):

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 2. Backend (`Backend/`)

```bash
cd Backend
composer install
cp .env.example .env          # already preconfigured for mysql / database "inventory"
php artisan key:generate
php artisan migrate --seed     # creates tables + demo catalogue
php artisan serve              # http://127.0.0.1:8000
```

> If your MySQL has a root password or a different host/port, edit `DB_*` in `.env` first.

### 3. Frontend (`Frontend/`)

```bash
cd Frontend
npm install
cp .env.example .env           # VITE_API_URL=http://127.0.0.1:8000/api
npm run dev                    # http://localhost:5173
```

Open **http://localhost:5173**.

### Demo login

```
email:    demo@retail.test
password: password
```

(Or register a new account from the UI.)

---

## API reference

All routes are prefixed with `/api`. Every route except `ping`, `auth/register` and `auth/login`
requires the header `Authorization: Bearer <token>`.

| Method | Endpoint                                   | Description                          |
|--------|--------------------------------------------|--------------------------------------|
| GET    | `/ping`                                    | Health check (public)                |
| POST   | `/auth/register`                           | Create account → `{ token, user }`   |
| POST   | `/auth/login`                              | Login → `{ token, user }`            |
| POST   | `/auth/logout`                             | Revoke current token                 |
| GET    | `/auth/me`                                 | Current user                         |
| GET    | `/dashboard/summary`                       | KPIs + low-stock + recent movements  |
| GET    | `/categories`                              | List categories (with product count) |
| POST   | `/categories`                              | Create category                      |
| GET    | `/categories/{id}`                         | Show category                        |
| PUT    | `/categories/{id}`                         | Update category                      |
| DELETE | `/categories/{id}`                         | Delete (409 if it still has products)|
| GET    | `/products`                                | List — `search, category_id, low_stock, is_active, sort_by, sort_dir, per_page, page` |
| POST   | `/products`                                | Create (optional `quantity` = opening stock) |
| GET    | `/products/{id}`                           | Show product                         |
| PUT    | `/products/{id}`                           | Update product attributes            |
| DELETE | `/products/{id}`                           | Delete product                       |
| POST   | `/products/{id}/stock-adjustments`         | Adjust stock — `type` (`in`/`out`/`adjustment`), `quantity`, `reason` |
| GET    | `/products/{id}/stock-movements`           | Paginated movement history           |
| GET    | `/stock-movements?limit=N`                 | Recent movements across all products |

### Response envelope

```jsonc
// single resource
{ "data": { /* ... */ }, "message": "Optional message." }

// paginated list
{ "data": [ /* ... */ ], "meta": { "total": 17, "per_page": 10, "current_page": 1, "last_page": 2, "from": 1, "to": 10 } }

// error
{ "message": "Human-readable error." }
// validation error (422)
{ "message": "...", "errors": { "field": ["..."] } }
```

---

## Business rules

- **Stock is only ever changed through the Stock module** (`POST /products/{id}/stock-adjustments`),
  so every change writes an immutable `stock_movements` row (`quantity_before`/`quantity_after`).
  Stock writes run inside a DB transaction with a row lock to stay consistent under concurrency.
- A stock `out`/`adjustment` that would drive quantity below zero is rejected (`422`,
  `InsufficientStockException`).
- A category that still has products cannot be deleted (`409`).
- Creating a product with an opening `quantity` records an "Opening stock" movement.

---

## Testing

**Backend** — PHPUnit, against an in-memory SQLite DB (configured in `phpunit.xml`):

```bash
cd Backend
php artisan test
```

54 tests covering: pure units (enum, `PaginatedData`, `ProductFilterData`, all mappers),
service business logic (stock math, insufficient-stock & category-has-products rules,
dashboard aggregation), and HTTP feature tests (auth + token revocation, products
CRUD/validation/pagination, stock adjustments, category `409`).

**Frontend** — Vitest + Testing Library (jsdom):

```bash
cd Frontend
npm test          # single run   (npm run test:watch for watch mode)
```

16 tests covering the formatters, the API error extractor, the products query-param
cleaner, and the `StockStatusBadge` component states.

---

## Project structure

```
Apps/
├── Backend/     # Laravel API (modular monolith)
│   └── app/Modules/{Shared,Auth,Category,Product,Stock,Dashboard}/
└── Frontend/    # React SPA
    └── src/{api,components,context,lib,pages}/
```
