# Job cards

Step 5 creates a `job_cards` row when a receipt/job is registered and provides a print-history-backed browser print view for authorized users.

## Card content and printing

The one-page A5 portrait layout follows the supplied repair job card. It pre-fills the job ID/number, received date, item, brand/details, and reported fault. Status, issues found, tested/repaired by and dates, repair details, parts, costs, total, special notes, and cashier handover fields remain blank for the technician to complete by hand. Saved inspection, repair, status, parts, and billing values are deliberately not printed as technician entries; the completed card is returned to the cashier for system updates. A duplicate print displays the configured duplicate label and snapshots the data used for that copy.

The template may configure margins, font size, business identity, logo, and title; job cards use A5 portrait. Output is HTML/CSS sent to the browser print dialog or converted to PDF for download. Verify paper selection and physical alignment on the target printer before operational use.

## Access

- `job-cards.view`: browse jobs available for job-card handling.
- `job-cards.print`: prepare the original card or duplicate for printing.

Job visibility policies apply, including technician access being limited to assigned jobs. Card printing does not modify job status or parts inventory.
