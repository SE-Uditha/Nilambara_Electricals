# Step 2 database foundation

For current additive POS and administration/reporting work, see the [Step 10](../development/step-10-summary.md) and [Step 11](../development/step-11-summary.md) results. This page retains the original foundation's historical scope. Step 11 extends backup history metadata without creating another financial or stock ledger.

This guide describes the original Step 2 database foundation: 58 entity tables, 58 module models, 16 migrations, enums, constraints, relationships, and database tests. Steps 3-8 add employee/account/RBAC, customer, repair, quotation, invoice, and payment workflows to the existing schema. Step 7 adds `invoice_history` and an informational invoice `payment_method`; Step 8 reuses the existing payment, allocation, release, reversal, account-ledger, and print-history tables without adding another financial ledger. Stock deduction, POS checkout, reports, and backup/restore execution remain deferred.

Read the [schema overview](schema.md), [complete ERD](erd.md), [data dictionary](data-dictionary.md), [relationship rules](relationship-rules.md), [requirements traceability](requirements-traceability.md), [open decisions](open-decisions.md), [verification results](verification.md), and [file summary](change-summary.md).

## MySQL and phpMyAdmin

Laravel uses the `mysql` connection. The database is **nilambara_repair_management**. phpMyAdmin is the administration interface for this same server; it is not another database engine and needs no Laravel package. The current XAMPP server reports MariaDB 10.4.32 and phpMyAdmin 5.2.1. Native MySQL deployment requires MySQL 8.0.16 or newer for enforced CHECK constraints; a separate MySQL 8 instance has not been tested here.

Start MySQL and Apache in the XAMPP Control Panel. Open **http://localhost/phpmyadmin/** and select **nilambara_repair_management** from the database tree. Refresh the tree after running migrations. The Structure tab lists the application tables, including retained Laravel infrastructure and the migration repository; later workflow steps add only documented tables/columns and do not duplicate the Step 2 invoice or payment tables. Table Structure pages show primary keys, unique indexes, foreign keys, and InnoDB/utf8mb4 metadata.

All application tables are created by Laravel migrations. Do not recreate them in phpMyAdmin, import a conflicting SQL dump, or alter business/financial rows manually as a substitute for future validated workflows. phpMyAdmin may be used for schema inspection, queries, exports, and administration. The Mermaid ERD remains the complete diagram even if phpMyAdmin Designer configuration storage is not enabled.

## Local connection

The ignored `.env` contains the operator's requested local account. These are local development examples:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nilambara_repair_management
DB_USERNAME=root
DB_PASSWORD=
```

The database container itself may be created once in phpMyAdmin or through MySQL administration with utf8mb4 collation. Application tables must then come from migrations. Never commit `.env`, passwords, application keys, backup secrets, or production connection URLs.

## Exact commands in this workspace

Use the installed PHP 8.5 CLI. XAMPP's bundled PHP 8.2 cannot run this Laravel 13 dependency set. The project-local PHP configuration enables the existing PHP 8.5 extensions; it does not change XAMPP or system configuration.

```powershell
cd "D:\xampp\htdocs\Nilambara_Electricals\nilambara-repair-management"
$env:PHPRC = (Resolve-Path ..\.tools\php.ini).Path
php artisan config:clear
php artisan migrate:status
php artisan migrate --pretend
php artisan migrate
php artisan db:seed --class=RoleSeeder
```

The seeder idempotently creates six roles, the permission catalog, and their baseline grants. It does not create accounts. It keeps the Administrator role fully granted and only initializes the other role grants when they have no existing assignments. Never regenerate an existing application key.

Inspect an existing table and its migration state before changing its design. An existing conflicting table is an error to investigate; migrations do not silently drop or replace it. Step 3's migration is additive to the existing schema: it keeps the role/permission pivots and old codes, preserves prior labels, backfills usernames, and maps those pivots to Spatie. Review `migrate --pretend` output and take a verified backup before applying it to an existing populated database. Do not run rollback on the application database.

## Verification commands

The default test configuration uses SQLite in memory with FK enforcement. It does not target the local application database:

```powershell
php artisan test
php vendor\bin\pint --test
```

Three native storage checks require a MySQL-compatible server and are skipped on SQLite. To run the complete suite, rollback, and remigration against that server safely:

```powershell
php scripts\verify-mysql.php
```

This script creates a randomized `nilambara_step2_test_...` database, refuses to reuse an existing one, passes its connection only to child verification processes, tests the schema, and drops only the disposable database it created. The configured local account needs database creation/deletion permission for this test helper. The application database and `.env` are never changed by the helper.

## Reconciliation and decisions

Current stock is the signed movement sum. Customer and supplier balances are their canonical signed ledger sums. Payment allocations describe application of an already-recorded receipt; releasing an advance allocation and reapplying it must not create another payment or account credit. Invoice payment status is derived from net allocations, not a separately editable paid flag.

Composite FKs enforce ownership/source consistency, but SQL row CHECKs cannot enforce aggregate allocation ceilings, prevent overselling across concurrent transactions, reconcile document headers to all source lines, or authorize refunds. Future operations must use transactions, stable idempotency keys, row locks, and the relationship rules. Cost valuation, fractional units, adjustment/refund policy, and other unresolved BRD choices remain explicit proposals in the decision register.

Step 2 schema evidence is historical; current Step 3 behavior is described in the [employee guide](../modules/employee-management.md), [authentication guide](../modules/authentication.md), and [RBAC guide](../modules/role-permissions.md). Step 4 customer behavior is documented in the [customer management guide](../modules/customer-management.md). The next planned phase is Step 5 repair receipt and job registration.
