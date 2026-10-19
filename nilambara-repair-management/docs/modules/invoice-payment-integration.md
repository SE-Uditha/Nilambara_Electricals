# Invoice payment integration

Step 7 does not implement payment receipt or collection. It only displays a derived view of the existing payment/allocation records to keep invoice detail and outstanding filters consistent without adding a second balance store.

## Read-only summary

- Invoice allocations contribute their net amount after allocation-release rows.
- Job-advance allocations contribute when the advance is linked to a job on that invoice.
- `current_payment` is always `0.00` because this workflow accepts no tender.
- `paid`, `outstanding`, and payment status are calculated from the invoice total and net allocations. They are not copied into mutable invoice columns.
- The selected `payment_method` is informational and does not represent a receipt, settlement, or account posting.

## Step 8 responsibilities

Payment workflows must retain the existing payment, allocation, release, reversal, and customer-account ledger records as the authoritative sources. Receipt, allocation/reapplication, overpayment, refund, cancellation-with-payment, receipt printing, and audit permissions still require explicit transaction rules and tests. Do not add payment writes to invoice issue or infer a payment from cash terms, invoice method, or a zero-current-payment display.
