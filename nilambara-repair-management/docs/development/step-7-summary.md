# Step 7 summary: invoice management

Step 7 implements invoice drafts, multi-job billing for one customer, approved-quotation price integration, lifecycle history, authorized cancellation, allocation-derived balance display, and A5 print/PDF. It reuses the Step 2 invoice/job/item/payment schema; an additive migration adds only informational `payment_method` and `invoice_history`.

## Delivered

- Database-generated, configurable `INV-` invoice numbers.
- Server-recalculated same-customer invoices with job-wise totals, labor/parts/service item snapshots, approved-quotation item references, and issued job-part usage itemization when the amounts reconcile.
- Draft creation/update with job locks/reservations, immutable issued snapshots, issue/cancel events, general audit records, and cancellation protections.
- Invoice search/filters, customer/job selection using the Step 4 customer search, permission-checked listing/details/actions, collection and delivery metadata without changing repair status.
- Payment status/outstanding summaries from existing net allocations and allocation releases; no Step 8 payment or customer-ledger writes.
- A5 HTML/PDF, duplicate labels, Sinhala fonts, and print/export history.
- English/Sinhala invoice translations and user guides for workflow, multiple jobs, printing, and payment integration.

## Business rules and limits

Only `Repair Completed` and `Ready for Collection` jobs are billable. An issued approved quotation must match the saved job total. Price-change approval is not implemented, so mismatches are rejected. A job remains reserved by its invoice even if the invoice is canceled, as required by the existing unique invoice-job constraint. Current payments, credit collection, payment receipts, refunds/reversals, stock posting, and delivery transitions are not implemented; full payment processing is Step 8.

## Verification

The focused invoice feature tests cover multi-job totals/numbering, customer/job validation, quotation match and mismatch, allocation-derived balances, immutability, cancellation, permissions, A5/PDF output, search, and the Step 4 selector integration. Run `php artisan test --filter=InvoiceManagementTest`, then the full suite, Pint, `npm run build`, view compilation, and route checks from the application directory.

Before deployment, inspect `php artisan migrate:status` and `php artisan migrate --pretend`; apply only the additive invoice workflow migration after confirming the configured database. Never use `migrate:fresh` or create test business records in the operational database.
