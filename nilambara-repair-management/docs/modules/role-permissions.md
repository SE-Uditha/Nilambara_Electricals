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
| Technician | Dashboard, assigned job view, inspection, repair details/status/completion, and job-card view. | Job policy restricts a technician to jobs assigned to the linked employee record, even if a URL contains another job ID. |
| Cashier | Dashboard; customer registration/edit/search; repair receiving and job/card viewing/printing; quotation preparation/issue/print; invoice creation/issue; payment/credit collection; repair/sales reports. | No customer statements/opening balances, invoice cancellation, payment reversals, refunds, or settings by default. |
| Storekeeper | Dashboard, inventory view/create/update/issue/adjust/count, purchase view/create/receive. | Supplier administration, purchase approval, and backup restore are withheld by default. |
| Manager | Dashboard, customer view/statements, job view/assignment, quotation view/approval, invoice/payment view, credit view/collection/reports, supplier administration, purchase/receipt/approval, and reports including financial reports. | No system settings, user/role administration, administrator privilege grants, or backup restore by default. |

`rolePermissions` in `RoleSeeder` is the source for initial role grants. Storekeeper supplier creation and purchase approval stay management-only; the Storekeeper retains operational purchase entry and receipt. Cashier financial reversals/cancellations and unspecified approval actions are denied by default.

## Evaluation and administration

- Route authorization uses Laravel `can` middleware. Views filter sidebar items with the same abilities; hiding a link is not the authorization boundary.
- Spatie resolves role and direct permission grants. The configured Gate check applies explicit `deny` overrides before grants; explicit `allow` adds an individual grant, and `inherit` removes an override.
- Individual overrides are managed only by users with `permissions.manage`. A user cannot change their own role or overrides.
- The five seeded roles cannot be deleted from the UI. Administrator role permissions cannot be reduced; other role grants can be configured at **Administration → Roles & Permissions**.
- Disabling or demoting the last active Administrator is rejected. A disabled user is logged out on the next request.
- The RepairJob policy checks technician assignment in addition to the broad `jobs.view` permission. Future job controllers must call the policy for every record action.

## Decisions for owner confirmation

The BRD does not specify detailed approval authority. The restricted default allows Managers and Administrators to approve purchases and quotations; only Managers/Administrators receive purchase approval and job assignment. Confirm whether repair-price changes, refunds, invoice cancellation, and backup restore should have additional approvers before those workflows are built.


## Cashier consolidation (2026-10-21)

Receptionist is no longer a selectable role. Existing receptionist accounts now have Cashier with their usernames, passwords, employment history and individual overrides preserved. Existing Cashier and Receptionist permission grants are combined, including customized grants. The retired role and assignments are recorded in an audit event. The merge is transactional/idempotent and runs via `2026_10_21_100000_merge_receptionist_into_cashier.php`; RoleSeeder also handles legacy installations without recreating the retired role. Automatic rollback does not split merged accounts/permissions because their later intent cannot be inferred. Historical Step 13 PDFs retain their dated six-role snapshot.
