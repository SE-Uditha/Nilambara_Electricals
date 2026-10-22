# Cashier consolidation — proposed changes

Status: implemented and verified in the local MySQL database on 21 October 2026.

## Database accounts before migration

- cashier.demo and receptionist.demo: Cashier, active.
- manager.demo: Manager, active.
- storekeeper.demo: Storekeeper, active.
- administrator.demo and technician.demo: retained unchanged.

## Authorization changes

Cashier receives the union of current Cashier, Manager and Storekeeper grants, including custom grants. Manager and Storekeeper role assignments transfer to Cashier, then the two obsolete role definitions and their pivots are removed. Individual permission denials stay effective. Administrator and Technician grants are preserved. No business tables, records, passwords or employee references are deleted.

Cashier consequently gains job assignment, quotation approval/revision/cancellation, invoice cancellation, payment reversal, financial and credit reports, inventory management/counts/adjustments, suppliers, purchasing/receiving/approval, and customer statements. No new user administration, role administration, system settings or backup powers are added beyond grants already present on the three merged roles.

## Application files changed

- database/seeders/RoleSeeder.php: create only Administrator, Cashier and Technician; assign the union of previous operational default grants to Cashier; never recreate retired roles.
- New MergeOperationsIntoCashier service and migration: transfer existing custom grants and memberships transactionally, retain before/after assignments and grants in the audit log, clear permission cache, safely permit reruns.
- ApprovalAccess.php and config/navigation.php: permit Cashier to access the approval queue, still checking each required permission.
- QuotationController.php and QuotationPolicy.php: allow Cashier with quotations.approve to see pending/draft quotations; preserve permission checks.
- InventoryController.php, JobCardController.php, RepairJobController.php, RepairJobPolicy.php, and QuotationController.php: replace Manager supervisory exceptions with Cashier exceptions for mixed-role accounts; retain Technician-only restrictions otherwise.
- Approval pending text: replace Manager with Cashier.
- Role documentation and affected tests: describe/test the three-role structure and updated access.

## Existing accounts

Confirmed user preference: disable manager.demo and storekeeper.demo. Preserve user IDs, password hashes and historical employee/transaction references. Existing Cashier accounts continue working.

## Recovery and verification

Before applying the migration, save an encrypted snapshot of affected role/permission/membership/account records. Record the original split in audit history. Avoid an automatic rollback that guesses the old split or removes later Cashier grants; recovery uses the captured before state.

Test a disposable MySQL database first: permission union, membership deduplication, custom grants, explicit denials, unchanged credentials and records, rerun idempotence, fresh installations, sidebar visibility, quotation access, purchases, stock workflows, reports, payment reversal and approval access. Keep the existing restriction against deciding one's own approval request; another authorized Cashier or Administrator can review it.

Then apply php artisan migrate to the local database and compare the saved records to verify only intended changes. Confirm no Manager/Storekeeper roles remain, Cashier retains every original operational grant, and protected Administrator/Technician assignments and business records are unchanged.

## Verification results

- Full isolated MySQL suite: 231 tests passed, 3,653 assertions.
- Full migration rollback/remigration succeeded in the disposable database.
- Four concurrent receipt, technician, repair-stock and purchase-receiving checks passed.
- Live migration applied successfully. Cashier has the exact original union of operational grants and memberships; Manager and Storekeeper roles are absent.
- Extra manager.demo/storekeeper.demo logins are disabled. Existing cashier.demo, receptionist.demo, administrator.demo and technician.demo status and credentials are preserved.
- Password hashes and records in 59 other tables matched the encrypted before snapshot exactly.
- Final recovery snapshot: storage/app/private/pre-cashier-consolidation-20261021-203425-ab4a96.json.enc. Keep the existing Laravel APP_KEY to decrypt it.
- Configuration and compiled-view caches cleared.
