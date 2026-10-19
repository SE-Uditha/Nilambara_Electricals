# Repair receipts

Step 5 implements repair receiving at `/repair-receipts`. A receipt submission creates the repair job and receiving document together; the database transaction also writes the initial `Received` status event and creates its A6 job card. If an optional advance is entered, its payment, job allocation, and customer-ledger entry are part of the same transaction.

## Registration and identifiers

The form selects an active customer and active receiving employee, then captures the unique staff-entered job number, item name/brand/model/description, reported fault, accessories, condition/damage, job type, labour cost, optional advance/payment method, and special notes. Job type (`Normal`, `Special`, or `Urgent`) remains internal and is omitted from the customer-facing receipt.

The database-generated repair-job ID is formatted as `JOB-00001` for the automatic reference. The manual job number remains a separate unique identifier and cannot be changed after creation. The receipt stores a snapshot of the customer, item, and receiving details so its output remains stable if linked records change later.

## Search, details, and print

The list supports text search, date range, job type, status, and receiving employee filters with database pagination. The detail view shows the receipt, linked job, and status history.

The print view defaults to A5 and can use an active receipt print template for paper, layout, margins, title, and branding. It includes the customer copy and signature area. Reprints display the configured duplicate label. Each prepared copy creates an append-only print-history and audit event. These are HTML print views using the browser's print dialog; no server-side PDF is generated.

## Access

- `receipts.view`: list and view receipts.
- `receipts.create`: register a receipt and linked job.
- `receipts.print`: prepare the original receipt for printing.
- `receipts.reprint`: prepare a duplicate.

Job-level visibility policy still applies to linked job data. Tests use isolated SQLite and do not create real customer or repair records in the configured database.

