# Inventory, supplier and purchase workflows

Use the existing **Stock & Purchasing** sidebar section. Existing per-user permission overrides remain effective. No extra database or ledger is used.

## Register stock

Open **Inventory → Register part**. Create units, stock locations, brands and categories with the reference form if necessary. Set the SKU, optional barcode, selling price, quantity precision, stock/reorder levels, location and preferred supplier. An opening quantity posts an opening movement once; enter an opening unit cost only when it is actually known. An unknown cost remains unknown. Stock units cannot change after movements have been recorded.

Search by name, SKU or barcode. The part page shows movement history and permission-protected corrections, damage entries and physical counts. Corrections use signed quantities; damage removes stock. A count records the expected and actual quantity and posts only the difference. Every posted operation needs a reason and retains its history.

## Purchase and receive

An authorized employee registers the supplier, contacts, credit terms and actual opening balance. Editing a supplier cannot rewrite its opening financial history. Supplier detail pages show account entries and recent purchases/payments.

Create a purchase draft with one or more items, quantities, unit costs, supplier invoice reference, discount and other costs. A draft creates neither stock nor a payable. An employee with **purchase approval** finalizes it, recording the supplier payable once. Receivers then post each actual delivered line/location as a GRN; partial deliveries are supported. Receiving cannot exceed the remaining ordered quantity and cannot be posted twice with the same request key.

Purchase and GRN pages provide browser print, PDF and duplicate copies using the purchase print template. A GRN document contains the received quantity; it does not pretend the entire purchase was received.

## Repair parts

Authorized technicians request parts for jobs they may repair. Requests do not deduct or reserve stock. A stock issuer approves and issues an open request; only this step deducts stock. An issued customer quotation must be approved before parts can be issued. POS and repair issues use the same stock balance and product lock.

Before billing, an issuer can cancel an unissued request or return an entire unused, resalable issue. Confirm that the goods have physically returned. The original issue movement is retained with a linked return movement. Partial return of one repair issue is not yet provided. Record damaged goods through the damage workflow instead. Closed/billed job pricing and part use cannot silently change.

Approved quotation charges now transfer automatically to the job. Authorized billing staff with access to the job can set a labour selling charge on an unquoted/unbilled job. Selling charges and actual labour costs are separate. Issued part charges contribute to unquoted job totals without changing customer-approved quotation prices.

## Supplier payments and returns

Purchase approval permission is also required to post supplier payments, reverse them or record agreed return credit. Storekeepers' existing receive/stock permissions do not grant access to supplier money operations. Cash, bank and cheque tenders are supported, with bank/cheque references validated. Purchase and opening-balance settlements are separate allocation targets. Payments cannot exceed their target's outstanding amount. Reversals restore the liability and retain the original payment, allocation and audit trail.

Supplier returns remove only received, unreturned and available stock. A manager may enter the credit note amount already agreed with the supplier. This is a supplier account credit, not a cash refund. Historical purchases with repeated lines for the same part require source reconciliation before a supplier return; the system refuses ambiguous posting rather than guessing.

## Costing and acceptance

The owner selected **latest purchase cost** on 2026-10-20. New POS/repair cost snapshots and valuation use the latest posted purchase cost at the stock location; a known opening cost is a fallback. Old returns keep their original cost and do not replace a newer purchase cost. Previous financial snapshots are not rewritten.

Recorded-cost profit estimates reconcile actual issued parts, labour, POS costs, resalable returns, real refunds and recorded expenses. Missing/mismatched costs, ambiguous repair refunds and unallocated purchase-wide discounts/delivery costs keep estimates incomplete. The owner still needs to specify how purchase-wide adjustments should enter unit cost.

Tests and limitations: [pending completion report](../testing/pending-completion-report.md). An approved operational backup folder, physical printer/scanner tests and business-owner UAT remain outstanding. Google Drive credentials remain blank as requested. No production build, deployment or scheduler activation was performed.
