# Rosewood Royale Backend

Laravel API for the Rosewood Royale estate management platform. It serves admin operations, the customer portal, public property listings, authentication, and a server-side proxy to the FastAPI AI concierge service.

Repository: [Hsu-Hlaing-Htet/em_backend](https://github.com/Hsu-Hlaing-Htet/em_backend)

---

## Project Overview

This backend is a Laravel 12 application that exposes JSON APIs used by the Vue frontend and integrates with a separate AI service.

Core responsibilities:

- Authenticate users with Laravel Sanctum
- Manage buildings, rooms, contracts, utilities, invoices, payments, receipts, and maintenance
- Expose public property listing and detail endpoints
- Proxy public property and customer rent AI questions to the FastAPI AI service
- Generate and deliver document PDFs and email notifications

---

## Tech Stack

Confirmed from `composer.json` and project config:

| Layer | Technology |
| --- | --- |
| Language | PHP `^8.2` |
| Framework | Laravel `^12.0` |
| Auth | Laravel Sanctum `^4.3` |
| Testing | Pest `^4.3` (with Pest Laravel plugin) |
| Code style | Laravel Pint |
| Default DB | SQLite (configurable via `DB_CONNECTION`) |
| Queue default | `database` (`QUEUE_CONNECTION`) |
| Container | Optional `Dockerfile` (PHP 8.2 CLI image) |

---

## Main Features

- **Authentication** — login, logout, current user, forgot/reset password, change password
- **Role-based access** — `super_admin`, `admin`, and `customer` via `EnsureRole` middleware
- **Property management** — buildings, rooms, room images
- **Contracts** — sale and rent contract drafts, approval/rejection, active/approved views, document download/export/email
- **Utilities & billing** — utility types/rates, utility records, invoices, payments, receipts, late fees, charge types, payment methods/plans
- **Customer portal** — dashboard, profile, contracts, invoices, payments, receipts, notifications, maintenance requests
- **Public property API** — list, featured, stats, and detail endpoints with search and pagination
- **AI integration** — Laravel proxies questions to the FastAPI AI service (browser never calls AI directly)
- **Admin extras** — dashboard charts, list PDF exports, document preview PDF

---

## Project Structure

```text
app/
  Http/
    Controllers/     # Admin, Auth, Customer, Public controllers
    Middleware/      # EnsureRole and related middleware
    Requests/        # Form request validation
    Resources/       # API resources
  Models/            # Eloquent models
  Services/          # Domain services (billing, public property, AI proxy, documents)
  Mail/              # Mailable classes
  Notifications/     # Auth and account notifications
  Policies/          # Authorization policies
  Support/           # Shared helpers and profiles
bootstrap/           # Application bootstrap and middleware aliases
config/              # Laravel + app config (includes config/ai.php)
database/
  migrations/        # Schema migrations
  seeders/           # Demo and reference data seeders
  factories/         # Model factories for tests/seeders
routes/
  api.php            # Authenticated and public AI API routes
  web.php            # Public property routes + SPA fallback view
  console.php        # Console routes
tests/
  Feature/           # Feature tests (Admin, Auth, Customer, Public, Ai, Timebox*)
  Unit/              # Unit tests
```

---

## Requirements

- PHP 8.2+
- Composer 2
- SQLite (default) or MySQL/PostgreSQL if configured
- Extensions commonly required by Laravel (e.g. `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`)
- Optional: Redis if you switch cache/queue/session drivers
- Optional: running FastAPI AI service for AI endpoints

---

## Installation

```bash
git clone git@github.com:Hsu-Hlaing-Htet/em_backend.git
cd em_backend
composer install
```

Create a local `.env` file (see Environment Setup), then:

```bash
php artisan key:generate
```

If using SQLite with the default path:

```bash
touch database/database.sqlite
```

---

## Environment Setup

Do **not** commit real secrets. Configure values in a local `.env` file only.

### Application

| Variable | Purpose |
| --- | --- |
| `APP_NAME` | Application name |
| `APP_ENV` | Environment (`local`, `production`, …) |
| `APP_KEY` | Encryption key (`php artisan key:generate`) |
| `APP_DEBUG` | Debug mode |
| `APP_URL` | Backend base URL |
| `FRONTEND_URL` | Frontend origin used for CORS fallback and links |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | Locale settings |

### Database

| Variable | Purpose |
| --- | --- |
| `DB_CONNECTION` | Driver (`sqlite`, `mysql`, …). Default config uses `sqlite` |
| `DB_DATABASE` | Database name/path |
| `DB_HOST` / `DB_PORT` / `DB_USERNAME` / `DB_PASSWORD` | Used when not on SQLite |

### Auth / session / CORS

| Variable | Purpose |
| --- | --- |
| `SANCTUM_STATEFUL_DOMAINS` | Stateful domains for Sanctum |
| `SESSION_DRIVER` / `SESSION_DOMAIN` / `SESSION_LIFETIME` | Session settings |
| `CORS_ALLOWED_ORIGINS` | Comma-separated exact allowed origins |
| `CORS_SUPPORTS_CREDENTIALS` | Credentialed CORS flag |

### Queue / cache / mail (as needed)

| Variable | Purpose |
| --- | --- |
| `QUEUE_CONNECTION` | Queue driver (default config: `database`) |
| `CACHE_STORE` | Cache store |
| `MAIL_MAILER` | Mail transport |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | From identity |
| `MAIL_LOGO_URL` | Logo URL used in branded emails |
| `RESEND_API_KEY` | Used when sending via Resend |

### AI proxy (`config/ai.php`)

| Variable | Purpose |
| --- | --- |
| `AI_SERVICE_BASE_URL` | FastAPI base URL (default in config: `http://127.0.0.1:8001`) |
| `AI_SERVICE_TIMEOUT_SECONDS` | HTTP timeout in seconds (default: `60`) |
| `AI_SERVICE_INTERNAL_KEY` | Optional shared secret sent as `X-Rosewood-AI-Internal-Key` |

Never place real API keys, passwords, tokens, or database credentials in this README or in git.

---

## Database Setup

```bash
php artisan migrate
php artisan db:seed
```

`DatabaseSeeder` loads roles, users, billing reference data, workflow/demo scenarios, and room images. Do not publish seeder credentials in documentation or source control.

Fresh reset (destroys local data):

```bash
php artisan migrate:fresh --seed
```

---

## Running Locally

Start the HTTP server:

```bash
php artisan serve
```

Health check route (from `bootstrap/app.php`):

- `GET /up`

API routes from `routes/api.php` are served under the `/api` prefix.

Composer also defines a `dev` script that runs the Laravel server, queue listener, log watcher, and frontend Vite process together when the sibling frontend project is available. For backend-only work, `php artisan serve` plus a queue worker is enough.

---

## Queue Worker

Default queue connection is `database` (`config/queue.php`).

Run a worker locally:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

Or:

```bash
php artisan queue:work
```

Ensure migrations that create the `jobs` table have been applied when using the database queue driver.

---

## Public Property API

Defined in `routes/web.php` under the `api/public` prefix:

| Method | Path | Description |
| --- | --- | --- |
| `GET` | `/api/public/properties` | Paginated listings |
| `GET` | `/api/public/properties/featured` | Latest public sale listings (limit 6) |
| `GET` | `/api/public/properties/stats` | Inventory stats (`total`, `available`) |
| `GET` | `/api/public/properties/{property}` | Property detail |

Query parameters supported by `PublicPropertyService`:

- `purpose` — `sale` (default) or `rent`
- `search` — matches room number, description, building name, or location
- `per_page` — page size (default `12`)
- standard Laravel `page` pagination

Sale listings require an active sale contract. Rent listings require room type `rent`/`both` and status `available`.

---

## Authentication

Auth routes in `routes/api.php` (under `/api`):

| Method | Path | Auth |
| --- | --- | --- |
| `POST` | `/api/auth/login` | Public |
| `POST` | `/api/auth/forgot-password` | Public |
| `POST` | `/api/auth/reset-password` | Public |
| `POST` | `/api/auth/logout` | Sanctum |
| `GET` | `/api/auth/me` | Sanctum |
| `POST` | `/api/auth/change-password` | Sanctum |

Protected route groups use:

- `auth:sanctum`
- `role:...` middleware (`App\Http\Middleware\EnsureRole`)

Roles seeded by `RoleSeeder`:

- `super_admin`
- `admin`
- `customer`

Admin APIs require `super_admin` or `admin`. Customer portal APIs require `customer`.

---

## AI Integration

Laravel proxies AI traffic through `App\Services\AiAssistantProxyService` using `config/ai.php`.

### Public property assistant

| Method | Path | Auth |
| --- | --- | --- |
| `POST` | `/api/public/ai/property/ask` | Public (throttled: `ai-public`, 20/minute per IP) |

Request body (`AskPropertyQuestionRequest`):

- `question` (required)
- `property_id` (optional) — when present, Laravel preloads that public property for the AI payload
- `purpose` (optional: `rent` or `sale`)

Proxied to FastAPI: `POST {AI_SERVICE_BASE_URL}/api/v1/property/ask`

### Customer rent assistant

| Method | Path | Auth |
| --- | --- | --- |
| `POST` | `/api/customer/ai/rent/ask` | Sanctum + `customer` role |

Request body (`AskRentQuestionRequest`):

- `question` (required)

Proxied to FastAPI: `POST {AI_SERVICE_BASE_URL}/api/v1/rent/ask` with the customer’s Bearer token forwarded.

Optional header to the AI service when configured:

- `X-Rosewood-AI-Internal-Key: {AI_SERVICE_INTERNAL_KEY}`

---

## Testing

This project uses Pest.

Run the full suite:

```bash
php artisan test
```

Or via Composer:

```bash
composer test
```

Useful focused examples:

```bash
php artisan test tests/Feature/Ai
php artisan test tests/Feature/Public
php artisan test tests/Feature/Auth
```

Feature coverage includes Admin, Auth, Customer, Public, Ai, and Timebox workflow suites under `tests/Feature/`.

---

## Security

- Keep `.env` out of version control
- Never commit API keys, tokens, passwords, or mail credentials
- Use Sanctum tokens/session auth for protected endpoints
- Enforce roles with the `role` middleware alias
- Restrict browser origins with `CORS_ALLOWED_ORIGINS` / `FRONTEND_URL`
- Keep `AI_SERVICE_INTERNAL_KEY` private and aligned with the AI service configuration
- Public AI ask endpoint is rate-limited (`ai-public`)

---

## Related Repositories

| Project | Repository |
| --- | --- |
| Frontend | https://github.com/Hsu-Hlaing-Htet/em_frontend |
| Backend | https://github.com/Hsu-Hlaing-Htet/em_backend |
| AI | https://github.com/Hsu-Hlaing-Htet/em_ai |

---

## Developer

Designed and Developed by Hsu_Hlaing_Htet

https://github.com/Hsu-Hlaing-Htet
