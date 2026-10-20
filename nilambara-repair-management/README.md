# Nilambara Repair Management

Laravel foundation for Nilambara Electricals' repair shop management system. The eventual application covers electrical-appliance and motor repair, customer/company records, quotations, billing, credit, spare parts, purchasing, POS, staff, reports, and operational controls.

**Current delivery: Step 11 reporting and administration.** Dashboard, reporting/exports, settings, versioned print layouts, audit viewing and tested private backup/restore software extend the existing application. The Step 11 migration is applied and existing data preserved. Live backup storage and host-task activation await destination approval; Google Drive remains blank for later configuration. See the [Step 11 results and activation status](docs/development/step-11-summary.md).

The supplied [Business Requirements Document](../Repair%20Shop%20Management%20Final.pdf) is the source of truth. See the [BRD coverage map](docs/modules/brd-coverage.md), [architecture overview](docs/architecture/overview.md), [database guide](docs/database/README.md), and [development roadmap](docs/development/roadmap.md).

Executed database checks are recorded in the [Step 2 verification results](docs/database/verification.md). The [Step 1 verification results](docs/development/verification.md) remain as historical foundation evidence.

## Stack and requirements

| Component | Foundation |
| --- | --- |
| Backend | Laravel 13, PHP, Eloquent, Laravel Fortify, Spatie Laravel Permission 8 |
| Frontend | Blade, Livewire 4, Tailwind CSS 4, Alpine.js through Livewire, Vite 8 |
| Runtime database | MySQL connection, InnoDB / `utf8mb4`, phpMyAdmin administration; local XAMPP server is MariaDB 10.4.32 |
| Testing | PHPUnit 12, isolated SQLite plus disposable MySQL-compatible database verification |
| Dependency tools | Composer 2, npm; exact dependency versions are recorded in lockfiles |

Use PHP **8.5** as the recommended and tested target. The resolved dependencies require PHP **8.4 or newer**. Enable the extensions required by Composer, including `mbstring`, `openssl`, `fileinfo`, `curl`, `pdo_mysql`, `dom`, `xml`, and `gd` (required for DomPDF output with the business logo); enable `pdo_sqlite`/`sqlite3` for tests and `zip` for convenient Composer downloads. Use Node.js 22.12 or a compatible newer release, npm, a running MySQL server, and a browser. The PHP process needs write access to `storage/` and `bootstrap/cache/`.

## First installation

The following commands use PowerShell from the directory containing this application. Use PHP 8.5 and load the repository's PHP configuration so Composer, Artisan, and child PHP processes all have the required extensions.

```powershell
cd nilambara-repair-management
$env:PHPRC = (Resolve-Path ..\.tools\php.ini).Path
php ..\.tools\composer.phar install
npm ci
```

On the first setup only, create the local environment file if it does not already exist:

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

Edit `.env` for your MySQL server. These are local examples, not shared production credentials:

```dotenv
APP_NAME="Nilambara Repair Management"
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Colombo
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nilambara_repair_management
DB_USERNAME=root
DB_PASSWORD=
```

Open MySQL using a local account with permission to create this database. For example, `mysql -u root -p` prompts for the local root password. Execute:

```sql
CREATE DATABASE IF NOT EXISTS nilambara_repair_management
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

If the example root account is denied access, put working local credentials in `.env` before continuing. For a brand-new local installation only, generate the application key if `.env` does not already contain one. Never rotate an existing key as part of an upgrade:

```powershell
php artisan key:generate
```

Then run:

```powershell
php artisan config:clear
php artisan migrate:status
php artisan migrate --pretend
php artisan migrate
php artisan db:seed --class=RoleSeeder
npm run build
```

Inspect the pending migration output before applying it to an existing database. Do not run `migrate:fresh`, rollback, or manually recreate tables. `RoleSeeder` idempotently adds the six BRD roles and permission catalog; it creates no demo accounts. If no active administrator exists, create the initial linked Administrator account:

```powershell
php artisan app:create-user
```

If an account already exists but has no Administrator role, use `php artisan app:assign-initial-admin <username>` and confirm the grant. The private commands refuse when an active administrator already exists. Sign in at `http://127.0.0.1:8000/login` with the username (not email) and password.

Preserve an existing `APP_KEY`; generate it only for a new installation. See the [database guide](docs/database/README.md), [Step 3 summary](docs/development/step-3-summary.md), and [role-permission guide](docs/modules/role-permissions.md) for safe migration, account, and access details.

## Daily development

Run the PHP server in one terminal and Vite in another:

