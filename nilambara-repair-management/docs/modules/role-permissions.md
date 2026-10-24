# Roles and permissions

The app uses Spatie Laravel Permission 8 with the existing roles/permissions schema and its existing pivots. The package's own migration is intentionally not published. `role_user`, `permission_role`, and `permission_user` are mapped to Spatie's role, role-permission, and per-model-permission relations; `model_type` was added to the user pivots. No duplicate role or permission tables are created.

The migration preserves the existing identifiers and display data: role `code` remains, the package's role `name` becomes the role slug, and the prior display name is stored in `label`. Permission `code` remains; `name` is the dotted permission identifier and the prior display label is stored in `label`. `guard_name` is `web`. The existing `permission_user.effect` is retained for individual allow/deny overrides.

## Permission catalog

`RoleSeeder` idempotently creates five roles and the full permission catalog. Receptionist was consolidated into Cashier on 2026-10-21 at the owner's request. The merge preserves the union of actual role grants and account assignments; individual overrides remain intact. New module permissions can be added by extending the grouped permission list in that seeder; the authorization model and tables do not need redesign. When adding a permission, also decide which default roles should receive it and deliberately update/backfill their grants: the seeder preserves any existing non-empty customized role matrix and does not silently broaden it.

| Module | Permissions |
| --- | --- |
| Dashboard | `dashboard.view` |
| Customers | `customers.view`, `customers.create`, `customers.update`, `customers.deactivate`, `customers.statement` |
| Repair receipts | `receipts.view`, `receipts.create`, `receipts.print`, `receipts.reprint` |
| Repair jobs | `jobs.view`, `jobs.create`, `jobs.update`, `jobs.assign`, `jobs.inspect`, `jobs.repair`, `jobs.update-status`, `jobs.complete`, `jobs.cancel` |
| Job cards | `job-cards.view`, `job-cards.print` |
| Quotations | `quotations.view`, `quotations.create`, `quotations.update`, `quotations.approve`, `quotations.print` |
| Invoices | `invoices.view`, `invoices.create`, `invoices.print`, `invoices.cancel` |
| Payments | `payments.view`, `payments.create`, `payments.receive`, `payments.reverse` |
| Credit | `credit.view`, `credit.collect`, `credit.reports` |
| Inventory | `inventory.view`, `inventory.create`, `inventory.update`, `inventory.issue`, `inventory.adjust`, `inventory.stock-count` |
| Suppliers | `suppliers.view`, `suppliers.create`, `suppliers.update` |
| Purchases | `purchases.view`, `purchases.create`, `purchases.receive`, `purchases.approve` |
| Employees | `employees.view`, `employees.create`, `employees.update`, `employees.deactivate` |
| User accounts | `users.view`, `users.create`, `users.update`, `users.disable` |
| Roles and permissions | `roles.view`, `roles.manage`, `permissions.manage` |
| Reports | `reports.view`, `reports.export`, `reports.financial` |
| Settings | `settings.view`, `settings.update` |
| Backup/restore | `backups.view`, `backups.create`, `backups.restore` |
| Audit | `audit.view` |

## Default role matrix

| Role | Default access | Important restrictions |
| --- | --- | --- |
| Administrator | Every catalog permission. The role always receives all current permissions. | Cannot remove its final essential administration capabilities; an Administrator account cannot receive individual permission overrides. |
| Cashier | Customer and repair-job intake and viewing (create and view only), receipts, job assignment, inspection, repair notes/status/completion, quotation preparation/approval, invoicing/cancellation, collection/refunds/reversals, customer statements, inventory, suppliers, purchasing/receiving/approval and financial/stock reports. | No direct editing, activation or deactivation of customers or repair-job details (`customers.update`, `customers.deactivate`, `jobs.update` are Administrator-only). No user/role administration, settings or backup restore by default. Explicit permission denials still apply; users cannot decide their own manual approval requests. |

`rolePermissions` in `RoleSeeder` defines the two current roles: Administrator and Cashier. Cashier combines the previous cashier, manager, storekeeper and technician defaults.

## Evaluation and administration

