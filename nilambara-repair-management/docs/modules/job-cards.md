# Job cards

Step 5 creates an A6 `job_cards` row when a receipt/job is registered and provides a print-history-backed browser print view for authorized users.

## Card content and printing

The two-page A6 layout uses current job and latest inspection facts: automatic/manual job identifiers, item and reported fault, customer and technician context, status, inspection/repair details, parts notes, and special notes. Its work areas are sized for handwritten notes. A duplicate print displays the configured duplicate label and snapshots the data used for that copy.

The template may configure orientation, margins, font size, business identity, logo, and title. Output is HTML/CSS sent to the browser print dialog; it is not a server-generated PDF. Verify paper selection and physical alignment on the target printer before operational use.

## Access

- `job-cards.view`: browse jobs available for job-card handling.
- `job-cards.print`: prepare the original card or duplicate for printing.

Job visibility policies apply, including technician access being limited to assigned jobs. Card printing does not modify job status or parts inventory.

