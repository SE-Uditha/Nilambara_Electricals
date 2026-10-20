# Step 10 summary: POS

Implemented POS checkout and supporting workflows in the existing Laravel modules.

## Delivered

- Barcode/SKU/product search and session cart with exact decimal totals, discounts guarded by permission, and server-side stock revalidation.
- Transactional cash checkout with tender/change and shared payment/allocation/stock ledgers.
- Pending bank-transfer and cheque sales; manager confirmation posts payment and stock together. Cheque receipt is not represented as clearing.
- Checkout and return idempotency fingerprints to reject changed-data replays.
- POS sale history/details, duplicate-aware print/PDF receipts, payment-history integration, returns, authorized refunds, and operating summaries.
- Resalable returns restore saleable stock; damaged returns do not.
- Permission grants, record policies, navigation, English/Sinhala labels, focused feature tests, and module/database documentation.
- Correct unit-precision validation, locking current stock/return reads, historical available cost, proportional discounted refund ceilings, actor-bound return retries, explicit creation/discount audits, and refund-only reporting periods.

## Verification

- Audit and completion verification: 2026-10-20, PHP 8.5.11, local MySQL-compatible XAMPP MariaDB 10.4.32.
- Focused disposable MySQL suite: 29 passed, 2,110 assertions (15 POS workflow tests plus relationship and native storage tests).
- Full isolated SQLite suite: 181 passed, 3 skipped, 2,276 assertions. Skips require native MySQL storage; those checks passed in the focused MySQL run.
- Native MySQL rollback and remigration: passed in the disposable database.
- Separate-process MySQL races: one winner when two cashiers each request four of five units; distinct sale numbers for simultaneous independent sales; one sale/payment for simultaneous identical retries.
- Pint: focused changed PHP files pass.
- Vite production build: passed.

The existing POS/return tables were inspected before applying `2026_10_19_190000_add_step10_pos_checkout_support` to local `nilambara_repair_management`. The migration succeeded. Original column values and records were fingerprinted before/after across 14 core business/authentication tables, including shared stock and payment ledgers; all were preserved. Existing POS sales numbered zero before deployment, and no demo sales were inserted. POS grants were added without removing existing assignments or explicit denials.

Reproduce native verification with `php scripts/verify-mysql.php --pos`, using the repository PHP configuration. The helper creates and removes only its own fresh random local database. `scripts/verify-pos-concurrency.php` refuses an ordinary application database.

Physical USB scanners/printers and visual browser layout were not tested. Scanner-style POST requests, receipt HTML and PDF responses were exercised by feature tests. Hardware/user acceptance remains a shop-side check.

The wider MySQL suite also exposed seven failures outside POS (168 passed, one skipped in that run): dashboard aggregate SQL under strict grouping; invoice numbering and an invoice test querying a nonexistent stock column; quotation numbering; repair-job numbering. They remain outside Step 10. This evidence does not certify the entire application on MySQL. See local `storage/logs/step10-mysql-verification.log` for details.

## Shared records

POS reuses `pos_sales`, `pos_sale_items`, `stock_movements`, `payments`, `payment_allocations`, `stock_returns`, `stock_return_items`, `payment_reversals`, `document_sequences`, print history, and audit history. No parallel inventory or payment ledger was introduced.

Step 9's wider inventory/purchasing capability remains separate. Work stopped at Step 10; no new Step 11 functionality was developed.
