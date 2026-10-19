# Payment management

Step 8 posts invoice payments and advances through the existing `payments`, `payment_allocations`, `payment_reversals`, `payment_allocation_releases`, and `customer_account_entries` tables. It adds no duplicate ledger or payment schema. Posted payments and allocations are append-only; a payment's available amount and invoice's paid status are derived from allocations, releases, and reversals.

## Payment lifecycle

1. An authorized active employee submits an invoice payment or customer/job advance.
2. The application validates the customer, invoice/job state, amount, method, receiver, and idempotency key.
3. A database transaction locks the invoice or customer and rechecks the idempotency key and current balance.
4. A payment row receives a unique `PAY-` reference based on its database-generated ID. It is allocated, posted to the customer ledger, and audited atomically.
5. A receipt can be previewed, printed, reprinted as a marked duplicate, or exported to A5 PDF.

Cash, bank transfer, and cheque are payment methods. Invoice terms (`cash` or `credit`) are separate. A credit invoice collection is still a payment allocation against its existing invoice. Partial receipts can be repeated while the invoice has a balance; overpayments are rejected.

`payments.receive` requires both `payments.create` and `payments.receive`. Cashiers and managers can receive payments; cashier permissions do not include refunds/reversals. `payments.receive-for-others` is reserved for the administrator by the default seeder. Without it, the receiver is always the signed-in user's active employee record. With it, the acting user remains auditable while a different active employee can be selected as the receiver.

## Balances and allocations

- Payment available amount = payment amount - net allocations - reversals.
- Net allocation = original allocation - appended allocation releases.
- Invoice paid amount includes invoice allocations and eligible job advances for linked repair jobs; outstanding is clamped at zero.
- Invoice status is derived as unpaid, partially paid, or paid. Cancelled invoices are not included in credit lists.
- A customer-level unallocated advance is applied oldest-first to available finalized-invoice balances when an invoice is issued. Applied value is an allocation, not another cash receipt.
- Payment entry through the UI applies to one invoice at a time. The shared allocation service supports subsequent allocations, but there is no multi-invoice allocation form.

Amounts use `Brick\Math\BigDecimal`. Invoice rows are locked during collection; payment rows are locked during allocation/reversal. Unique idempotency constraints protect repeated submissions. MySQL multi-user contention still needs deployment-database verification.

## Routes and permissions

- `GET /payments`: payment search, filters, and pagination (`payments.view`).
- `GET /payments/create`, `POST /payments`: invoice payment or advance (`payments.create`, `payments.receive`).
- `GET /payments/{payment}`: payment and allocation/reversal history (`payments.view`).
- `POST /payments/{payment}/reverse`: authorized refund or accounting reversal (`payments.reverse`).
- `/payments/{payment}/print`, `/duplicate`, `/pdf`: print and export permissions.
- `/credit-invoices`: credit invoice balances and collection history (`credit.view`).

Payment receipt output uses current business identity/logo configuration and the latest active payment print template. It uses actual saved transactions only; output history is recorded.

## Data and boundaries

No Step 8 migration was needed after inspecting the existing schema. No historical transaction was backfilled or altered. Actual refund records require an explicit refund method; refund and accounting reversal have distinct ledger effects. POS payments, supplier payments, overpayment credits, financial dashboard reports, and Step 9 stock/purchasing are out of scope.

## Validation

Run focused payments tests with `php artisan test --filter=PaymentManagementTest` and invoice regressions with `php artisan test --filter=InvoiceManagementTest`. SQLite feature tests verify the workflow and constraints but do not replace a MySQL concurrent-payment test.
