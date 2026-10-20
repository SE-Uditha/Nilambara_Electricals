# POS management

See [POS report definitions](pos-reports.md) and [Step 10 verification](../development/step-10-summary.md). Record actions use the registered `PosSalePolicy`. Managers approve discounts/non-cash tenders/returns/refunds; performing checkout additionally needs the cashier permission. Storekeepers can inspect POS but cannot sell/refund.

The POS module supports cashier checkout, manager verification of non-cash tenders, sale history, receipts, returns, and operational summaries. It uses the existing spare-parts catalog, `stock_movements` ledger, `payments` and `payment_allocations` ledgers, and `stock_returns`; it does not create duplicate stock or payment balances.

## Main routes and permissions

- `pos.index` and cart operations: `pos.view`; checkout additionally requires `pos.sale`.
- Discounts: `pos.discount`.
- Sale history/details and payment verification: `pos.sales.view` and `pos.payment.verify`.
- Receipt print/PDF: `pos.receipt.print`.
- Returns and refunds: `pos.return`; refunds additionally require `pos.refund` and `payments.reverse`.
- Reports: `pos.reports.view`.

Permission is enforced on server routes and in the relevant services. Role grants are seeded by `RoleSeeder`.

## Workflow

1. Search by SKU, barcode, part name, brand, or category; add selected stock items to the session cart.
2. Checkout validates the active cashier, products, quantities, current stock, tender, and authorized discount.
3. Cash sales post a payment/allocation and stock movement in one transaction.
4. Bank transfers and cheques remain pending until a manager confirms receipt. Neither payment nor stock is posted before that confirmation. Confirmation does not certify that a cheque has cleared.
5. Finalized sales may be returned through the POS return workflow. Original sale records are retained.

See the focused [checkout](pos-checkout.md), [inventory integration](pos-inventory-integration.md), [payments](pos-payments.md), [receipts](pos-receipts.md), and [returns](pos-returns.md) guides.

## Scope boundary

Step 9's purchasing, goods receiving, stock counts, adjustments, supplier returns, and valuation workflows remain separate planned work. POS uses the existing shared movement ledger without claiming those workflows are implemented.
