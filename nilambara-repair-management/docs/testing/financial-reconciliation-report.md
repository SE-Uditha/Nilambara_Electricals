# Financial reconciliation report

Final native MySQL result: **218 passed (3566 assertions)**, duration 175.11s. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.

Exact disposable-fixture assertions include: approved quotation total 30.00, advance 5.00, outstanding 25.00 then 0.00; repair parts cost 6.00 and labour cost 2.00 produce recorded profit estimate 22.00. Supplier purchase total 16.00, payment 6.00 leaves 10.00; full reversal restores 16.00. Agreed supplier return credit 5.00 leaves 11.00. Opening balance 10.50 less payment 3.50 gives 7.00, restored to 10.50 after reversal. These are test fixtures only.

Latest-purchase fixture: an old sale costs 3.00, a new purchase costs 7.00, the old item returns at its original 3.00, and a subsequent sale still snapshots 7.00. Nine units are valued at 63.00. Net revenue 10.00 and net recovered/sold cost 7.00 produce a 3.00 recorded margin. Unknown costs remain unknown, not invented zero costs.

Invoice and customer payment calculations continue to derive net allocations/releases; advances are not counted twice. Supplier balances use the existing supplier ledger, with positive purchase/opening liabilities, negative payments/return credits, and linked positive reversals. Original entries remain append-only. No parallel accounting ledger was created.

Latest purchase cost is owner-approved. Purchase-level freight/discount allocation is not yet approved. Affected estimates, cost mismatches, unknown source costs, no-refund stock returns and ambiguous repair cash refunds remain labelled incomplete. Profit is not sales revenue or a certified accounting statement.
