# Database planning: Step 2

## Current boundary

Step 1 configures Laravel's MySQL connection through environment variables and retains the framework's default infrastructure migrations. It intentionally creates no customer, repair, invoice, payment, stock, supplier, role, or other business tables. The test environment uses isolated SQLite in memory; this is not the runtime database choice or a business schema design.

The full schema, ERD, relationships, keys, indexes, and migration plan are the next phase. Use the supplied [BRD](../../../Repair%20Shop%20Management%20Final.pdf), particularly sections 16-17 on physical pages 15-16, together with the [module coverage map](../modules/brd-coverage.md).

## Local MySQL preparation

The example configuration is local development configuration:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nilambara_repair_management
DB_USERNAME=root
DB_PASSWORD=
```

Create the database using an account that has local database-creation privileges:

```sql
CREATE DATABASE IF NOT EXISTS nilambara_repair_management
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Then run `php artisan migrate` from the application directory. Credentials belong in untracked `.env`; do not commit them. A MySQL access-denied result means the example account does not match the local server and must be replaced with working local credentials. Do not substitute SQLite and describe that as a successful MySQL migration.

## Step 2 deliverables

1. A BRD-traceable entity and relationship catalogue with ownership by module.
2. An ERD showing receipt/job/customer relationships, invoice/job links, invoice lines, payment allocations, stock movements, and supplier/purchase relationships.
3. A data dictionary with required/optional fields, types, precision, defaults, lifecycle rules, and English/Sinhala text handling.
4. Unique keys, foreign keys, indexes, deletion/reversal rules, and concurrency strategy.
5. Explicit decisions for monetary amounts, balances, costing/valuation, document numbering, and permissions ambiguities.
6. An implementation sequence for business migrations, factories, seeds, and transactional tests. Business migrations are not created as part of Step 1.

## Required relationship and consistency topics

| Area | BRD design constraint |
| --- | --- |
| Customer and job | A job belongs to a customer and its receiving receipt; customer history includes jobs, invoices, and payments. |
| Receiving and numbering | System identifiers are generated automatically; manual job numbers are unique and immutable after creation. Concurrent receiving must not allocate duplicates. |
| Job history | Record each status change with actor and timestamp. Keep inspection, repair, testing, approval, and delivery facts distinguishable. |
| Quotation | Link to a job, preserve approvals/history, and reuse approved details without re-entry or loss of the original quotation. |
| Invoice and job | An invoice can include several jobs for the same customer, with job-wise totals and correct relational links. Agree whether/how a job may be re-invoiced after cancellation. |
| Payment | One invoice may receive several payments; advances may precede the invoice. Design allocations, rounding, refund/reversal links, and reconciliation before storing balances. |
| Inventory | Repairs and POS share the same stock source. Record date, reference, quantity, actor, and reason for every movement. Prevent duplicate issue/deduction and define cancellation/return behavior. |
| Purchase | Receiving goods increases stock. Supplier credit, payment history, purchase costs, discounts, and other costs remain traceable. |
| Financial history | Preserve important transactions through cancellation/reversal; define which business facts and print snapshots must remain stable after later edits. |
| Atomic operations | Related stock/payment changes must commit together or roll back together; agree transaction boundaries, locking, and retry behavior. |
| Reporting | Reconcile sales, collections, advances, outstanding credit, purchases, stock, costs, and estimated profit to their authoritative records. |
| Access and audit | Separate staff employment, login accounts, roles, permissions, and audit history without assuming that one login automatically has management privileges. |

## Questions to resolve before migration design

- Clarify automatic Job ID versus automatic/manual Job Number wording and numbering/reset rules.
- Confirm invoice finalization, cancellation, edits, reissue, duplicate printing, and allocation of earlier advances to multi-job invoices.
- Agree money precision, allowed quantities/units, rounding, negative balances, overpayments, stock valuation method, and cost snapshots.
- Define POS sale and return detail, supplier payments, damaged stock, physical counts, and whether stock transfers are required.
- Resolve management-only supplier/purchase access (p. 12) against storekeeper purchase permissions (p. 13).
- Define role-to-action and per-user permission precedence, job assignment, and approval authorities.
- Confirm ownership, retention, archival, restore procedure, and scope of audit records.

Record business decisions with the relevant BRD page before implementation. Do not silently convert unresolved wording into a database constraint.
