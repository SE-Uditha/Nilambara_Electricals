# Inventory reconciliation report

Final native MySQL result: **218 passed (3566 assertions)**, duration 175.11s. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.

One signed movement ledger supports opening stock, GRNs, authorized repair issues, POS, resalable returns, supplier returns, damage, corrections and physical counts. The part is locked before checking current ledger quantities, using the same product lock as POS. Requests do not reserve or deduct stock; approval-and-issue records both authorization and movement atomically. Complete unused issues may be returned once before billing, retaining the original negative movement and a linked positive return.

Native shared-stock race: repair and POS each request four from five; one succeeds and one rejects, leaving one unit. Native GRN race: two receivers request four against five ordered; exactly four are received once. Sequential replay, over-receiving, counts and damage cases also pass. Negative stock is rejected by the workflow. Counts record expected and observed quantities before posting their difference.

Valuation uses the latest posted purchase cost at the location, falling back to an explicitly known opening cost only when no purchase cost exists. Old returns retain original cost snapshots and do not alter the selected latest purchase cost. Unknown costs are visibly excluded from known valuation totals. Purchase-wide adjustment allocation remains pending.
