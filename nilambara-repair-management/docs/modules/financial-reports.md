# Financial reports

Collections and advances use the shared payments and reversals. A receipt is positive on `paid_at`; a reversal is negative on `reversed_at`. Tendered cash is not income, allocations are not additional receipts, and an advance is never counted twice as cash. Cash collection/summary restricts the method to cash.

Customer/supplier outstanding use their existing signed account-entry ledgers. Opening balances come from opening entries, not a second addition of the master-record opening value. End-date balance filters include entries before the range start. Account statements retain preceding entries in the running balance while displaying the requested range. Supplier payments, reversals and adjustments affect the same ledger.

Overdue invoices require a real due date and current outstanding status from `InvoiceBalanceService`. No invented overdue threshold is used. Invoice totals and outstanding amounts are different figures. Current allocation/reversal state is used; the overdue report is not a historical ageing reconstruction.

Purchases and expenses use finalized records. Their CRUD workflows are not introduced by Step 11. Profit displays repair/POS revenue, discounts, refunds, known parts/labour costs and recorded expenses, but is explicitly incomplete because approved inventory valuation and expense-recognition policies are absent. Purchases are not subtracted again as both acquisitions and consumption. This is not certified net profit.
