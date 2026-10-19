# Credit invoice management

Credit management is a view over finalized credit-term invoices and their existing payment allocations. It does not create duplicate invoices or store a mutable outstanding-credit total.

## Credit invoice list

`GET /credit-invoices` supports invoice/customer/mobile search, customer selection, date ranges, and outstanding, partially-paid, paid, or all statuses. Each row derives original total, advance applied, net amount paid, outstanding amount, and payment status from the invoice and payment allocation services. The history page shows receipt dates, payment references, methods, receiving employees, and allocation releases.

Users with `credit.view` may inspect these balances. Collection actions require `credit.collect`, `payments.create`, and `payments.receive`; a credit invoice must also be finalized and have an outstanding balance. The payment service checks `credit.collect` again before posting. Collecting a credit payment allocates it to the existing invoice, adds a ledger payment entry, and creates a payment receipt; it never creates another invoice.

## Repeated settlements

Multiple partial collections are supported. Every request locks the invoice and recalculates the remaining balance before creating a payment. Collection amounts greater than the current outstanding balance, zero/negative amounts, inactive receiving employees, and duplicate idempotency keys with changed transaction data are rejected.

## Boundaries

Credit invoices are the finalized invoices whose terms are `credit`; invoice terms remain distinct from cash, transfer, or cheque tender methods. A cancelled invoice is excluded. General overdue dashboards and date-wise financial reports are future reporting work; this module provides the date filters and transaction history needed by Step 8.
