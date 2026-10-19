# Step 8 summary — payments and credit

## Delivered

- Payment list, invoice payment entry, customer/job advances, payment details, allocation history, credit invoice list/history, and statement pages.
- Cash, bank transfer, and cheque payment methods; separate invoice cash/credit terms; repeated partial settlements; derived invoice/payment balances.
- Transactional payment and ledger posting, `PAY-` database-ID references, idempotency checks, invoice/customer/job row locking, and audit events.
- Step 5 receipt advance integration, payment references on saved receipt snapshots, and job/receipt advance history.
- Partial cash refunds, full accounting reversals, immutable allocation-release history, and restricted reversal permissions.
- A5 payment receipt preview, duplicate marking, PDF export, customer statement print/PDF, and English/Sinhala strings.
- Payment, invoice, and statement feature tests; payment and credit workflow documentation.

## Existing schema and data

Inspection found existing payment, allocation, release, reversal, customer-ledger, invoice-history, and print-history tables sufficient for Step 8. No Step 8 migration was added. Step 8 did not seed operational payments, alter historical payments, or backfill ledger rows. New invoice receivables are posted only when newly issued; historical invoice/account entries are not guessed or reconstructed. Existing Step 5 payments remain unchanged.

## Explicit decisions and limits

- Overpayments are rejected; customer credits remain unallocated until applied.
- Customer-level advances are applied oldest-first when a finalized invoice is issued.
- Invoice payment UI creates one invoice allocation per receipt; no multi-invoice allocation screen is provided.
- Partial refunds are supported. Accounting reversals are full-only and single-use due to the existing unique compensating-ledger relationship.
- A refund requires a recorded method; the application does not claim a bank/cheque settlement happened automatically.
- Default cashier permissions allow receipts and collections but not reversals or receiver impersonation. Only `payments.receive-for-others` permits selecting another active receiver; the acting user remains the audit actor.
- POS checkout, supplier payments, full financial dashboards, and Step 9 stock/purchasing remain out of scope.

## Verification

- `php artisan test --compact` — 153 passed, 3 skipped, 2,043 assertions.
- Targeted `vendor\bin\pint --test` — passed for the payment, credit, invoice, customer-statement, receipt, seeder, and related test files.
- `npm run build` — passed.
- `php artisan view:cache` and `php artisan route:list --path=payments` — passed; compiled views were cleared afterward.
- `php artisan migrate:status --no-ansi` — all listed migrations, including the Step 7 invoice-workflow migration, were already applied.
- `git diff --check` — passed.

Feature tests use isolated SQLite databases and do not alter operational payment records. MySQL row-lock contention still requires a deployment-environment test; no destructive migration command was used.

## Local run

From PowerShell:

```powershell
Set-Location D:\xampp\htdocs\Nilambara_Electricals\nilambara-repair-management
$env:PHPRC = (Resolve-Path ..\.tools\php.ini).Path
php artisan serve
```

Ensure `.env` points to the intended database and that required migrations are applied before starting the app. To run Vite's development server for frontend asset changes, open a second terminal in the application directory and run `npm run dev`.
