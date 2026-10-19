# Repair jobs

Step 5 adds job browsing and the initial repair-workflow operations to the existing `repair_jobs` and `job_inspections` records. It does not introduce a second repair-job master.

## Search and job details

The repair-job list is database-paginated and supports partial search across job identifiers, customer/name/mobile, and item information, plus filters for current status, job type, and received date. The status search uses the same authorized records. Technicians see only jobs assigned to their linked active employee record; managers/administrators and other permitted roles use the shared job-view policy.

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

