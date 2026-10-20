# Sales and income reports

The report registry provides sales, daily/monthly/yearly sales, repair income, invoice-wise sales, cash/credit breakdown, cashier breakdown, discounts, refunds, payment collections and POS-parts reports. Use `/reports` for permitted links.

Sales use finalized invoices and POS headers, not invoice-job joins; a multi-job invoice counts once. Gross is subtotal, discount is counted once, and net is the document total. General and grouped sales include negative refund rows on reversal dates for POS/invoice/credit-collection payments. Advances and their refunds are collections rather than a second sale. Repair-income and invoice-only views show original invoice values; consult refunds for subsequent adjustments. POS parts retain original sale quantities/values; return disposition is available in POS return history.

Refund cash/credit attribution follows the original collection type. Mixed invoice-allocation attribution needs an approved accounting policy; the UI documents this limitation. Cashier reports/collections enforce the current employee for cashiers without financial-report permission.

Filters run on the backend. Sorting is restricted to displayed columns. Exports enforce the same report authorization plus `reports.export`. PDF is A4 with business identity, selected filters, summaries, generated time, Sinhala font and page numbers. XLSX streams rows with headings, numeric/date styles and applicable exact totals. Values beyond Excel's 15 significant digits remain text to preserve money. Export limits are 1,000 PDF rows and 50,000 spreadsheet/CSV rows; narrow the range rather than accepting silent truncation.
