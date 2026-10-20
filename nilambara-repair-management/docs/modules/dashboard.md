# Business dashboard

`/dashboard` requires `dashboard.view`. Cards and lists additionally respect the relevant job, invoice, POS, payment, credit and inventory permissions. Technicians see only their currently assigned jobs.

Today, yesterday, week-to-date, month-to-date, year-to-date and custom ranges use Asia/Colombo. Stored business DATETIME values use the application's configured business timezone. Repair figures are a received-date cohort, classified by current status; they are not a reconstruction of historical status. Recent lists use the selected range. Ready-for-collection jobs use the same cohort.

Repair and POS sales include finalized documents only and are distinct from collections. Payment figures reuse the reporting receipt/reversal query; refunds affect the reversal date rather than rewriting the receipt date. Cashier collection views are limited to that employee. Customer credit uses positive per-customer ledger balances, including opening entries, without offsetting one customer's debt against another's credit.

Inventory quantities come from the shared movement ledger at default locations, through the selected end date. Low stock uses minimum stock where positive, otherwise reorder level. Stock valuation is incomplete until a costing policy is approved. No example statistics or ratings are generated.
