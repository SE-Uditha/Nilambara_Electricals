# Multiple-job invoicing

One invoice may combine several repair jobs only when all selected jobs belong to the same active customer. The server—not client-provided totals—recalculates job amounts and invoice line totals using decimal arithmetic.

## Eligibility and quotation rules

- A job must be in `Repair Completed` or `Ready for Collection`, not canceled, and not already linked to an invoice.
- The invoice customer must match every repair-job customer. Foreign keys also enforce this for direct database inserts.
- An issued approved quotation is used as the immutable price reference. If its total differs from the saved job total, invoice creation/update is rejected until an authorized price-change workflow is defined.
- Existing issued part usages are printed as item lines only when their line sum exactly matches the job's parts charge. Otherwise the full recorded parts amount is represented by a single line, avoiding an unexplained total mismatch.

The invoice header total is the sum of job amounts. Discounts and taxes are not added by this workflow. Every job amount and line is snapshotted for later display and print.

## Draft edits and reservations

An existing draft can be updated and jobs may be appended, but existing job links and line snapshots cannot be removed or repriced. A draft therefore reserves its selected jobs. Cancellation does not release those links: the current schema uniquely allows one invoice link per job across draft, issued, and canceled invoices. Do not delete links to re-bill a job; an approved adjustment/reissue design is needed first.

## Step 8 handoff

Payment allocation tables remain the source for advances and prior payments. Step 7 reads net allocations for display only. It does not add payment records, allocate a current tender, change the customer ledger, or implement refunds. Step 8 must reconcile payments, releases/reversals, invoices, and account entries atomically.
