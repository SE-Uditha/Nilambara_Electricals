# Step 2 database foundation

The existing Laravel application defines the BRD database foundation: 58 new entity tables, 58 module models, 16 additive migrations, enums, constraints, relationships, and database tests. Authentication and the Step 1 frontend remain in place. No business CRUD, payment processing, stock deduction, POS checkout, report, or Step 3 access-control workflow is implemented.

Read the [schema overview](schema.md), [complete ERD](erd.md), [data dictionary](data-dictionary.md), [relationship rules](relationship-rules.md), [requirements traceability](requirements-traceability.md), [open decisions](open-decisions.md), [verification results](verification.md), and [file summary](change-summary.md).

## MySQL and phpMyAdmin

Laravel uses the `mysql` connection. The database is **nilambara_repair_management**. phpMyAdmin is the administration interface for this same server; it is not another database engine and needs no Laravel package. The current XAMPP server reports MariaDB 10.4.32 and phpMyAdmin 5.2.1. Native MySQL deployment requires MySQL 8.0.16 or newer for enforced CHECK constraints; a separate MySQL 8 instance has not been tested here.

Start MySQL and Apache in the XAMPP Control Panel. Open **http://localhost/phpmyadmin/** and select **nilambara_repair_management** from the database tree. Refresh the tree after running migrations. The Structure tab lists the 67 tables, including retained Laravel infrastructure and the migration repository. Table Structure pages show primary keys, unique indexes, foreign keys, and InnoDB/utf8mb4 metadata. Browse displays stored rows; initially only the six reference role names and migration records are populated.

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

The seeder inserts only six reference role names if absent. It does not create accounts, assign permissions, or grant access. It is safe to repeat without replacing customized role metadata. Never regenerate an existing application key.

Inspect an existing table and its migration state before changing its design. An existing conflicting table is an error to investigate; these migrations do not silently drop or replace it. Existing authentication tables are not recreated or modified by the business migrations. Normal migrations add tables/indexes only. Rollback is supported for disposable/test databases and removes the schema it rolls back; do not run it on a populated application database.

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

Step 2 stops at this database foundation. The next stage is Employee Management, Authentication, and Role-Based Access Control; it is not started automatically.
