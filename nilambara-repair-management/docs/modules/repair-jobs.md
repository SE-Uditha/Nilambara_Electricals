# Repair jobs

## Database integrity incident checks

Job registration, payment, job-card printing and invoicing depend on transactional InnoDB tables and valid job foreign keys. A MyISAM `repair_jobs` table is not compatible: failed transactions can leave partial jobs, and changing the engine alone cannot reconcile existing mismatched records or guarantee that foreign keys are correct.

For an affected installation, stop business writes, retain the original database and take a fresh, verified backup before any repair. In phpMyAdmin select the application's database and run these read-only checks:

```sql
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('repair_jobs', 'repair_receipts', 'job_cards',
                     'job_status_histories', 'payment_allocations',
                     'repair_job_charges', 'invoice_jobs');

SELECT r.id AS receipt_id, r.repair_job_id, r.customer_id AS receipt_customer_id,
       j.customer_id AS job_customer_id
FROM repair_receipts AS r
LEFT JOIN repair_jobs AS j ON j.id = r.repair_job_id
WHERE j.id IS NULL OR r.customer_id <> j.customer_id;

SELECT a.id AS allocation_id, a.repair_job_id, a.customer_id AS allocation_customer_id,
       j.customer_id AS job_customer_id
FROM payment_allocations AS a
LEFT JOIN repair_jobs AS j ON j.id = a.repair_job_id
WHERE a.repair_job_id IS NOT NULL
  AND (j.id IS NULL OR NOT (a.customer_id <=> j.customer_id));

SELECT j.id AS job_id
FROM repair_jobs AS j
LEFT JOIN repair_receipts AS r ON r.repair_job_id = j.id
WHERE r.id IS NULL;
```

Missing receipts and mismatches require investigation against original receipts, payments and audit history; do not infer ownership or delete records automatically. Have a database administrator inspect the live engines, foreign keys, constraints and possible InnoDB metadata damage, reconcile records with the business owner, then plan and test the repair on a separate restored copy before changing production. Do not disable foreign-key checks or run `migrate:fresh` as a workaround. Verify a new job, job-card preview, payment and invoice after repair, and confirm the corresponding records and financial balances.

Step 5 adds job browsing and the initial repair-workflow operations to the existing `repair_jobs` and `job_inspections` records. It does not introduce a second repair-job master.

## Search and job details

The repair-job list is database-paginated and supports partial search across job identifiers, customer/name/mobile, and item information, plus filters for current status, job type, and received date. The status search uses the same authorized records. Administrator and Cashier use the shared job-view policy. Cashier includes inspection, repair and completion duties; repair assignments reference active employee records independently of login roles.

The detail page presents the current job, linked receipt/customer, technician, inspection and repair notes, required inventory parts, estimated costs, and append-only status history. Repair details, part requests, and cancellation information remain visible after cancellation.

## Assignment, inspection, and repair notes

Users with `jobs.assign` may assign an active technician employee. Users with `jobs.inspect` may record inspection date, findings, parts used, quantity, and inspected-by employee. Users with `jobs.repair` may record work performed, parts/cost notes, repaired-by employee, and completion date. These operations validate active employees and append audit events. The Job Details page also lets authorized users request, edit, and cancel inventory part requests in any job status, including cancelled. Requests do not reserve or deduct stock. On the transition to Repair Completed, open requests are checked against available stock and posted once in the same transaction as the status change. A failed stock check rolls back completion; cancelled-job requests do not create stock movements.

## Permissions and scope

- `jobs.view`: list, search, and view jobs, subject to record-level visibility.
- `jobs.update`: edit permitted job details.
- `jobs.assign`: assign an active technician.
- `jobs.inspect`: record inspection details.
- `jobs.repair`: record work and repair information.
- `jobs.update-status`: change status and append history.
- `jobs.complete` and `jobs.cancel`: additional authorization for those status values.

Final invoicing, testing workflows, customer delivery, and warranty features remain separate workflows. See the [job-status guide](job-status-tracking.md) for status history behavior.


## Unified job acceptance and customer receipts (22 October 2026)

