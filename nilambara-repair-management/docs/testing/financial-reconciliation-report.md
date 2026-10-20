# Financial reconciliation report

Disposable-fixture assertions verify invoice total minus net applied allocations/eligible advances, allocation releases and reversals. Partial invoices remain outstanding; advances are applied once. Same-customer constraints prevent transferring another customer's money. Payment idempotency blocks duplicate collection and overpayment.

Report tests count a multi-job invoice once, exclude drafts, and use reversal date for negative collections without rewriting the original day. POS refund tests retain the original sale, reverse linked payments and preserve return audit/movement history. Step 12 adds exact final-cent tests for repeated partial returns and cross-line discounts.

Fixed rounding policy: allocate the discounted sale total cumulatively across ordered lines, then across cumulative returned quantity, rounding down to currency precision at each boundary. The final eligible return receives the remaining cent. Total entitlements cannot exceed sale total. The existing payment reversal ceiling remains enforced. Historical recorded refunds are preserved; no retroactive ledger edits or catch-up refunds are performed.

This is fixture reconciliation, not certification of operational accounts. No real financial records were changed. Supplier settlement/purchase costing cannot be reconciled end to end because posting workflows are absent. Profit estimates remain explicitly incomplete until costing, expense and valuation policies are agreed; revenue is not certified as profit. Mixed payment allocation refund classification deserves further business review.
