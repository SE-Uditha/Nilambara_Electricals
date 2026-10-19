# Step 4 summary

Step 4 implements the customer/company registration capability in the existing Laravel application. It reuses the existing `customers` table, customer enum/model, related models, and Step 3 permission system. No customer data is seeded, and no new database migration is required.

## Delivered

- Individual/company customer forms with type-specific required names and a company contact person.
- Required address/mobile and validated optional telephone/email fields, notes, registration date, status, and decimal opening balance.
- ULID customer codes with configurable `BUSINESS_CUSTOMER_CODE_PREFIX` (default `CUS-`) and the existing database unique constraint.
- Search by customer ID, individual/company name, or mobile; type/status filtering and 20-record database pagination.
- Customer details, edit, explicit confirmation before deactivation, activation, retained history, and audit entries.
- Reusable `<x-customer-search>` backed by an active-customer JSON search route.
- Financial details and read-only ledger statements protected by `customers.statement`. A nonzero opening balance posts exactly one initial ledger entry; it cannot be directly edited afterward.
- English/Sinhala UI labels and Step 4 guides/roadmap updates.

## Database

The configured database already contained the customer schema; its migration status was inspected and no new migration was necessary. No existing customer records were deleted or changed by implementation. Customer records are created only when an authorized user submits the registration form. Tests use isolated SQLite and do not alter local MySQL data.

## Scope boundary

This step does not implement repair receipt/job creation, quotations, invoices, payments, stock, POS, reports, or backup operations. Detail pages use existing foreign-key-backed job/invoice/payment counts and the actual account ledger, not generated totals. Future job intake must use the active-only customer selector.

## Verification

The customer feature tests pass: 14 passed, 142 assertions. The full suite passes: 104 passed, 3 MySQL-only checks skipped, 1,585 assertions. Pint formatting, production asset build, customer route registration, Blade compilation, and `migrate:status` checks pass; all migrations are applied and no schema change was needed. The local database still contains zero customer records. `RoleSeeder` was run to add the intended Manager statement/view and Cashier view grants without replacing other role grants or creating customer data.
