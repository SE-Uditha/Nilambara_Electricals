# Quotation management

Step 6 implements the quotation workflow on the existing `quotations`, `quotation_items`, and `quotation_approvals` schema, extended by the additive workflow and legacy-status backfill migrations. It does not create inventory movements, invoices, payments, or job-part issues.

## Draft, issue, and revise

- Create a draft from an authorized repair job. The linked customer, job, item, fault, and latest inspection facts are captured in a document snapshot.
- Quotation numbers are generated from the database row id and `BUSINESS_QUOTATION_NUMBER_PREFIX` (default `QUO-`); users cannot submit or edit the number.
- Parts can be selected from active catalogue records, which use the current registered selling price, or entered as a described estimate line. Selecting a catalogue part does not reserve or deduct stock.
- Quantities and prices are validated server-side. Brick Math performs decimal calculations; each part line is rounded half-up to two decimals, the parts total is the sum of rounded lines, and the quotation total is parts plus labour. Discounts and taxes are not included.
- Drafts may be edited. Issuing requires a non-expired validity date and at least one part line; issued values and customer/job facts are frozen in `document_snapshot`.
- Create a revision from an issued quotation. The revision gets a new number and copies its lines and terms. It retains an unexpired validity date; an expired/missing date is renewed for 14 days. The existing issued version is not changed until the replacement is issued; issuance marks other issued versions for that job superseded.
- A draft can be cancelled; an issued quotation may be cancelled before approval. Approved records are not cancellable. Rows and item lines are retained for history.

## Permissions

The `quotations.*` permissions are enforced in routes, policies, and server-side validation:

| Role | Quotation access |
| --- | --- |
| Administrator | All quotation permissions. |
| Manager | View, create/edit, issue, record customer decisions, revise, cancel, print/PDF. |
| Receptionist | View, create/edit, issue, print/PDF. |
| Technician | View only, subject to assigned-job access. |
| Cashier | View and print/PDF only for issued and approved quotations. |

The seeded catalog grants these permissions idempotently. Managers/cashiers who also have customer/job scope do not gain access to unrelated repair jobs through quotation routes.

## Audit and boundaries

Quotation creation, edits, issue, supersession, cancellation, revisions, customer decisions, and document output create audit/history records. Customer decisions append an approval row; the current approval status is also stored on the quotation/job for workflow checks. Issued snapshots remain the source for later print/reprint output.

The documented status flow, approval-gate behavior, decision-recording limitation, and downstream boundaries are in the [approval guide](quotation-approval.md) and [printing guide](quotation-printing.md). Approved quote values are not yet copied into an invoice because billing is a later step.