The sidebar has one Repair Jobs dropdown containing New Repair Job, All Repair Jobs, Job Cards and Job Status. Repair receiving and printable receipts are combined into this group, with no separate Repair Receipts menu. Open Repair Jobs > New Repair Job to accept an item. Accept Repair Job saves the job and automatically generates its customer receipt in the existing registration transaction. Accept & Print Receipt opens that receipt immediately. Job cards and status search remain available from the job list.

Administrators can add common item names under System settings and save them, just like common faults. On new and edited repair jobs, select a saved name from the item dropdown or enter a different name and press Add. The table shows one selected name; adding another replaces it. When no names are configured, the dropdown is disabled and manual entry remains available.

Reported faults use the same list as visible damage on new and edited jobs: select configured faults or add manual entries, and remove entries from the table. The saved field remains newline-separated text with its existing 10,000-character limit; existing text is retained unchanged until the list is edited.

The job list includes a receipt print action when a saved receipt exists. Job details provide Print Receipt, Receipt PDF and an authorized duplicate-copy action. Existing receipt list/detail links redirect into Repair Jobs; historical receipt print links remain usable. Print/PDF/reprint operations use the saved receipt snapshot and retain print-history/audit entries; they do not create additional jobs or receipts.

The receipt uses a compact A5 customer-copy layout with business branding, received date/time, job/manual references, customer contact details, item and reported fault/condition/accessories, recorded advances and labour charges, notes, signatures and a collection reminder. Job type is excluded from print/export data. Active template paper/orientation/margins/branding/footer settings remain supported. Longer content may continue to another page without clipping.

No database tables, saved receipt snapshots, job references, users or existing business data are removed or rewritten by this UI/workflow consolidation.

Verification: 235 isolated MySQL tests passed (3,707 assertions), followed by complete migration rollback/remigration and four concurrent workflow checks. Focused sidebar/intake/receipt tests and the Sinhala-customer PDF test also passed. The normal English and Sinhala-customer sample receipts were rendered and visually inspected as single A5 pages. Configuration and compiled-view caches were cleared. No live business records or database schema changes were required.

## Receiving receipts and final invoices (22 October 2026)

A receiving receipt is an acknowledgement of the item, not a completed-work invoice. It never prints labour costs or repair totals, including for old snapshots. Zero advances are omitted; a positive advance and its payment reference remain visible. New intake no longer requests labour cost. Job totals are displayed after completion, and invoicing continues to accept only completed/ready jobs.

Record required stock parts from Job Details before completion. Marking the job Repair Completed posts those open requests to stock exactly once; final bill preparation never issues stock. On a completed unbilled job, select Prepare Final Bill / Invoice, enter the base labour charge, and add custom description/quantity/unit-price lines. A single typed amount uses quantity 1. Existing issued parts are listed in the bill and contribute to invoice totals. Insufficient stock prevents completion and leaves the job and ledger unchanged.

Custom charge lines are stored in repair_job_charges (many lines belong to one repair_jobs record). The migration uses InnoDB, utf8mb4, DECIMAL quantity/prices/totals, restrictive foreign keys, unique job/line numbers and financial check constraints. Removed custom lines are retained with removed_at and audit history. Existing tables and historical records are preserved.

Create Invoice from Saved Bill opens the separate invoice form with that customer and completed job selected. The invoice imports base labour, stock parts and individual custom descriptions/amounts, calculates totals server-side, and uses existing advance/payment handling. Unsaved bill changes must be saved first. Issued-quotation prices require quotation revision/approval to change, and an existing invoice prevents further bill edits. Invoice job selection also links back to bill preparation.

Browser live totals use scaled integers; server calculations use BigDecimal with per-line half-up rounding. Four-decimal unit prices print without losing precision. The sample receiving receipt and final invoice were both visually checked as single A5 pages.

Final billing verification: the full disposable MySQL suite passed 239 tests (3,759 assertions). After the final additions, 61 affected MySQL tests passed (2,521 assertions), with rollback/remigration and all four concurrency checks succeeding. Six focused billing tests passed (51 assertions). A real Chrome/Alpine check verified the live 155.25 example total and 0.50 fractional-line rounding. The live migration created the empty charge table and verified unchanged records in all 64 pre-existing nonvolatile tables. Recovery snapshot: storage/app/private/pre-final-billing-20261022-120031-bee97b.json.enc; retain the Laravel APP_KEY for recovery.
