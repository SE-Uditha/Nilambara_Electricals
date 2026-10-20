# Integration test report

Final native MySQL result: **218 passed (3566 assertions)**, duration 175.11s. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.

Verified chains: intake/advance → approval pricing → repair stock issue → invoice → net payments → delivery; supplier purchase draft → approval/payable → partial receiving → stock ledger → supplier payment → reversal; supplier return → stock deduction → explicitly agreed supplier credit; POS versus repair competing for the same stock. Existing customer financial tables are untouched by supplier payment operations.

Draft purchase, quotation and part request saves do not change stock. Approval sets job prices but does not issue parts or collect money. Repeat posts are protected by stable keys and locked source documents. Failure cases and the native races exercise duplicate/over-receiving/overselling protection.

Native concurrency covers two receptionists, two assigned technicians on their respective jobs, two invoice/payment cashiers, POS versus repair issue, and two GRN receivers. This does not establish a whole-shop capacity limit or same-record optimistic conflict detection for arbitrary edits.
- PASS: two cashiers buying four of five units; one sale, one rejection, one unit remains.
- PASS: simultaneous independent checkouts receive distinct sale numbers.
- PASS: simultaneous identical retries create one sale and one payment.
- PASS: two simultaneous cashiers, invoice, one save and one rejection; barrier-to-completion 165.828 ms.
- PASS: two simultaneous cashiers, payment, one save and one rejection; barrier-to-completion 198.91 ms.
- PASS: one invoice-job reservation and exactly 100.00 allocated, no overpayment.
- PASS: service/PDF observations recorded: {"invoice-create-and-issue":57.288,"payment-receive-and-allocate":63.399,"pos-checkout-including-stock-posting":34.648,"a5-invoice-pdf-with-print-history":1424.018}
- PASS: two receptionists create atomic receipts and unique jobs; 2 concurrent workers; 149.996 ms; ["SAVED:1","SAVED:2"]
- PASS: two technicians update their assigned jobs with retained histories; 2 concurrent workers; 103.372 ms; ["SAVED:5","SAVED:6"]
- PASS: POS and repair issue compete for the same five units; 2 concurrent workers; 163.011 ms; ["SAVED:1","REJECTED:{\"cart\":[\"Insufficient saleable stock for Test spare part. Available: 1.000.\"]}"]
- PASS: two storekeepers cannot over-receive a purchase; 2 concurrent workers; 151.152 ms; ["REJECTED:{\"quantity\":[\"Quantity exceeds the unreceived purchase quantity.\"]}","SAVED:1"]
- PASS: receipt, technician, shared stock and GRN race invariants verified in disposable MySQL.
- PASS: real MySQL dump created, nonempty, SHA-256 verified, private and protected.
- PASS: confirmation and wrong-password refusals; authenticated CLI restore, safety dump, maintenance lifecycle, data recovery and audit history.

An approved live-backup folder has not been supplied; Google Drive folder/account/OAuth fields remain blank as requested. No host/production scheduler, production build, installer, packaging or deployment was performed. Physical printer/scanner checks, realistic-volume capacity acceptance and owner signatures remain pending. Latest purchase cost was explicitly selected by the owner. Purchase-wide discount/freight allocation remains a separate unanswered decision; affected profit estimates remain incomplete rather than assuming a policy. Existing role permissions were preserved: storekeepers can receive/issue/count/adjust, while purchase approval and supplier money operations require `purchases.approve`.
