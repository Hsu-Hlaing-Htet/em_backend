# Rosewood Royale — Backend

Laravel API for Rosewood Royale Residences. This service owns authentication, authorization, property and customer records, contracts, billing, payments, maintenance, documents, and email. MySQL stores application data. The Vue frontend calls this API; Laravel proxies assistant requests to the FastAPI AI service.

Repository: [em_backend](https://github.com/Hsu-Hlaing-Htet/em_backend).

## Technology Stack

- PHP, Laravel 12, and Laravel Sanctum 4 for Bearer-token authentication.
- MySQL for documented local development; database-backed sessions, cache, and queues.
- Pest 4 / PHPUnit 12 for tests; Laravel Pint for PHP formatting.
- Chrome/Chromium for server-side PDF conversion.

Dependency constraints are in [composer.json](composer.json); exact installed versions are recorded in [composer.lock](composer.lock).

## Prerequisites

- **PHP 8.3–8.5 for development with the current lockfile.** The application declares `^8.2`, but the locked Pest/PHPUnit development dependencies require PHP 8.3 or later. The production Dockerfile uses PHP 8.2 and installs without development dependencies.
- Composer 2. Run `composer check-platform-reqs --lock` to check the lockfile's PHP and extension requirements. Also enable `pdo_mysql` for application data, `pdo_sqlite` for default integration tests, and GD for image-generating seed helpers.
- A running MySQL server and credentials permitted to create/use a local development database. No MySQL version constraint is declared in the repository.
- Git. The shell examples use Bash-compatible syntax on macOS/Linux; Windows users can use WSL.
- Optional Chrome/Chromium for PDF downloads. The enhanced list-PDF path also uses Node with built-in `fetch` and `WebSocket` support (for example, the frontend's Node 22.12+ environment); it falls back to Chrome CLI conversion when unavailable.

The core API does not require FastAPI, an OpenAI key, Redis, or a separate Node build. Start MySQL → backend → AI (when needed) → frontend. Each service runs in its own terminal.

## Quick Start

From this repository's root, with MySQL running and the prerequisites installed:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env`: set `DB_*` for your local MySQL account and a **development-only** database named `rosewood_royale`. Set `MAIL_MAILER=log` to develop without email credentials. Create the database if it does not exist (see [Detailed Local Setup](#detailed-local-setup)), then run:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8000
```

In another terminal:

```bash
curl --fail http://127.0.0.1:8000/up
curl --fail -H 'Accept: application/json' http://127.0.0.1:8000/api/public/properties
```

Expect HTTP 200 from both; the second request should return property JSON after demo seeding. `/up` checks application boot, not database or AI connectivity. Seeding writes demo records and files; use only a database intended for development or demonstration.

## Detailed Local Setup

### 1. Clone and install

```bash
git clone https://github.com/Hsu-Hlaing-Htet/em_backend.git
cd em_backend
composer install
composer check-platform-reqs --lock
cp .env.example .env
php artisan key:generate
```

Skip steps already completed in Quick Start. Keep `composer.lock`; use `composer install` rather than updating dependency versions during onboarding. PHP must be able to write to `storage/` and `bootstrap/cache/`.

### 2. Create and configure the database

Connect using an administrative MySQL account (replace `root` if necessary; `-p` prompts for its password):

```bash
mysql -u root -p
```

At the MySQL prompt:

```sql
CREATE DATABASE rosewood_royale CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Exit MySQL and set `.env` to match the database and an account with schema/data permissions on it:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rosewood_royale
DB_USERNAME=your_local_database_user
DB_PASSWORD=your_local_database_password
MAIL_MAILER=log
```

The credentials above are placeholders. Do not copy them unchanged. `.env.example` uses `root` with an empty password; change those values if your server requires authentication. The documented application setup uses MySQL even though Laravel's database configuration falls back to SQLite when no connection is configured.

### 3. Initialize schema, demo data, and public files

```bash
php artisan config:clear
php artisan migrate
php artisan db:seed
php artisan storage:link
```

Migrations create application tables and the tables used by database sessions, cache, and queues. The default [DatabaseSeeder](database/seeders/DatabaseSeeder.php) loads roles, users, billing reference data, workflow scenarios, buildings, rooms/images, customer financial histories, maintenance, and contact inquiries. It can update existing demo records, so it is not a production provisioning command.

For local login, use a demo account defined in [UserSeeder](database/seeders/UserSeeder.php), which declares its initial fixture password. Existing account passwords are preserved on repeat seeding. Only `super_admin`, `admin`, and `customer` are login roles. The 25 seeded inactive staff records use employment roles (`manager`, `accountant`, `receptionist`, `security_guard`, `cleaner`, `maintenance_staff`, `electrician`, `plumber`) and cannot log in.

`storage:link` exposes `storage/app/public` under `public/storage` for uploaded and seeded media. Do not use `migrate:fresh`, `db:wipe`, or truncation commands for ordinary setup: they delete existing data.

## Environment Configuration

Use [.env.example](.env.example) as the complete template. Keep `.env`, generated keys, database passwords, and provider credentials out of Git.

| Variable | Local value or purpose |
| --- | --- |
| `APP_KEY` | Generated once with `php artisan key:generate`; retain it for an existing installation. |
| `APP_URL` | `http://localhost:8000`; also used for generated public file URLs. |
| `APP_ENV` / `APP_DEBUG` | `local` / `true` for local development only. |
| `APP_TIMEZONE` | `Asia/Yangon` in the template. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection described above. |
| `FRONTEND_URL` | `http://localhost:5173`; used in frontend links, including password resets. |
| `CORS_ALLOWED_ORIGINS` | Exact browser origins, comma-separated. Template allows `http://localhost:5173,http://localhost:5174`. |
| `CORS_SUPPORTS_CREDENTIALS` | `false` for the current Bearer-token frontend. |
| `SANCTUM_STATEFUL_DOMAINS` | Cookie/stateful auth host list; not a replacement for CORS origins. |
| `SESSION_DRIVER` / `CACHE_STORE` / `QUEUE_CONNECTION` | `database` in the template; migrations must run before these stores are used. |
| `AI_SERVICE_BASE_URL` | `http://127.0.0.1:8001`, with **no `/api` suffix**. |
| `AI_SERVICE_TIMEOUT_SECONDS` | `60` by default. |
| `AI_SERVICE_INTERNAL_KEY` | Optional shared secret; must equal AI `AI_INTERNAL_KEY` when that gate is enabled. |
| `MAIL_MAILER` | Template uses `resend`; use `log` locally to inspect messages in `storage/logs/laravel.log` without delivery. |
| `RESEND_API_KEY` / `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | Resend delivery requires its SDK, credentials, and an allowed sender; see the dependency note below and template sender restrictions. |
| `MAIL_LOGO_URL` | Optional public HTTPS logo URL for email branding. |
| `PASSWORD_RESET_LOCAL_DELIVERY_TEST` | Leave `false` for the normal reset flow; never enable in production. |
| `DOCUMENTS_CHROME_PATH` | Executable Chrome/Chromium path if automatic discovery fails. |
| `NODE_PATH_BIN` | Optional Node executable for enhanced list-PDF conversion. |
| `CHROME_NO_SANDBOX` | Only enable when the runtime requires it, such as the included container configuration. |

The template selects Resend, but `resend/resend-php` is not declared in the current Composer dependencies/lockfile. Credentials alone do not enable that transport. Use `MAIL_MAILER=log` for onboarding; real Resend delivery needs the SDK added through a separately reviewed dependency change. A configured SMTP transport is also available through `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_ENCRYPTION` in `config/mail.php`.

After editing configuration, run `php artisan config:clear` and restart the server and any queue process. Keep browser origins consistent: `localhost` and `127.0.0.1` are different CORS origins.

## Running the Application

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Run a separate queue process for queued work when `QUEUE_CONNECTION=database`:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

Then follow the [AI setup](https://github.com/Hsu-Hlaing-Htet/em_ai#readme) for assistant features and the [frontend setup](https://github.com/Hsu-Hlaing-Htet/em_frontend#readme) for the browser UI.

### Verify API and AI integration

Use the Quick Start HTTP checks, then sign in through the frontend using a seeded login role and confirm dashboard data loads. With FastAPI running:

```bash
curl --fail http://127.0.0.1:8001/health
curl --fail -X POST http://127.0.0.1:8000/api/public/ai/property/ask \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"question":"What rentals are available?","purpose":"rent"}'
```

The property response should contain `data.answer` and `data.properties`; fallback answers do not prove an OpenAI call succeeded. The public proxy is rate-limited. `POST /api/customer/ai/rent/ask` requires a customer Sanctum Bearer token and answers using that customer's context.

Laravel preloads property/customer context before calling FastAPI. This avoids an AI callback to the single-worker local Laravel server while it is waiting for FastAPI. Direct FastAPI calls can instead fetch grounding from Laravel APIs.

The Composer `setup` and `dev` scripts assume a sibling directory named `../frontend`; `dev` also expects `../frontend/node_modules/.bin/concurrently`, which is not declared by the frontend package manifest. Use the explicit commands above for standalone repositories.

## Running Tests

From the backend root after installing development dependencies:

```bash
php artisan config:clear
php artisan test --testsuite=Unit
php artisan test --testsuite=Integration
```

`php artisan test` runs both suites. `composer test` clears configuration and then runs both suites. Focus a run with, for example, `php artisan test tests/Integration/Timebox1`.

- `tests/Unit/Timebox1`–`Timebox5` run without Laravel/database bootstrapping.
- `tests/Integration/Timebox1`–`Timebox5` bootstrap Laravel and use database refreshes. [phpunit.xml](phpunit.xml) defaults to SQLite `:memory:`, array mail/cache/session stores, and synchronous queues. Enable `pdo_sqlite`; do not point direct tests at the development database. The safety guard rejects unconfirmed database targets.
- Testing uses a fake PDF converter and mocked external-service boundaries where specified by the tests. Passing tests do not verify real Chrome rendering, mail delivery, or OpenAI access. Some behavior depends on MySQL semantics; use the isolated runner when checking those paths.

For MySQL integration verification, install Python 3 and make `mysqld`, `mysql`, and `php` available on `PATH`:

```bash
mkdir -p .test-verification
python3 scripts/verify_timebox_tests.py --group integration
```

The runner initializes a new disposable MySQL data directory, verifies its identity, creates `rosewood_test`, runs tests, and shuts the server down. Results remain under `.test-verification/runs/`. Do not manually set `TEST_DATABASE_DISPOSABLE=1` to bypass isolation. See [the test reorganisation report](docs/testing/reorganisation-report.md) for historical coverage, known failures at that audit, and limitations; it is not a current passing-suite guarantee.

Frontend Playwright testing uses the same runner with `--system-suite` through the frontend npm commands. Each invocation creates `rosewood_system_test` on a newly initialized MySQL instance at a free loopback port, verifies the actual server port/data directory, and isolates Laravel storage, public uploads, cache, and logs. Preparation and cleanup reset only that verified disposable database; the normal server on port 3306 is refused. The runner shuts down its MySQL process and removes its data directory while retaining evidence under `.test-verification/runs/`. Follow the [frontend test instructions](https://github.com/Hsu-Hlaing-Htet/em_frontend#running-tests); do not invoke the preparation/cleanup scripts directly.

## Project Structure

```text
app/Http/               Controllers, requests, resources, middleware
app/Models/             Eloquent domain models
app/Services/           Billing, documents, property data, AI proxy
app/Mail/               Mail messages
app/Notifications/      Notification delivery
app/Policies/           Authorization policies
config/                 Application, AI, database, mail, document settings
database/migrations/    Schema changes
database/seeders/        Demo and system-test fixtures
routes/api.php          Public, auth, customer, and admin APIs
routes/web.php          Root JSON status response
resources/documents/    Print styles and branding
scripts/                Isolated integration and system-test tooling
tests/                  Unit, Integration, and shared Support fixtures
```

## Troubleshooting

| Symptom | Check or resolution |
| --- | --- |
| Composer rejects PHP 8.2 | Install PHP 8.3–8.5 for the locked development dependencies; check the PHP executable used by Composer. Do not ignore platform requirements. |
| `could not find driver` / database connection error | Enable `pdo_mysql` (or `pdo_sqlite` for default tests), start MySQL, and check the database, port, credentials, and permissions. |
| Missing cache/session table | Run `php artisan migrate` against the intended development database after clearing cached configuration. |
| Missing app key | Run `php artisan key:generate` on a new local installation. |
| Images return 404 | Run `php artisan storage:link`; confirm `APP_URL` matches the server and seeded/uploaded files exist. If a link already exists, inspect its target before replacing it. |
| Browser CORS error | Add the exact frontend origin to `CORS_ALLOWED_ORIGINS`, clear configuration, and restart Laravel. |
| AI returns 502 / 403 | Check FastAPI `/health`, `AI_SERVICE_BASE_URL`, and the matching internal keys; inspect AI logs for backend/provider failures. |
| Email is not delivered | `log` intentionally does not deliver. For real delivery, configure the transport/sender and run a worker for queued messages. |
| PDF export fails | Install Chrome/Chromium, set `DOCUMENTS_CHROME_PATH` if needed, and check executable/temp-directory permissions. |
| Integration tests refuse the database | Clear cached configuration and remove conflicting exported `DB_*` settings. Use SQLite memory or the isolated runner; never disable the guard. |

## Deployment Notes

[Dockerfile](Dockerfile) builds PHP 8.2/Apache with Chromium; [render.yaml](render.yaml) configures a web service with `/up` as its health check. Supply deployment secrets through the host, use `APP_DEBUG=false`, configure exact frontend origins, and provision the external database separately. Database queues also need a worker process.

The included [docker-compose.yml](docker-compose.yml) and [.env.docker.example](.env.docker.example) default to PostgreSQL, while the Dockerfile installs `pdo_mysql` and does not install `pdo_pgsql`. Treat those defaults as configuration drift: they are not a working PostgreSQL Quick Start. The documented local path is MySQL.

## Related Repositories

- [Backend — em_backend](https://github.com/Hsu-Hlaing-Htet/em_backend)
- [Frontend — em_frontend](https://github.com/Hsu-Hlaing-Htet/em_frontend)
- [AI — em_ai](https://github.com/Hsu-Hlaing-Htet/em_ai)

Developed by [Hsu-Hlaing-Htet](https://github.com/Hsu-Hlaing-Htet).
