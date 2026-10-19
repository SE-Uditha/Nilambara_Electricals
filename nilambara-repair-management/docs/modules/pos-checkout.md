# POS checkout

The checkout screen supports scanner-style barcode entry and search against SKU, barcode, product name, brand, and category. Cart quantities and monetary calculations are revalidated server-side using decimal arithmetic. The service locks product records in a stable order and rechecks saleable balances before posting.

Cash checkout records tendered cash and change on `pos_sales`, but posts only the sale total to the existing payment ledger. A successful cash checkout records the sale, `pos` payment, allocation to the sale, and negative POS stock movements atomically.

Discounts require `pos.discount`; unauthorized discount requests are denied by the backend. The checkout idempotency key is bound to a hash of the normalized tender, customer, discount, notes, and cart. A retry with the same data returns the existing sale; changed data using the same key is rejected.

Bank transfers and cheques create pending draft sales. A manager with `pos.payment.verify` confirms that funds or a cheque were received before payment and stock are posted. A recorded cheque is not represented as cleared.
