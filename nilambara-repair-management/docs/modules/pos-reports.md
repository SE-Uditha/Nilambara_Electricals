# POS reports

`/pos/reports` requires `pos.reports.view`. Date filters and daily/monthly grouping apply to finalized POS sales. Draft/pending/cancelled sales and repair invoices are excluded.

- Gross sales: finalized subtotals before discounts.
- Discounts: sale-wide discounts counted once.
- Net sales: finalized totals less POS payment refunds/reversals dated in the selected period.
- Collected payments less refunds/reversals: shared POS payment receipts by payment date, less reversals by reversal date. Tender/change are not collections.
- Method and cashier breakdowns: finalized totals before refunds. Item totals are before sale-wide discounts. These breakdowns must not be added together.
- Returns: finalized POS return documents. Refund totals use the reversal ledger, avoiding duplicate counting of return and reversal records.

Refund-only dates/months appear with negative net sales even without new sales. Item stock movement and return disposition references remain available in sale details and return history. This is POS operating reporting; Step 10 adds no broader reporting module.

`php scripts/verify-mysql.php --pos` creates a random disposable local database, runs POS/relationship/native storage tests, checks rollback/remigration, and runs separate-process checkout races. Only that newly created database is removed. Production, remote, cached, and URL-based connection configurations are refused.
