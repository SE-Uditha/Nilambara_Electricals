# POS payments

POS payments are recorded in the existing `payments` table with `payment_type = pos`; `payment_allocations.target_type = pos_sale` links each payment to its sale. Payment history, search, details, and payment receipts include POS allocations.

Cash payment is posted for the sale total, not cash tendered. Cash tender and change are retained on the POS sale. Bank-transfer and cheque checkout records remain pending; no payment/allocation is posted until an authorized manager confirms receipt. This workflow records receipt, not bank settlement or cheque clearing.

Refunds are partial payment reversals through the existing payment reversal service. The POS return workflow is the authorized entry point so a refund cannot be posted without recording the returned item, condition, reason, and stock disposition. Generic payment reversal remains unavailable for POS payments.

Refund amounts cannot exceed the returned line's net selling value or cumulative amounts already refunded against the payment. Walk-in sales do not create a customer-account balance.
