# Step 3 summary

Step 3 implements the employee/account/RBAC foundation in the existing Laravel application. It does not implement the Step 4 customer/company module or any deferred repair, sales, inventory, POS, reporting, or backup workflow.

## Delivered

- Additive migration that retains the existing users, employees, roles, permissions, and pivot tables; adds normalized usernames, account status, last-login timestamps, Spatie guard/model columns, and transforms the existing role/permission name data without dropping records.
- Spatie Laravel Permission 8, configured to use the existing pivot tables rather than creating duplicate access-control tables.
- Username/password Fortify authentication, disabled-account checks, session invalidation, login throttling, login/logout auditing, and private first-administrator console bootstrap.
- Employee list, search/filter/pagination, registration, details, edit, linked account creation, account/role management, password reset, and permission overrides.
- Role/permission pages, six exact BRD roles, seeded granular permissions, role-matrix defaults, server-side `can` authorization, navigation filtering, and assigned-technician record policy.
- Audit events for administrative/account operations and automated tests for the core success and denial paths.
- Feature guides: [employee management](../modules/employee-management.md), [authentication](../modules/authentication.md), and [role permissions](../modules/role-permissions.md).

## Apply to the existing local database

First inspect the configured database and migration status; do not use `migrate:fresh`, rollback, or import over existing business tables. In PowerShell from the application directory:

```powershell
$env:PHPRC = (Resolve-Path ..\.tools\php.ini).Path
php artisan config:clear
php artisan migrate:status
php artisan migrate --pretend
php artisan migrate
php artisan db:seed --class=RoleSeeder
```

If the database already has a user but no active administrator, promote that account through the private console command:

```powershell
php artisan app:assign-initial-admin <username>
```

If no account exists, run `php artisan app:create-user` to create the first linked Administrator account. Rebuild assets with `npm run build` if needed, then start the server with `php artisan serve --no-reload` after setting `PHPRC` so the child process loads `pdo_mysql`.

## Verification status

The full test suite passes: 90 passed, 3 skipped (the MySQL-only cases), 1,445 assertions. The focused migration lifecycle test passes 204 assertions on isolated SQLite. Pint formatting, production Vite build, Composer manifest validation, and employee/role route registration all pass.

The configured local database reports the Step 3 access-control migration as pending; `migrate --pretend` was inspected and shows only additive columns/indexes and the existing-row username/role/permission backfills. The migration has not been applied. Apply it only after reviewing the intended database and taking a verified backup. Feature and migration tests use isolated SQLite and do not modify that configured application database.
