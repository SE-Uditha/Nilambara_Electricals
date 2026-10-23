# User manual

## About this manual
This guide describes the implemented application at the Step 12 follow-up snapshot, 20 October 2026. It covers staff operations, not deployment approval. Perform training with an approved isolated database. Figures and examples are instructions, not records to enter into the operational database. Access depends on your role and any individual overrides.

## 1. Accounts, roles and navigation
Sign in at the administrator-provided address using a username and password. Five failed attempts per username/IP per minute are rate limited. Public self-registration and public password recovery are disabled; ask an administrator for account recovery. Sign out on shared computers.

Receptionists normally register customers and receive repairs. Technicians see assigned jobs and update repair work. Cashiers handle invoices, collections and POS sales. Storekeepers manage inventory, purchase entry and receiving. Managers supervise jobs, quotations, credit, supplier operations and reports. Administrators control accounts, settings, permissions and backups. Individual permissions may differ. A hidden menu or forbidden action requires an administrator to review access, not a different URL.

## 2. Customers and account statements
Search before creating a customer. Choose individual or company and complete the displayed identity/contact fields. Review spelling, mobile numbers, address and company details before saving. Use the existing record for later work. Authorized staff can edit details or deactivate a customer rather than erasing history.

Opening balances represent verified pre-system debt or credit and require the relevant permission; they are not ordinary cash receipts. Authorized staff open the customer statement, choose the date range and inspect the brought-forward balance, transactions and closing balance. Print or export using the statement actions. A statement summarizes account movements; it does not create a payment.

## 3. Repair intake, receipts and job cards
Open Repair Receipts and create a receiving record. Select an active customer and receiver. Enter the unique manual job number, appliance/item, brand/model, reported fault, accessories, visible condition/damage and notes. Normal, Special and Urgent are internal job classifications. The manual number is separate from the automatic JOB reference and cannot be changed after creation.

If an authorized advance is genuinely received, record the amount and payment method with its required references. Save once. Receipt, job, initial status and job card are created atomically; an advance submitted with intake is included in that transaction. Use the receipt detail page to confirm success after a timeout before attempting a new submission.

Give the customer the A5 receipt and attach/use the one-page A5 job card internally. The technician completes the blank status, findings, repair, parts, cost, and handover fields by hand, then returns it to the cashier for system updates. Browser print and PDF actions are available. Check customer, appliance, accessories and signatures before handover. Duplicate output is marked and recorded in print history. The receipt preserves an intake snapshot even if the customer master record changes.

## 4. Repair work and quotations
An authorized manager assigns the job to an active technician. Technicians open their assigned jobs, record inspection and repair details, and update the available status actions. Follow the displayed allowed transitions. Retain the reason/history for cancellations and changes. Completion and delivery record timestamps when missing.

Create a quotation for the correct job/customer, with parts, labour and validity/notes as appropriate. Review totals before issuing. Record the actual customer decision using the authorized quotation approval workflow. Do not mark approval merely to bypass a stock restriction. Approved quotation charges transfer to the job; approval itself posts neither stock nor a payment. Customer-approved selling prices remain protected when parts are issued.

For an unquoted, unbilled job, authorized billing staff can enter the labour selling charge on the job. Actual labour cost is a separate measure used in recorded-cost estimates. Quoted, billed or closed job charges cannot be silently changed using that form.

## 5. Repair parts and unused returns
An authorized technician requests the required part and quantity for an accessible job. Requests do not reserve or deduct stock. A stock issuer checks the location, available quantity and job/quotation state, then approves and issues the request. The issue posts stock and retains its cost snapshot. POS and repairs consume the same balance, so a request can become unavailable before issue.

Before billing, cancel an unissued request if no longer needed. For an issued part, physically receive the entire unused, resalable issue back before using the return action. The original issue remains and a linked return is posted. Partial return of one repair issue is not provided. Use the damage workflow for damaged goods. Ask management to reconcile unsupported or billed cases; do not invent a balancing movement.

## 6. Invoices, advances and collections
Create an invoice for eligible jobs of the same customer and verify the line descriptions and charges. Multiple eligible jobs can share one invoice. Review cash/credit terms and issue the invoice using the authorized action. Existing job advances and eligible customer advances contribute through allocations; they are not new cash received at invoicing.

Open Payments to receive an invoice collection or customer/job advance. Select the correct customer and target, amount and method. Cash, bank transfer and cheque are supported; bank/cheque references must satisfy the form. Staff without receive-for-others permission record themselves as receiver. Save once and inspect the saved payment and allocation before retrying.

Partial collections leave an outstanding balance. Overpayment against a target is rejected. The invoice derives unpaid, partially paid or paid status from its allocations and releases. The payment form applies to one invoice at a time. Print/download the saved payment receipt and use duplicate output for later copies.

