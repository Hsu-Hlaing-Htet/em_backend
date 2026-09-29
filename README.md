# Rosewood Royale — Backend

Laravel API for Rosewood Royale Residences.

This repository is the main application backend: authentication, business rules, MySQL data access, documents/email, and the server-side proxy to the FastAPI AI service. The Vue frontend and FastAPI AI service both talk to this API.

Repository: [Hsu-Hlaing-Htet/em_backend](https://github.com/Hsu-Hlaing-Htet/em_backend)

---

## Quick Start — Run Order

Start the full system in this order:

1. **MySQL** — create/use database `rosewood_royale`
2. **Laravel Backend** — Terminal 1 (this repository)
3. **FastAPI AI Service** — Terminal 2
4. **Vue Frontend** — Terminal 3

| Terminal | Service | Typical command | Local URL |
| --- | --- | --- | --- |
| — | MySQL | Start MySQL; ensure DB exists | `127.0.0.1:3306` |
| 1 | Laravel Backend | `php artisan serve` | http://localhost:8000 |
| 2 | FastAPI AI | `uvicorn app.main:app --reload --port 8001` (in `em_ai`) | http://localhost:8001 |
| 3 | Vue Frontend | `npm run dev` (in `em_frontend`) | http://localhost:5173 |

Connection flow:

```text
Browser
   |
   v
Vue Frontend (:5173)
   |
   v
Laravel Backend (:8000)
   | \
   |  \--> FastAPI AI (:8001)
   |
   +-----> MySQL (:3306)
```

Optional fourth process for async jobs (mail, queued work):

```bash
php artisan queue:listen --tries=1 --timeout=0
```

Default queue driver in `.env.example` is `database`.

---

## First-Time Setup

Do this once after cloning.

### 1. Clone and install PHP dependencies

```bash
git clone git@github.com:Hsu-Hlaing-Htet/em_backend.git
cd em_backend
composer install
```

Requirements:

- PHP `^8.2` (see `composer.json`)
- Composer 2
- MySQL (project `.env.example` uses MySQL)
- PHP extensions commonly required by Laravel (`pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, …)

### 2. Environment file

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Configure MySQL

In `.env` (names from `.env.example`):

```bash
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rosewood_royale
DB_USERNAME=root
DB_PASSWORD=
```

Create the empty database in MySQL (example):

```sql
CREATE DATABASE rosewood_royale CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Also set frontend / CORS / AI placeholders for local work:

```bash
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:5174
AI_SERVICE_BASE_URL=http://127.0.0.1:8001
```

Do not commit real secrets (`APP_KEY`, `RESEND_API_KEY`, DB passwords, `AI_SERVICE_INTERNAL_KEY`, …).

### 4. Migrate and seed (safe for first setup)

```bash
php artisan migrate
php artisan db:seed
```

`DatabaseSeeder` loads roles, users, billing reference data, demo scenarios, and room images. Demo login credentials are printed in the terminal by the seeder — do not publish those passwords in documentation or commits.

**Do not use** `migrate:fresh`, `db:wipe`, or truncate commands for normal setup. They destroy existing data.

### 5. Sibling services

Set up and run:

- Frontend: https://github.com/Hsu-Hlaing-Htet/em_frontend
- AI: https://github.com/Hsu-Hlaing-Htet/em_ai

---

## Daily Development

When returning to the project:

1. Start MySQL
2. Start Laravel:

```bash
php artisan serve
```

3. Start FastAPI (in `em_ai`) if you need AI
4. Start Vue (in `em_frontend`): `npm run dev`
5. Optional queue worker if you need email / queued jobs:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

Health check: http://localhost:8000/up

---

## Running the Full Rosewood Royale System

### Step 1 — Database

Start MySQL and confirm `rosewood_royale` exists with credentials matching backend `.env`.

### Step 2 — Laravel Backend

```bash
cd em_backend
php artisan serve
```

- App: http://localhost:8000  
- Health: http://localhost:8000/up  
- API: http://localhost:8000/api  

`php artisan serve` defaults to port **8000**.

### Step 3 — FastAPI AI

```bash
cd em_ai
source .venv/bin/activate
uvicorn app.main:app --reload --port 8001
```

URL: http://localhost:8001

### Step 4 — Vue Frontend

```bash
cd em_frontend
npm run dev
```

URL: http://localhost:5173  
Frontend `.env` should use `VITE_API_BASE_URL=http://localhost:8000/api`.

---

## Verify Everything Is Working

- [ ] `GET http://localhost:8000/up` returns OK
- [ ] Frontend login hits `/api/auth/login` successfully
- [ ] Admin/customer lists load (Laravel ↔ MySQL)
- [ ] `GET http://localhost:8001/health` returns OK when AI is running
- [ ] Property AI via Laravel: `POST /api/public/ai/property/ask` reaches FastAPI
- [ ] Customer rent AI via Laravel: `POST /api/customer/ai/rent/ask` (authenticated) reaches FastAPI

---

## Architecture

```text
Browser
   |
   v
Vue Frontend
   |
   v
Laravel Backend
   | \
   |  \--> FastAPI AI
   |
   +-----> MySQL
```

- **Vue** owns the user interface.
- **Laravel** is the authoritative API, auth, and business/data layer.
- **MySQL** stores application data.
- **Laravel** calls FastAPI for AI answers (`config/ai.php`).
- **FastAPI** reads Laravel APIs for grounding; it does not own the database or replace Laravel.

Note: `config/database.php` falls back to `sqlite` if `DB_CONNECTION` is unset. The project’s `.env.example` documents **MySQL** (`rosewood_royale`) for local Rosewood Royale development. Prefer that setup.

---

## Repository Responsibility

This backend owns:

- Authentication / authorization (Laravel Sanctum + `role` middleware)
- JSON APIs for admin, customer, public, and auth
- Business rules and Eloquent models
- MySQL access (buildings, rooms, contracts, utilities, invoices, payments, receipts, maintenance, notifications, contact inquiries, …)
- Contract / invoice / receipt / utility document generation and PDF export
- Email / notifications (mailer configured via env; queue when using database queue)
- Server-side AI proxy to FastAPI (`AiAssistantProxyService`)

It does **not** own the Vue UI or the LLM prompts inside FastAPI.

---

## Environment Configuration

Copy `.env.example` → `.env`. Important variable **names** (never commit real values):

### Application / frontend link

| Variable | Purpose |
| --- | --- |
| `APP_URL` | Backend base URL |
| `FRONTEND_URL` | Frontend origin (password-reset links, etc.) |
| `APP_KEY` | Encryption key |
| `APP_TIMEZONE` | Default `Asia/Yangon` in `.env.example` |

### Laravel → MySQL

| Variable | Purpose |
| --- | --- |
| `DB_CONNECTION` | Driver (`mysql` in `.env.example`) |
| `DB_HOST` / `DB_PORT` | MySQL host/port |
| `DB_DATABASE` | Database name (`rosewood_royale`) |
| `DB_USERNAME` / `DB_PASSWORD` | Credentials |

### CORS / Sanctum

| Variable | Purpose |
| --- | --- |
| `CORS_ALLOWED_ORIGINS` | Exact browser origins allowed |
| `CORS_SUPPORTS_CREDENTIALS` | Keep `false` with Bearer-token SPA auth |
| `SANCTUM_STATEFUL_DOMAINS` | Hosts for stateful Sanctum (if used) |

### Laravel → FastAPI

| Variable | Purpose |
| --- | --- |
| `AI_SERVICE_BASE_URL` | FastAPI base URL (default `http://127.0.0.1:8001`) |
| `AI_SERVICE_TIMEOUT_SECONDS` | Proxy timeout (default `60`) |
| `AI_SERVICE_INTERNAL_KEY` | Optional shared secret (`X-Rosewood-AI-Internal-Key`); must match AI `AI_INTERNAL_KEY` |

### Mail / queue / documents (as needed)

| Variable | Purpose |
| --- | --- |
| `MAIL_MAILER` / `RESEND_API_KEY` / `MAIL_FROM_*` | Outbound email |
| `QUEUE_CONNECTION` | Default `database` in `.env.example` |
| `DOCUMENTS_CHROME_PATH` / `CHROME_NO_SANDBOX` | Chrome/Chromium for PDF generation |

---

## Project Structure

```text
app/
├── Http/Controllers/   # Admin, Auth, Customer, Public
├── Http/Middleware/    # Role checks, …
├── Models/             # Eloquent models
├── Services/           # Billing, documents, AI proxy, public property, …
├── Mail/               # Mailables
├── Notifications/      # Notifications
└── Policies/           # Authorization
config/                 # Includes config/ai.php
database/migrations/
database/seeders/
routes/api.php          # Main API (+ auth, admin, customer, public AI)
routes/web.php          # Public property routes under /api/public, …
tests/Feature/          # Pest feature suites
tests/Unit/
```

---

## Tech Stack

| Layer | Technology |
| --- | --- |
| Language | PHP `^8.2` |
| Framework | Laravel `^12` |
| Auth | Laravel Sanctum `^4.3` |
| Testing | Pest `^4.3` + Pest Laravel plugin |
| Code style | Laravel Pint |
| Local DB (documented) | MySQL via `.env.example` |
| Queue / session / cache (example) | `database` drivers |
| Container | Optional `Dockerfile` (PHP 8.2 Apache) + `render.yaml` |

---

## Common Commands

| Command | Description |
| --- | --- |
| `composer install` | Install PHP dependencies |
| `php artisan key:generate` | Generate `APP_KEY` |
| `php artisan migrate` | Apply migrations (non-destructive) |
| `php artisan db:seed` | Seed development/demo data |
| `php artisan serve` | HTTP server on port 8000 |
| `php artisan queue:listen --tries=1 --timeout=0` | Process queued jobs |
| `php artisan test` | Run Pest suite |
| `composer test` | Config clear + `php artisan test` |

Composer also defines a `dev` script that can start Laravel, queue, logs, and a sibling `../frontend` Vite process together. That assumes a local sibling frontend checkout; for three separate repos, start each terminal manually as shown above.

---

## Testing

```bash
php artisan test
# or
composer test
```

Focused examples:

```bash
php artisan test tests/Feature/Auth
php artisan test tests/Feature/Ai
php artisan test tests/Feature/Public
php artisan test tests/Feature/Admin/ListExportPdfTest.php
```

Feature coverage includes Admin, Auth, Customer, Public, Ai, ContactInquiry, and Timebox suites under `tests/Feature/`.

---

## AI Integration (Laravel proxy)

Browser → Laravel → FastAPI.

| Method | Laravel path | Proxied FastAPI path |
| --- | --- | --- |
| `POST` | `/api/public/ai/property/ask` | `{AI_SERVICE_BASE_URL}/api/v1/property/ask` |
| `POST` | `/api/customer/ai/rent/ask` | `{AI_SERVICE_BASE_URL}/api/v1/rent/ask` |

Public property ask is throttled (`ai-public`). Customer rent ask requires Sanctum + `customer` role and forwards the Bearer token.

---

## Troubleshooting

**Database connection refused**  
→ Confirm MySQL is running  
→ Confirm `DB_*` values and that `rosewood_royale` exists  
→ Confirm `pdo_mysql` is installed

**Frontend CORS errors**  
→ Add the exact frontend origin to `CORS_ALLOWED_ORIGINS`  
→ Confirm `FRONTEND_URL`  
→ Restart `php artisan serve` after `.env` changes (`php artisan config:clear` if cached)

**AI unavailable / 502 from AI routes**  
→ Confirm FastAPI health: http://localhost:8001/health  
→ Confirm `AI_SERVICE_BASE_URL`  
→ Confirm optional `AI_SERVICE_INTERNAL_KEY` matches AI `AI_INTERNAL_KEY`  
→ Confirm AI `OPENAI_API_KEY` and `BACKEND_BASE_URL`

**Emails never arrive**  
→ Confirm mail env (`MAIL_MAILER`, `RESEND_API_KEY`, …)  
→ Run a queue worker when `QUEUE_CONNECTION=database`

**PDF export fails**  
→ Confirm Chrome/Chromium is available, or set `DOCUMENTS_CHROME_PATH`  
→ On restricted environments, `CHROME_NO_SANDBOX=true` may be required (see Docker / Render config)

---

## Deployment

Confirmed in-repo:

- `Dockerfile` — PHP 8.2 Apache image with Chromium for PDFs
- `render.yaml` — Render web service `rosewood-royale-backend`, health check `/up`, MySQL-oriented env keys, AI and mail env placeholders
- `docker-compose.yml` — optional container run against an external database

Do not put production credentials in git. Set secrets in the host/platform dashboard.

---

## Related Repositories

- Frontend: https://github.com/Hsu-Hlaing-Htet/em_frontend
- Backend: https://github.com/Hsu-Hlaing-Htet/em_backend
- AI: https://github.com/Hsu-Hlaing-Htet/em_ai

---

## Developer

Designed and Developed by Hsu_Hlaing_Htet

https://github.com/Hsu-Hlaing-Htet