- Route authorization uses Laravel `can` middleware. Views filter sidebar items with the same abilities; hiding a link is not the authorization boundary.
- Spatie resolves role and direct permission grants. The configured Gate check applies explicit `deny` overrides before grants; explicit `allow` adds an individual grant, and `inherit` removes an override.
- Individual overrides are managed only by users with `permissions.manage`. A user cannot change their own role or overrides.
- The two seeded roles cannot be deleted from the UI. Administrator role permissions cannot be reduced; other role grants can be configured at **Administration → Roles & Permissions**.
- Disabling or demoting the last active Administrator is rejected. A disabled user is logged out on the next request.
- RepairJob policies enforce the permission for each action. Cashiers can work across repair assignments; an employee assignment no longer restricts account access.

## Decisions for owner confirmation

Cashiers now perform operational management and stock duties. Administrators retain system administration. Manual approval requests still require a different authorized Cashier or Administrator to decide them.


## Edit approval workflow (2026-10-24)

Administrators have full CRUD on customers and repair jobs. Users who can view but not edit (Cashier) see **Request edit** instead of **Edit**. The form requires a reason and creates a pending approval of kind `customer_update` or `job_update` holding the current and proposed values. Only an administrator (holder of `customers.update` / `jobs.update`) can approve; approval applies the change through the normal service (with audit logging) unless the record changed since the request, in which case it is blocked. Rejection leaves the record untouched. Migration `2026_10_24_100000_revoke_cashier_customer_update.php` removes `customers.update` from existing Cashier roles.

**Repair job deletion (2026-10-25).** The `jobs.delete` permission is held only by Administrators (migration `2026_10_25_100000_add_jobs_delete_permission.php`). Administrators see **Delete job** on the job page; Cashiers see **Request deletion** with a mandatory reason, which creates a `job_delete` approval. Approval by an administrator deletes the job together with its receipt, job card, inspections, status history and custom charges, and writes a `repair_job.deleted` audit entry holding a snapshot. A job that has an invoice, payment allocation, spare-part usage, stock return, quotation or customer approval record cannot be deleted (cancel it instead).

## Cashier consolidation (2026-10-21)

Receptionist is no longer a selectable role. Existing receptionist accounts now have Cashier with their usernames, passwords, employment history and individual overrides preserved. Existing Cashier and Receptionist permission grants are combined, including customized grants. The retired role and assignments are recorded in an audit event. The merge is transactional/idempotent and runs via `2026_10_21_100000_merge_receptionist_into_cashier.php`; RoleSeeder also handles legacy installations without recreating the retired role. Automatic rollback does not split merged accounts/permissions because their later intent cannot be inferred. Historical Step 13 PDFs retain their dated six-role snapshot.

## Operational role consolidation (2026-10-21)

Manager and Storekeeper grants and memberships migrate into Cashier via `2026_10_21_190000_merge_operations_into_cashier.php`. Extra operational logins are disabled, with existing Cashier, Administrator and Technician accounts protected. User IDs, password hashes, employees, transaction histories and individual overrides remain unchanged. Retired roles and original grants/memberships/account states are recorded in audit events; the encrypted pre-migration snapshot supports recovery. Historical PDFs remain dated evidence.

## Technician role retirement (2026-10-21)

Only Administrator and Cashier are selectable roles. Cashier inherits the former Technician repair grants as well as all existing operational grants. A separate migration transfers customized Technician permissions and memberships, disables extra Technician-only logins and preserves existing Cashier/Administrator accounts. Password hashes, employee IDs, repairs, assignments and history remain intact.

Repair assignment now selects an active employee, including employees with no login or a disabled login. The assignment field records who performs the repair; it no longer requires a Technician login role. Inactive employees cannot receive new assignments. Individual permission denials, active-login checks and the manual self-approval restriction remain effective.

Verification: 232 isolated MySQL tests passed (3,666 assertions), followed by complete rollback/remigration and four concurrency checks. Two focused employee-assignment and retirement tests also passed (28 assertions). The live migration applied successfully; exact original permission/membership union, password hashes and records in 59 other tables were verified against `storage/app/private/pre-technician-consolidation-20261021-204157-9d5c33.json.enc`. Keep the existing APP_KEY for recovery. The technician.demo login is disabled; Cashier and Administrator logins are preserved.
