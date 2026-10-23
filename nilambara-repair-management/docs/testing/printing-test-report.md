# Printing and PDF verification

Final native MySQL result: **218 passed (3566 assertions)**, duration 175.11s. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.

The earlier fixture run generated and visually checked PDFs using embedded regular/bold Noto Sinhala fonts: A5 repair receipt (one page), A6 job card (two pages), customized A5 landscape statement (one page), A4 supplier purchase and GRN (one page each). Exact dimensions/font metadata are in pending-pdf-review.json; artifacts are under storage/app/testing/pending-*.pdf and *-review-*.png. Earlier overflow and missing-glyph defects were corrected. Long documents may legitimately paginate beyond these short-fixture counts.

The technician job card has since been updated to the supplied one-page A5 portrait layout. Focused tests generated and visually checked its PDF, confirming one A5 page with intake details, blank technician fields, and cashier handover.

Statement paper/orientation/title now drive both browser CSS and PDF paper, and statement prints/duplicates preserve history. Invoice visible heading uses its configured title. POS receipt PDFs now embed Sinhala fonts and honor configured font size, including thermal output. Intake PDF download/reprint routes enforce original permissions and retain duplicate labels. Supplier/GRN documents support browser print, PDF, duplication, branding, margins, signatures and audit history using the existing purchase print template.

No physical printer/scanner or fresh interactive browser print-preview acceptance was performed. Physical margins, two-sided card handling, thermal roll behavior and native-language business approval remain owner/hardware UAT items. No asset build or packaging occurred.
