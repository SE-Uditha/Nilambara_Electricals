# Step 6 summary

Step 6 implements quotation creation, versioning, customer approval/rejection, job-status integration, and quotation print/PDF without beginning Step 7.

## Delivered

- Uses the existing quotation tables with additive workflow/backfill migrations and `quotation_history`.
- Creates unique database-generated quotation numbers with a configurable prefix; saves drafts and supports edit, issue, cancellation, revision, and supersession.
- Calculates decimal line and header totals server-side with Brick Math. Registered spare-part catalog prices are captured as estimate values; quotation creation/approval never changes stock.
- Preserves issued customer/job/inspection and price facts in the quotation snapshot, with append-only quotation, approval, job-status, and audit histories.
- Records decisions only through authorized staff. Issue moves the job to `Waiting for Customer Approval`; approval moves it to `Under Repair`; rejection moves it to `Customer Contact Failed`. A job with any issued quotation is blocked from entering repair unless job approval is approved.
- Provides role-scoped listing/detail/actions, including cashier visibility restricted to issued/approved quotations.
- Adds A4/A5 browser and PDF output, duplicate print history, business-logo support, and Noto Sans Sinhala fonts.

## Scope boundaries

No inventory deduction, invoice, payment, POS, invoice data-copy workflow, customer electronic signature, or notification service is added. Jobs without an issued quotation are not subject to the new approval gate because the schema has no separate approval-required flag. Step 7 is not started.

## Verification

Full isolated PHPUnit suite: 128 passed, 3 skipped (131 total), 1,811 assertions. Focused quotation tests: 11 passed, 108 assertions, including A4/A5 PDF signatures, page sizes, Noto font metadata, approval transitions, revision validity renewal, draft audit history, and no stock/billing side effects.

Laravel Pint passed for the quotation and coupled repair-status code. Blade templates cached and cleared successfully; the Vite production build passed; 15 quotation routes registered; and `git diff --check` passed. Both additive Step 6 migrations, including the legacy-state backfill, are applied to the configured local MySQL database. No customer/job/quotation/demo data was created in that database.

PDF rendering with the bundled PNG business logo requires PHP GD. GD is enabled in this workspace's ignored `.tools/php.ini`; other local/deployed PHP configurations must enable it and restart the web server before using the PDF download action.
