# Invoice printing and PDF

Invoices provide an A5 portrait HTML print view and server-generated PDF. Both use the saved invoice snapshot and line snapshots so reprints do not silently adopt later customer or job edits. Duplicate printing is explicitly labelled; each print, duplicate, and PDF export creates a `document_print_histories` record and audit event.

## Controls and layout

- Print, duplicate print, and PDF export require their respective invoice permissions.
- Original and duplicate outputs show the invoice number, issue date, customer, job-wise totals and item lines, terms, due date when present, delivery information, notes, derived payment summary, and signature areas.
- The configured business name, address, contact, logo, currency, date/time format, and Sinhala fonts are used where available.
- PDF rendering requires the PHP DOM, fileinfo, and GD extensions and the bundled Noto fonts. Enable those extensions and restart PHP if PDF export fails.

The browser print dialog is controlled by the user's browser/printer. Verify A5 paper size, margins, Sinhala glyphs, logo scaling, and physical output on the deployment printer; a successful PDF response alone does not establish final print acceptance. Server-side PDF generation does not create a payment or delivery event.
