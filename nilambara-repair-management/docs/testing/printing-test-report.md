# Printing and PDF verification

Automated checks: A5 invoice PDF signature/format and snapshot contents; A4/A5 quotation PDFs with Sinhala font; thermal/A4/A5 POS receipt PDFs; customer statement PDF; report PDF/workbook Unicode and money. Receipt and A6 card tests verify HTML paper styles, two-page writable card, duplicate label, print history, and snapshots. Versioned template changes and supported sizes are tested.

A5 receipt and A6 card currently provide browser printing; dedicated server PDF export is not verified. Payment receipt HTML exists. Supplier printing cannot be accepted without supplier/purchase workflows. Business title/font/logo/signature settings have not all been traced across every renderer, and statement paper settings require further acceptance review.

Prior Step 11 report PDF/dashboard screenshot artifacts exist under `storage/app/testing/`; they are historical visual evidence, not a new Step 12 browser sweep. This step generated PDFs through automated tests, but no fresh browser print-preview/manual page-break sweep or physical printer/scanner test was performed. English/Sinhala PDF tests establish renderability; they do not certify visual quality on every form, font, paper or device. No assets were built.
