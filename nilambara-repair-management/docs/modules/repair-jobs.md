# Repair jobs

Step 5 adds job browsing and the initial repair-workflow operations to the existing `repair_jobs` and `job_inspections` records. It does not introduce a second repair-job master.

## Search and job details

The repair-job list is database-paginated and supports partial search across job identifiers, customer/name/mobile, and item information, plus filters for current status, job type, and received date. The status search uses the same authorized records. Administrator and Cashier use the shared job-view policy. Cashier includes inspection, repair and completion duties; repair assignments reference active employee records independently of login roles.

The detail page presents the current job, linked receipt/customer, technician, inspection and repair notes, and append-only status history. Financial totals and outstanding balances are intentionally unavailable until an authorized billing workflow provides them.

## Assignment, inspection, and repair notes

Users with `jobs.assign` may assign an active technician employee. Users with `jobs.inspect` may record inspection date, findings, parts used, quantity, and inspected-by employee. Users with `jobs.repair` may record work performed, parts/cost notes, repaired-by employee, and completion date. These operations validate active employees and append audit events. Parts are notes only: Step 5 does not deduct or reserve inventory.

## Permissions and scope

- `jobs.view`: list, search, and view jobs, subject to record-level visibility.
- `jobs.update`: edit permitted job details.
- `jobs.assign`: assign an active technician.
- `jobs.inspect`: record inspection details.
- `jobs.repair`: record work and repair information.
- `jobs.update-status`: change status and append history.
- `jobs.complete` and `jobs.cancel`: additional authorization for those status values.

Quotation approval, final invoicing, stock movements, testing workflows, customer delivery, and warranty features remain future work. See the [job-status guide](job-status-tracking.md) for status history behavior.


## Unified job acceptance and customer receipts (22 October 2026)

The sidebar has one Repair Jobs dropdown containing New Repair Job, All Repair Jobs, Job Cards and Job Status. Repair receiving and printable receipts are combined into this group, with no separate Repair Receipts menu. Open Repair Jobs > New Repair Job to accept an item. Accept Repair Job saves the job and automatically generates its customer receipt in the existing registration transaction. Accept & Print Receipt opens that receipt immediately. Job cards and status search remain available from the job list.

The job list includes a receipt print action when a saved receipt exists. Job details provide Print Receipt, Receipt PDF and an authorized duplicate-copy action. Existing receipt list/detail links redirect into Repair Jobs; historical receipt print links remain usable. Print/PDF/reprint operations use the saved receipt snapshot and retain print-history/audit entries; they do not create additional jobs or receipts.

The receipt uses a compact A5 customer-copy layout with business branding, received date/time, job/manual references, customer contact details, item and reported fault/condition/accessories, recorded advances and labour charges, notes, signatures and a collection reminder. Job type is excluded from print/export data. Active template paper/orientation/margins/branding/footer settings remain supported. Longer content may continue to another page without clipping.

No database tables, saved receipt snapshots, job references, users or existing business data are removed or rewritten by this UI/workflow consolidation.

Verification: 235 isolated MySQL tests passed (3,707 assertions), followed by complete migration rollback/remigration and four concurrent workflow checks. Focused sidebar/intake/receipt tests and the Sinhala-customer PDF test also passed. The normal English and Sinhala-customer sample receipts were rendered and visually inspected as single A5 pages. Configuration and compiled-view caches were cleared. No live business records or database schema changes were required.

## Receiving receipts and final invoices (22 October 2026)

A receiving receipt is an acknowledgement of the item, not a completed-work invoice. It never prints labour costs or repair totals, including for old snapshots. Zero advances are omitted; a positive advance and its payment reference remain visible. New intake no longer requests labour cost. Job totals are displayed after completion, and invoicing continues to accept only completed/ready jobs.

On a completed unbilled job, select Prepare Final Bill / Invoice. Enter the base labour charge, select stock spare parts and quantities, and add custom description/quantity/unit-price lines. A single typed amount uses quantity 1. Save Bill Changes issues new spare parts from inventory and recalculates totals transactionally. Existing issued parts are listed separately to avoid adding them twice. Repeated submission with the same key does not issue the same parts again. Insufficient stock rolls back charges, prices and stock requests together.

Custom charge lines are stored in repair_job_charges (many lines belong to one repair_jobs record). The migration uses InnoDB, utf8mb4, DECIMAL quantity/prices/totals, restrictive foreign keys, unique job/line numbers and financial check constraints. Removed custom lines are retained with removed_at and audit history. Existing tables and historical records are preserved.

Create Invoice from Saved Bill opens the separate invoice form with that customer and completed job selected. The invoice imports base labour, stock parts and individual custom descriptions/amounts, calculates totals server-side, and uses existing advance/payment handling. Unsaved bill changes must be saved first. Issued-quotation prices require quotation revision/approval to change, and an existing invoice prevents further bill edits. Invoice job selection also links back to bill preparation.

Browser live totals use scaled integers; server calculations use BigDecimal with per-line half-up rounding. Four-decimal unit prices print without losing precision. The sample receiving receipt and final invoice were both visually checked as single A5 pages.

Final billing verification: the full disposable MySQL suite passed 239 tests (3,759 assertions). After the final additions, 61 affected MySQL tests passed (2,521 assertions), with rollback/remigration and all four concurrency checks succeeding. Six focused billing tests passed (51 assertions). A real Chrome/Alpine check verified the live 155.25 example total and 0.50 fractional-line rounding. The live migration created the empty charge table and verified unchanged records in all 64 pre-existing nonvolatile tables. Recovery snapshot: storage/app/private/pre-final-billing-20261022-120031-bee97b.json.enc; retain the Laravel APP_KEY for recovery.
