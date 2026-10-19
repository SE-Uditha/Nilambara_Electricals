# Invoice management

Step 7 delivers the invoice workflow on the existing `invoices`, `invoice_jobs`, and `invoice_items` schema. An invoice is for one registered customer and may contain multiple eligible repair jobs belonging to that same customer.

## Workflow

1. A cashier, manager, or administrator with invoice-create access selects an active customer and completed jobs.
2. The server locks and rechecks selected jobs, calculates every line and total from stored business records, snapshots the customer/job/pricing data, and creates the draft and its history in one transaction.
3. A user with issue permission issues the draft. The immutable invoice snapshot, audit record, history event, and finalized state are saved atomically.
4. Authorized users can inspect job-wise lines, allocation-derived balances, print/PDF history, and authorized print/export actions.
5. Cancellation requires a reason and appropriate permission. It preserves the invoice number and job links and is blocked when payment allocations exist.

Invoice numbers use `BUSINESS_INVOICE_NUMBER_PREFIX` (default `INV-`) plus the database-generated invoice ID. Gaps are possible after rolled-back transactions; numbers are unique and are never reused for canceled invoices.

## Pricing and payment boundaries

Only `Repair Completed` and `Ready for Collection` jobs can be invoiced. If there is an issued and approved quotation, its current total must match the repair job's recorded total; otherwise the operation is rejected. Quotation estimates and actual job charges are not silently reconciled or changed.

For jobs without an approved quotation, labor and parts charges are recalculated server-side. Issued job-part usage is itemized only when the saved quantities/unit prices reconcile exactly to the recorded parts charge; otherwise the recorded parts total is preserved as a single parts line. Invoice creation never posts stock movement.

The payment-method field is informational. Payment status and balances are derived from existing allocations less allocation releases, including linked job advances. The screen shows current payment as zero; this workflow does not record a receipt, change customer account entries, refund money, or change repair/delivery status. Full payment and credit processing is Step 8.

## Access and documentation

The permission catalog separates invoice view/create/draft update/issue/cancel/print/export. Exact role grants are maintained in `RoleSeeder`; policy checks protect every route and action. Review [multiple-job invoicing](multiple-job-invoicing.md), [invoice printing](invoice-printing.md), and [payment integration](invoice-payment-integration.md) for details.
