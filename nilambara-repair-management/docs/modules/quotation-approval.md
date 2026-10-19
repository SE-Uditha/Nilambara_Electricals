# Quotation approval and repair gate

Customer decisions are manually recorded by an authorized Manager/Administrator; the application does not send an offer, authenticate the customer, capture a signature, or prove identity.

## Workflow

1. Issuing a valid draft freezes its document snapshot, marks the preparation status `issued`, sets approval to `pending`, and moves the job to `Waiting for Customer Approval`.
2. Record the customer's decision, contact name, communication method, decision date, and optional notes. Decisions are accepted only once for the current pending issued quotation; decision time cannot be in the future.
3. Approval is rejected if the quotation has expired. On approval, quotation and job approval records, audit/history, and job status are updated in one transaction; the job moves to `Under Repair`.
4. Rejection is recorded as history and moves the job to `Customer Contact Failed`.
5. A later issued revision supersedes prior issued versions, resets the job approval to pending, and returns the job to `Waiting for Customer Approval`. The new version must be approved before work starts.

The selected approval/rejection status transitions are business-approved project choices. Job status changes are made through the existing `RepairJobService`, so `job_status_histories` records actor, timestamp, old/new status, and reason.

## Authorization and enforcement

Only users with `quotations.approve` and access to the linked job may record a decision. The policy prevents decisions on drafts, superseded/cancelled quotes, or quotes already decided. Cashiers cannot see pending quotes. Quotation and repair-job statuses are independently checked server-side; directly setting a job to `Under Repair` is blocked whenever an issued quotation exists and job approval is not `approved`.

Jobs without an issued quotation remain eligible for the existing status workflow; the system has no separate “approval required” flag. Issuing a quote establishes that the job is subject to the approval gate.

## History and no downstream transaction

Every decision appends an immutable `quotation_approvals` record and a `quotation_history` event; the job-level approval and status histories are written in the same database transaction. Failed or repeated decisions leave no partial status updates.

Approval does not deduct stock, issue parts, create an invoice, or record a payment. Billing must later consume the exact approved quotation version rather than recomputing or modifying the historical approval.
