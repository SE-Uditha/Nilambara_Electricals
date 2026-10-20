# Functional test report

Evidence logs are under `storage/logs/`: `step12-mysql.log` (initial baseline), `step12-mysql-final.log` (full regression), `step12-mysql-step12.log` (targeted/native additional coverage), `step12-sqlite-targeted.log`, `step12-schema.json`, `step12-syntax.log`, `step12-composer.log`, `step12-dependency-audit.log`. Test fixtures are created only in newly randomized disposable databases. `scripts/verify-mysql.php` refuses production/nonlocal connections and never reuses an existing database.

## Recorded suite summaries

- Final execution not yet recorded.

Initial MySQL baseline: 195 passed, one SQLite-only rollback test skipped, 3381 assertions, 163.65 seconds. An intermediate expanded run had 199 passes and one failure: MySQL trigger fault injection implicitly committed the test transaction (`step12-mysql-injection-failure.log`). Portable isolated model-event fault injection replaced the trigger; final totals above supersede these historical runs. No failing test was disabled.

Customer/company, intake/job/card, quotation/revision/approval, same-customer multi-job invoice, advances/partial/reversed payments, credit collection, POS return/refund, reports/settings/audit and backup code are exercised. See [test methods](automated-test-inventory.md) and [requirement matrix](brd-compliance-matrix.md) for bounded coverage.

Supplier registration, purchase drafts, GRNs, supplier settlement, inventory master UI, repair-part issue, adjustments and stock counts cannot be functionally accepted: tables/models exist but workflows are missing. No substitute financial or stock records were added to the operational database.

HTTP render assertions cover many pages but are not a complete manual click-through, responsive-browser or assistive-technology review. Existing UI was preserved.
