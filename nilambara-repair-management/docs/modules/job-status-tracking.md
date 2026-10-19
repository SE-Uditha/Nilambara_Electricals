# Job status tracking

Step 5 exposes the BRD's 17 status values through `App\Enums\JobStatus`. Each authorized change updates `repair_jobs.status` and appends a `job_status_histories` row containing the prior status, new status, actor, timestamp, and optional notes. Initial registration writes the first `Received` history row with no previous status.

## Status values

Received; Pending Inspection; Under Inspection; Quotation Pending; Waiting for Customer Approval; Customer Contact Failed; Advance Payment Pending; Waiting for Spare Parts; Under Repair; Price Modification; Re – Assembling; Repair Completed; Ready for Collection; Customer Contact Failed After Ready; Delivered; Cancelled; Unable to Repair.

No transition graph is imposed because the BRD does not define one. The `jobs.update-status` permission is required to change a status; `jobs.complete` is additionally required for `Repair Completed`, and `jobs.cancel` for `Cancelled`. Existing role grants and per-user overrides determine which users can perform these actions.

The job list and status-search view support database filtering and pagination. Technicians are limited to their assigned jobs. Completion does not itself mark a job Delivered, and the current Step 5 status operation does not implement a delivery workflow.

