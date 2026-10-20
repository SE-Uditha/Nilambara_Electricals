# Cashier / Receptionist consolidation

Implemented and applied on 21 October 2026 at the owner's request. Cashier is the sole role for customer registration, repair intake, quotation preparation, billing, collections and POS. The other four roles remain Administrator, Technician, Storekeeper and Manager.

The operational migration combined the actual permission sets into 36 Cashier grants and transferred the former Receptionist assignment. Both existing front-desk accounts now have Cashier; neither user was deleted or renamed. Passwords, employee records, other roles and per-user permission overrides are preserved. Explicit denials still take precedence. Original legacy grants and assignment metadata remain in the `roles.receptionist_merged_into_cashier` audit event.

No new management permission was introduced beyond the union of the existing roles. Fresh Cashier defaults include repair intake and billing/POS, while assignment/approval, refunds/reversals and account/settings administration remain separately restricted. RoleSeeder creates five roles, merges a legacy installation idempotently and does not recreate Receptionist. The data migration's down method deliberately keeps the merged state: later account/grant intent cannot be split automatically from current data.

Verification:

- Four new focused cases passed (55 assertions) before operational changes: five-role defaults and both workflows; custom grants/duplicate assignments/other roles/explicit denials and credential preservation; repeated legacy seeding; legacy installation without Cashier.
- Full disposable MySQL run: **222 passed, 3620 assertions**, 184.92 seconds. Migration rollback/remigration on that disposable database, POS/invoice/payment/shared-stock/receipt/GRN native concurrency and isolated real dump/restore also passed. Evidence: `storage/logs/cashier-role-consolidation-mysql.log`. Only its own randomized database was removed.
- Only `2026_10_21_100000_merge_receptionist_into_cashier.php` was applied to the operational database. Read-only before/after verification confirmed the exact permission union, two Cashier assignments, five remaining roles and retained audit evidence.
- Row counts and SHA-256 fingerprints matched for users, employees, customers, repair jobs/receipts, quotations, invoices, payments, stock movements, purchases, suppliers, POS sales, individual overrides and permission definitions. Raw record/password values were not written into the verification evidence. Evidence: `storage/logs/cashier-role-consolidation-before.json` and `cashier-role-consolidation-after.json`.
- Existing customer, intake, employee and dashboard authorization tests now use Cashier. Receptionist references remain only where needed to migrate/test old data or preserve dated historical documentation.

Refresh the application to see the consolidated sidebar/role choices. Existing logins continue using their existing usernames and passwords. Step 13 PDFs are historical six-role snapshots; current role guidance is `docs/modules/role-permissions.md`. No production asset build is needed for this role change.