```powershell
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

```powershell
npm run dev
```

Alternatively, after setting `PHPRC`, `composer run dev` starts both processes. `--no-reload` is required on this PHP setup so Laravel's child web-server process inherits the extension configuration; restart the server after editing `.env`. A built asset bundle from `npm run build` is sufficient when running only `php artisan serve`. Stop the development processes with `Ctrl+C`.

## Environment and business configuration

`config/business.php` contains the initial business, display, and printing configuration. `.env.example` documents its supported variables:

| Variables | Purpose |
| --- | --- |
| `BUSINESS_NAME`, `BUSINESS_ADDRESS`, `BUSINESS_CONTACT_NUMBER`, `BUSINESS_LOGO` | Business identity and document branding. The logo path must point to a PNG/JPEG file under `public/`. |
| `BUSINESS_CUSTOMER_CODE_PREFIX` | Customer identifier prefix; defaults to `CUS-` and is followed by a unique ULID. |
| `BUSINESS_QUOTATION_NUMBER_PREFIX` | Quotation number prefix; defaults to `QUO-` and is followed by a database-generated id. |
| `BUSINESS_INVOICE_NUMBER_PREFIX` | Invoice number prefix; defaults to `INV-` and is followed by a database-generated id. |
| `BUSINESS_CURRENCY` | Defaults to `LKR`. |
| `APP_TIMEZONE`, `APP_LOCALE`, `APP_FALLBACK_LOCALE` | Defaults to `Asia/Colombo`, `en`, and `en`. |
| `BUSINESS_PAGINATION` | Default page size, initially 25. |
| `BUSINESS_DATE_FORMAT`, `BUSINESS_TIME_FORMAT` | PHP display format strings. |
| `BUSINESS_PRINT_PAPER`, `BUSINESS_PRINT_ORIENTATION` | Initial paper and orientation defaults. |

`lang/en/` and `lang/si/` prepare English and Sinhala translations. Keep new text in translation keys. These directories do not imply that every future business screen or printable template is translated already.

Sessions use Laravel's database session driver, encrypted session payloads, HTTP-only cookies, and SameSite `lax`. The local HTTP example uses `SESSION_SECURE_COOKIE=false`; an HTTPS deployment should set `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`, and its real `APP_URL`. Authentication uses password hashing, login rate limiting, session handling, and CSRF middleware. Public registration is disabled; password-reset, email-verification, and account-settings screens are outside this foundation.

`.env`, local database passwords, application keys, `vendor/`, and `node_modules/` are ignored by Git. Commit dependency lockfiles and `.env.example`. After changing environment values, run `php artisan config:clear` if configuration was cached.

## Useful commands and verification

| Command | Purpose |
| --- | --- |
| `composer install` | Install the locked PHP dependencies. |
| `composer dump-autoload` | Refresh autoloading after namespace changes. |
| `php artisan migrate` | Run pending infrastructure and business migrations against the configured database. |
| `php artisan migrate:status` | Check migration state on that database. |
| `php artisan app:create-user` | Privately bootstrap the first linked Administrator account. |
| `php artisan app:assign-initial-admin <username>` | Promote an existing account during initial setup if no active Administrator exists. |
| `npm ci` | Install the exact npm lockfile. |
| `npm run dev` | Start Vite's development server. |
| `npm run build` | Build production frontend assets. |
| `composer test` | Clear cached configuration and run the automated tests. |
| `php artisan test` | Run PHPUnit through Artisan directly. |
| `php -d extension=gd vendor\bin\phpunit` | Run PHPUnit with GD enabled for PDF tests when GD is available but absent from the active PHP INI. |
| `php scripts/verify-mysql.php` | Run the suite and rollback/remigration in a newly created disposable MySQL-compatible database. |
| `composer lint:check` | Check PHP formatting with Laravel Pint. |
| `composer lint` | Apply PHP formatting. |
| `php artisan route:list --except-vendor` | Inspect application route names and middleware. |
| `php artisan optimize:clear` | Clear generated application caches. |

Run the main foundation checks with:

```powershell
composer test
composer lint:check
npm run build
php artisan route:list --except-vendor
```

`phpunit.xml` selects SQLite `:memory:` plus array/synchronous test drivers, so automated tests do not require the local MySQL database. Install the SQLite PHP extensions to use this test configuration. A passing SQLite test suite does not verify MySQL credentials or MySQL migration execution. Actual setup/build/test results and any unavailable checks are reported with the delivery; the commands above are reproducible instructions, not a claim that a particular workstation passed them.

The retained starter CI templates in `.github/` assume this application is the repository root. This workspace keeps it in the requested `nilambara-repair-management/` subdirectory, so GitHub will not discover its nested workflow automatically. A parent-repository CI setup must move/adapt the workflow and its working directory; the templates work in their current form when the application is the repository root.

## Project structure

```text
app/
  Console/                 Private account bootstrap and future commands
  Http/                    Shared controllers, middleware, requests
  Models/                  Authentication model and retained-record base
  Modules/                 21 business capabilities with 58 entity models
  Providers/               Framework and authentication integration
  Enums/ Events/ Exceptions/ Helpers/ Livewire/ Notifications/
  Policies/ Services/ Repositories/ Traits/  Shared extension points
