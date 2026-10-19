# Payment refunds and reversals

Original payment and allocation rows are never edited or deleted. Authorized users post a separate `payment_reversals` record and one or more `payment_allocation_releases`; the payment balance and invoice outstanding amount are then derived from the net records.

## Refund versus accounting reversal

- **Refund:** records money actually returned, requires a refund method, permits partial amounts, writes a positive `refund` ledger entry, and releases allocated value only when the refund consumes value previously applied to invoices/jobs.
- **Accounting reversal:** compensates a payment entry without claiming money was physically returned. It is restricted to one full reversal of the remaining payment and is disallowed after an earlier refund/reversal because the existing ledger permits one compensation per original payment entry. It writes a linked compensating account entry.

Both types require a reason, authorized `payments.reverse` permission, actor identity, timestamp, idempotency key, and audit event. Reversal history is visible on the payment detail page. Allocation releases record which invoice/job allocation was restored.

Partial refunds are supported; partial accounting reversals are not. Refunds cannot exceed the unreversed payment amount. POS payments are excluded from this workflow. A cheque/bank settlement status is not inferred or changed by recording a payment or refund.

## Verification notes

Feature tests verify partial refund allocation release, full accounting reversal, invoice balance restoration, ledger compensation, audit logging, and role restrictions using the isolated SQLite test database. Production MySQL concurrency and external refund settlement must be verified operationally; no genuine refund has been recorded by tests.
