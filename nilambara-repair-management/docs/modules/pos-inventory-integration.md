# POS inventory integration

POS reads saleable quantity from the same `stock_movements` ledger used by inventory and repair workflows. It creates no POS-specific stock balance table. Each finalized sale item is linked to one negative `pos_sale` movement; saleable stock is recomputed from movements at the active default location.

The checkout transaction locks spare-part rows in stable ID order, reloads current balances, and posts all stock movements with the sale. A pending bank-transfer or cheque sale creates no movement until an authorized manager confirms receipt and finalizes the sale.

Returns retain their original sale and link each return item to the sold item. A `resalable` item creates a positive `customer_return` movement. A `damaged` item creates no positive saleable-stock movement. Cumulative quantities returned cannot exceed the original sold quantity.

This guide describes POS's use of existing ledger structures, not completion of the broader Step 9 inventory and purchasing workflows.
