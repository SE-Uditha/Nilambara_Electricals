# Local performance test report

Final native MySQL result: **218 passed (3566 assertions)**, duration 175.11s. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.

One local service iteration per operation; fixture construction excluded; no browser/network latency.

| Operation | ms |
|---|---:|
| invoice-create-and-issue | 57.288 |
| payment-receive-and-allocate | 63.399 |
| pos-checkout-including-stock-posting | 34.648 |
| a5-invoice-pdf-with-print-history | 1424.018 |

Dataset row counts: `{"customers": 2, "repair_jobs": 2, "invoices": 2, "payments": 7, "pos_sales": 5, "spare_parts": 3, "stock_movements": 8}`. Native race timings are in the completion report. These are small local observations, not percentiles or production capacity. Existing report/dashboard/customer/job timing fixture output remains in step12-local-timings.json. No realistic-volume capacity or statistically defensible slow-query benchmark was performed.
