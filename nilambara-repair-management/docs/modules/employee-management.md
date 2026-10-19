# Employee management

Step 3 adds employee records and administrator-managed login accounts to the existing application. Employee information remains in `employees`; credentials and usernames remain in Laravel's `users` table.

## Employee and account data

The existing `employees` record contains:

| Field | Use |
| --- | --- |
| `employee_code` | Generated immutable `EMP-` plus ULID identifier, protected by the existing unique index. |
| `name`, `address`, `mobile_number` | Employee contact and identity information. |
| `job_title`, `department`, `date_joined` | Employment details. |
| `is_active` | Employment state; inactive employees cannot use a linked login. |
| `user_id` | Nullable unique FK to `users.id`; allows staff without accounts and prevents duplicate links. |

The additive access migration extends `users` with a unique normalized `username`, `is_active`, and `last_login_at`; existing email values remain intact and the email field becomes nullable. Existing accounts receive a username from the local part of their email address (with a collision suffix where necessary). Passwords stay in Laravel's hashed `users.password` field and are never copied to employees or audit payloads.

An employee can exist without a login. Account creation during employee registration requires `employees.create`, `users.create`, and `roles.manage`; the role, username, and strong password are validated together. Employee and user records, role assignment, and audit records are written in one transaction. Employee IDs are immutable and protected by the database uniqueness constraint.

## Screens and routes

| Route name | Capability |
| --- | --- |
| `employees.index` | Search/filter employees by identifier, name, mobile, role, and status; paginated. |
| `employees.create`, `employees.store` | Register an employee and optionally create a linked account. |
| `employees.show` | Employee details, account state, role, permissions link, last login, and recent actions. |
| `employees.edit`, `employees.update` | Edit employee details and employment state; ID cannot change. |
| `admin.users.index`, `admin.users.show` | View accounts, update username/name/email, role, status, password, and overrides. |

The employee and user account are deactivated rather than deleted. Deactivation disables the linked login and invalidates database sessions. Historical employee foreign keys remain intact. Re-enabling an employment record does not implicitly re-enable a separately disabled account; an authorized administrator must explicitly enable it.

Initial account bootstrap is private and console-only:

- `php artisan app:create-user` creates the first linked Administrator account only when no active administrator exists.
- `php artisan app:assign-initial-admin <username>` assigns Administrator to an existing enabled account after explicit confirmation, only if no active administrator exists.
- Additional accounts are created from the authorized employee form, not public registration or an unrestricted console command.

## Audit events

Employee creation/update, linked account creation, role changes, account enable/disable, password reset, permission overrides, and sign-in/sign-out outcomes are recorded in the existing append-only `audit_logs` table. Audit details exclude passwords and password hashes.

## Boundaries

This module does not implement customer CRUD, repair receipt/job workflows, invoice/payment processing, stock operations, POS, reports, or real backup/restore actions. The sidebar labels for those unimplemented capabilities remain disabled placeholders and are permission-filtered.
