# POS returns

Refund ceilings proportionally allocate sale-wide discounts to returned quantities, using exact decimal division rounded down to currency precision. A discounted item cannot be refunded at its undiscounted price. Payment reversal limits also cap cumulative refunds at the amount collected. Return retries are bound to the original actor and request fingerprint; cumulative quantities use a locking current read.

Only finalized sales may be returned. Each request identifies one original POS sale item, quantity, condition (`resalable` or `damaged`), reason, optional refund, and idempotency key. Quantity is checked against the original sold quantity less all prior finalized returns.

The return is retained in the shared `stock_returns` and `stock_return_items` tables and never edits or deletes the original sale. Resalable returns restore saleable stock through the shared movement ledger. Damaged returns do not increase saleable stock.

Refunds are optional and require both `pos.refund` and `payments.reverse`. A refund is capped at the returned line's net value and is posted as a payment reversal through the existing payment service. Reusing a return idempotency key with changed details is rejected.
