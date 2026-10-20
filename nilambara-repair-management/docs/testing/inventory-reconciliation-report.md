# Inventory reconciliation report

Stock is derived from one signed `stock_movements` ledger, not a new inventory database. POS tests assert exact sale deductions, one movement per source, current locking reads, rollback on failure, resalable returns only, damaged-return non-restocking, SKU/barcode lookup and no overselling. Quotation/invoice tests assert no unintended movement on approval/printing.

Native test: two cashiers each request four units from five available; exactly one succeeds and one unit remains. Further native scenarios verify distinct sale numbers and one sale/payment on identical retries. These are two concurrent processes, not a measured full-shop capacity.

Missing workflows: stock master maintenance, opening/receiving UI, approved repair issue, supplier returns, adjustment/damage/count posting and GRN stock receiving. Model integrity tests do not replace these. Repair/POS competing-stock and concurrent GRN scenarios are blocked. No manual operational stock adjustment was made for this audit.