config/business.php        Business, display, locale, and print defaults
database/                  Infrastructure/business migrations and reference seeders
docs/                      Architecture, ERD, field dictionary, BRD map, roadmap
lang/en/ and lang/si/       Translation resources
resources/views/           Application layouts, components, auth, dashboard
resources/views/modules/   Future module views
routes/web.php             Entry point, protected dashboard, module loader
routes/modules/            Future module routes
tests/Feature/             Authentication and foundation behavior tests
tests/Unit/                Isolated foundation tests
```

Each module reserves `Controllers/`, `Models/`, `Services/`, `Repositories/`, `Requests/`, `Policies/`, `Enums/`, and `Actions/`. Step 2 populates entity models and relationships; Step 3 implements the employee/account and role-permission layers. Other empty layers retain `.gitkeep` files. Classes use the existing `App\Modules\...` PSR-4 namespace.

| Module | Intended responsibility |
| --- | --- |
| Dashboard | Business overview and future statistics. |
| Customers | Step 4 delivers individual/company registration, search, history, and protected ledger statements. |
| RepairReceipts | Item receiving, job identity, advances, and receiving output. |
| RepairJobs | Inspection, repair details, assignment, completion, and delivery. |
| JobCards | A6 technician job-card output. |
| Quotations | Estimates, validity, customer approval, and quotation output. |
| JobTracking | Job search, status filters, and history presentation. |
| Invoices | Same-customer multi-job invoices and A5 invoice output. |
| Payments | Advances, collections, allocations, refunds, and reversals. |
| CreditManagement | Outstanding invoices, credit history, and collections. |
| Inventory | Common repair/POS spare-part catalogue and movement ledger. |
| Suppliers | Supplier registration, terms, and account history. |
| Purchases | GRNs, purchase costs, supplier credit, and stock receipt. |
| POS | Spare-part sales and returns using shared stock/payment rules. |
| Employees | Staff records and controlled employee account management. |
| RolesPermissions | Agreed role/per-user access rules and policy enforcement. |
| Reports | Repair, sales, finance, and inventory reporting/export. |
| AuditLogs | Significant user, status, stock, and financial action history. |
| BackupRestore | Backup scheduling, verification, history, and confirmed restore. |
| PrintTemplates | Shared configurable receipt/card/quotation/invoice templates. |
| Settings | Business identity, locale, display, and document settings. |

Controllers stay small; use Form Requests, Policies, Eloquent relationships, and focused actions/services as business modules are implemented. Use database transactions for related stock/payment changes. Add repositories when their persistence boundary is justified, and never modify framework files under `vendor/`.

## Planned next phases

The next step is **Step 7 — Inventory and Purchasing**. Step 5's receipt/job features and Step 6's quotation/approval behavior are documented in the [module guides](docs/modules/repair-receipts.md), [quotation guides](docs/modules/quotation-management.md), [roadmap](docs/development/roadmap.md), and [open database decisions](docs/database/open-decisions.md). Later phases cover stock/purchasing, invoicing/payments/credit, POS, reports, backup/restore/settings, and end-to-end business acceptance.

Dashboard business metrics remain empty until their authorized reporting queries are delivered. Step 5 repair navigation is active; future module links remain labelled as unavailable and no fabricated sales, credit, or stock data is shown.

## This workspace's optional PHP/Composer helper

The setup workstation has PHP 8.5 available but requires the ignored parent `.tools/php.ini` to enable its extensions. The parent `.tools/composer.phar` provides Composer where no global Composer command exists. If these files are present, run the following from the application directory before the standard commands:

```powershell
$env:PHPRC = (Resolve-Path ..\.tools\php.ini).Path
php --ini
php ..\.tools\composer.phar install
```

Replace `composer test` with `php ..\.tools\composer.phar test`, and `composer lint:check` with `php ..\.tools\composer.phar lint:check` in that environment. `php artisan ...` and npm commands remain the same. This helper file contains a workstation-specific PHP extension path; it is not required on a normally configured PHP installation and is not an application deployment dependency.