Credit Invoices lists outstanding credit for authorized staff. Review ageing, customer statements and collection history before following up. An authorized reversal retains the original payment and reason. An accounting reversal and an actual cash/bank refund have different financial effects; choose the action matching what occurred and escalate uncertainty to management. Cashiers do not receive reversal/refund authority by default.

## 7. Point of sale
Search by SKU, barcode, name, brand or category, or scan into the barcode input. Confirm the stock location, item, quantity precision and price in the cart. Authorized discounts require a dedicated permission. The server recalculates totals and checks stock at checkout; the screen's earlier quantity is not a reservation.

For cash, enter tendered money and confirm change. Only the sale total is posted as payment. Checkout atomically creates the sale, payment, allocation and stock movement. A repeated identical protected submission returns the saved sale; do not start another sale when the first may already exist.

Bank transfer and cheque checkout creates a pending draft. An authorized manager verifies receipt before stock and payment post. Recording a cheque does not mean it has cleared. Use the saved sale for receipt printing, verification, cancellation or permitted returns. For a return, identify the original sale and actual quantities, classify whether goods are resalable, and record the permitted refund. A resalable return restores stock at the original recorded cost; damaged goods must not increase saleable stock.

## 8. Inventory and physical controls
Open Stock & Purchasing, then Inventory. Register a part with SKU, optional barcode, unit/precision, selling price, location, reorder levels and preferred supplier. Create necessary units, locations, brands or categories using the permitted reference forms. Enter opening quantity only once and opening unit cost only when known. An unknown cost must remain unknown. A unit cannot change after stock movements exist.

Search the part and inspect movement history before posting adjustments. A correction uses a signed quantity and a reason. Damage removes stock. A physical count records expected and actual stock and posts their difference. Count the correct location; check recent sales/receiving before explaining a variance. Negative available stock is rejected. These actions retain history and require their respective permissions.

## 9. Suppliers, purchasing and GRNs
Authorized staff register suppliers, contact details, terms and verified opening balances. Editing contact details cannot rewrite opening financial history. Review supplier account entries and prior purchases/payments before entering new transactions.

Create a purchase draft with supplier, reference, line quantities/unit costs, discount and other costs. A draft creates no stock or payable. An employee with purchase approval finalizes it, posting the payable once. Storekeepers can enter/receive purchases under the existing matrix but cannot approve or post supplier money by default.

Receive only actual delivered quantities, against the correct purchase line and location. Each GRN submission receives one line/location; repeat for other lines or later partial deliveries. Receiving cannot exceed the unreceived quantity. Print/download the purchase or GRN from the saved document. A GRN shows its delivered quantity rather than implying the full purchase arrived.

Supplier payments settle either a purchase or an opening balance as separate targets, and cannot exceed that target's outstanding amount. An authorized full reversal retains the original payment and restores liability. Supplier returns remove only received, unreturned, available stock. Enter a credit amount only when explicitly agreed with the supplier; an account credit is not a cash refund. Historical purchases with repeated lines for the same part require source reconciliation before a return.

## 10. Reports, costing and printing
Authorized users open reports/dashboard, select the required dates and filters, and review the report basis before exporting. Financial reports require additional permission. Stock valuation and new transaction cost snapshots use the owner-selected latest purchase cost at the location, with a known opening cost as fallback. An old returned item's cost does not replace a newer purchase cost. Historical snapshots are preserved.

Profit is an estimate of recorded costs, not certified net income. Missing/mismatched costs, unresolved purchase-wide discount/freight allocation and ambiguous refund/return accounting keep affected estimates incomplete. Do not treat incomplete values as zero cost or distribute purchase extras using an unapproved policy.

Use the document's print/PDF action, check paper size/orientation and branding, then print at actual size where appropriate. Receipts and job cards use A5; job cards are portrait and leave technician findings, status, repair, parts, costs, and handover fields blank for handwriting. Return the completed job card to the cashier for system updates. POS and other documents follow their configured templates. Preview long text and Sinhala characters. Print history means a document was prepared, not proof that a physical printer completed it. Physical printer/scanner acceptance remains pending.

## 11. Corrections, errors and end of day
For validation errors, correct the highlighted fields and resubmit. For insufficient stock, verify the location and actual balance. A forbidden action requires permission review. After a timeout, search the saved document before creating another. Record the reference, approximate time and error when escalating. Do not send passwords, database credentials or confidential dumps in ordinary support messages.

At close, reconcile actual collections to receipts, review outstanding credit and pending POS verification, check stock exceptions, and have an authorized operator verify backup state. Sign out. Use explicit reversals/returns/corrections with reasons instead of deleting posted history.

## 12. Training and acceptance checklist
In an approved UAT database, practice customer registration, intake/advance, assignment, quotation approval, part issue, invoice/remaining payment, delivery, POS sale/return, partial GRN and stock count. Compare document totals, balances and movement histories. Check A5 job-card and receipt output, A4/A6/thermal output for configured documents, and an actual scanner with the shop hardware. Record expected result, actual result, tester and date. Owner sign-off is still pending; automated tests are not business acceptance.
