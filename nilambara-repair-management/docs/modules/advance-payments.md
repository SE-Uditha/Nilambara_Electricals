# Advance payments

Repair receipt advances use the same posted payment and customer-ledger records as every other advance. A job-linked advance has a `job_advance` allocation; a customer-level advance remains unallocated until an invoice uses it. The advance amount is not posted a second time during application.

## Recording

An active payment receiver may enter an advance from the payment form, choosing a customer and optionally an active repair job. The job must belong to the selected customer and cannot be cancelled. The payment stores method, date, reference, notes, authenticated acting user (in audit history), and active employee receiver. On the repair-receipt form, the advance field is shown only to users with both payment creation and receiving permissions. Other receipt users can still register a job but cannot record tender.

When a receipt creates an authorized advance, job creation, payment, job allocation, customer-ledger entry, receipt snapshot, and audit history share a database transaction. The immutable receipt snapshot contains the payment reference. The repair-job and receipt detail screens display job advance history to users allowed to view payments.

## Applying

At invoice issue, customer-level unallocated advances for that customer are considered oldest-first and allocated only up to each invoice's remaining balance. Existing job-specific advances already reduce the invoice balance through their linked repair-job membership. Any unused customer advance remains available and remains a customer credit. Repeated invoice issue/allocation does not allocate the same net payment value twice.

Invoice balances include net allocations after any releases for refunds/reversals. The original advance payment and ledger credit remain intact; an allocation release records the amount later reapplied or returned.

## Authorization and legacy records

Receptionists do not receive advance permissions by default. They see a restricted-entry notice instead of a tender input. The backend payment service independently checks payment permissions, active employee linkage, customer/job ownership, positive decimal amount, and transaction idempotency.

Existing Step 5 advance records are preserved. Step 8 does not re-number, backfill, or reinterpret historical payment rows. New receipt advances use `PAY-` references and a distinct idempotency key; legacy job references remain unchanged.
