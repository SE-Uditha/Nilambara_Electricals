# BRD module coverage

Current status (2026-10-20): the Step 12 follow-up adds the previously missing inventory, supplier and purchase/GRN operations and tests their integration. Use the [current compliance matrix](../testing/brd-compliance-matrix.md) and [completion report](../testing/pending-completion-report.md) for present verification. The step-specific descriptions below preserve historical architecture boundaries.

Source: [Repair Shop Management Final.pdf](../../../Repair%20Shop%20Management%20Final.pdf). Page references below use the physical PDF page number, starting with its cover as page 1. The PDF has 20 pages and is titled a project proposal internally. This map captures architecture ownership and current delivery boundaries. Steps 3-7 implement employee/authentication/RBAC, customer registration/management, repair intake/job workflow, quotation approval, and invoices; other capabilities remain planned unless explicitly delivered in a later phase.

## Required module map

| Module directory | BRD source | Future responsibility |
| --- | --- | --- |
| `Dashboard` | Section 15, pp. 14-15; overview/objectives, p. 3 | Repair, sales, credit, and stock overview from real business data. Step 1 displays clearly marked empty statistics. |
| `Customers` | Section 4, p. 4 | Step 4 delivers individual and company registration, required contact information, customer list/search/details/edit/deactivation, and linked-record counts. Step 8 adds permission-protected ledger statements with date filtering, print, and PDF. |
| `RepairReceipts` | Section 5, pp. 5-6 | **Steps 5 and 8:** receiving details, automatic `JOB-` reference and unique manual job number, internal priority, received-by employee, authorized advances posted through payment services, immutable receipt snapshot with payment reference, atomic linked job creation, A5 browser print and duplicate print history. |
| `RepairJobs` | Section 6.1, p. 6; workflow, pp. 17-18 | **Step 5:** technician assignment, inspection and repair notes, status changes/history, job search and filters. Quotation approval, stock deduction, final totals, testing, delivery, and warranty-related extensions remain planned. |
| `JobCards` | Section 6.3, p. 7 | **Step 5:** one-page A5 portrait browser/PDF worksheet with prefilled intake details and blank technician repair/status fields for cashier handover; print history and duplicate label. |
| `Quotations` | Section 7, pp. 7-8 | **Step 6:** job/customer linked drafts, unique database-generated numbers, exact totals, validity and terms, immutable issued snapshots, revisions/supersession, customer decision and audit history, approval-gated repair start, cashier visibility, A4/A5 browser/PDF output. Inventory, invoice, payment, and approved-data reuse workflows remain deferred. |
| `JobTracking` | Section 6.2, pp. 6-7; section 8, p. 8 | **Step 5:** search and pagination by job/customer/mobile/item with status filters and status history; authorized changes are recorded on the job lifecycle. Final totals and outstanding balances await billing. |
| `Invoices` | Section 9, pp. 8-10 | **Steps 7-8:** database-generated invoice numbering, same-customer multi-job draft/issue, approved-quotation price snapshots, A5/PDF, cancellation/audit history, derived balances, and receipt/allocation integration. |
| `Payments` | Section 10, p. 10; invoice details, p. 9 | **Step 8:** advance, current, partial and subsequent payments; cash/bank transfer/cheque; transaction-safe allocations and balances; payment receipts; permission-controlled refunds/reversals; audit history and customer statements. |
| `CreditManagement` | Section 11, p. 10 | **Step 8:** filtered credit invoice list, payment history, derived outstanding amounts, repeated partial collections, and customer credit monitoring using invoice/payment records. |
| `Inventory` | Section 12, pp. 10-11 | Spare-part catalogue, barcode/SKU, common repair/POS stock ledger, opening stock, receipts/issues/returns/adjustments/damage/counts, optional transfers, reorder alerts, and valuation. |
| `Suppliers` | Section 13, p. 12 | Supplier identity, contacts, opening balance, terms, account history, and outstanding balances. |
| `Purchases` | Section 13, p. 12 | GRNs, supplier invoices, purchase costs/discounts, supplier payments/credit, and stock increases on receipt. |
| `POS` | Objectives, p. 3; sections 12, 14-16 and 19, pp. 10-11, 13-15, 17; acceptance, p. 19 | Spare-parts sales against the shared stock ledger, linked payment and return flows, cashier access, and configurable receipt output. The BRD does not supply a separate detailed POS field list. |
| `Employees` | Section 14, pp. 12-13 | Step 3 delivers staff registration, employment/contact information, active state, account linkage, and administrator-controlled account creation. |
| `RolesPermissions` | Section 14, p. 13; security, p. 16 | Step 3 delivers Administrator, Receptionist, Technician, Cashier, Storekeeper, and Manager roles, granular permissions, per-user overrides, and backend authorization. |
| `Reports` | Section 15, pp. 14-15 | Repair, sales, income, financial, and stock reports; filters, print/PDF, selected Excel exports, and accurate cost-based profit estimates. |
| `AuditLogs` | Status history, p. 7; refunds, p. 10; database/security, pp. 15-16; recommendations, p. 18 | Steps 3-8 record employee/account/role/permission, repair, quotation, invoice, payment, allocation release, refund/reversal, and print events. Stock and restore events remain future work. |
| `BackupRestore` | Section 17, p. 16 | Manual/scheduled backups, destination/history, file verification, confirmed restore, failure warnings, external copies, and restore testing. |
| `PrintTemplates` | Receipt/job/invoice details, pp. 6-10; section 19, p. 17 | Separate receipt, A5 portrait job card, quotation, A5 invoice, POS receipt, statement, payment receipt, and optional supplier templates; preview/PDF/reprint and layout settings. |
| `Settings` | System modules, p. 4; sections 18-19, pp. 16-17 | Business identity/logo, language, currency, timezone, document settings, display defaults, and future authorized editing. Step 1 supplies configuration files only. |

The BRD combines some capabilities into 14 named sections on page 4. The 21-module structure separates their ownership as required by the Step 1 request. Purchases and POS are explicit modules even though POS is described across several BRD sections.

## Cross-module requirements

| Requirement | Owners and source |
| --- | --- |
| Permanent central records and historical retrieval | Every owning module; sections 1 and 16, pp. 3, 15-16. |
| Receipt-to-job consistency; immutable job identifiers | `RepairReceipts` and `RepairJobs`; pp. 5-6, 16. |
| Multi-job invoices and multiple payments | `Invoices`, `RepairJobs`, `Payments`, `CreditManagement`; pp. 9-10, 16. |
| One stock source with issue-once behavior | `Inventory`, `RepairJobs`, `Purchases`, `POS`; pp. 10-12, 16. |
| Transactions, cancellation, reversal, and history | Owning modules plus `AuditLogs`; pp. 7, 10-11, 16. |
| Desktop-first, multiuser, central database, online-capable interface | Application architecture and deployment; p. 16. |
| English/Sinhala, barcode scanner, A4/A5/A6, PDF/Excel | `Settings`, `Inventory`/`POS`, `PrintTemplates`, `Reports`; pp. 15-17. |
| Workflow and business acceptance | All modules; workflow pp. 17-18 and acceptance p. 19. These are future end-to-end acceptance criteria. |

Step 6 records customer decisions communicated by authorized staff; it does not capture or verify an electronic signature. Issuing a quote sets `Waiting for Customer Approval`; approval sets `Under Repair`; rejection sets `Customer Contact Failed`. The approval path records quotation and job approval histories atomically and uses the existing job-status history service.

## Step 7 invoice boundary

Only `Repair Completed` and `Ready for Collection` jobs are billable. A job with an issued, approved quotation must still match its approved quote total; mismatches are rejected because no price-change approval workflow exists. Draft job links reserve their jobs. Cancellation preserves the invoice and its job reservations, so re-invoicing remains blocked under the existing schema. Invoice payment status is derived from net allocations/releases, including eligible job advances. Invoice issue posts its receivable and applies available customer advances; it does not collect new tender or change delivery status.

## Job status vocabulary and Step 5 boundary

Section 6.2 on pages 6-7 lists: Received; Pending Inspection; Under Inspection; Quotation Pending; Waiting for Customer Approval; Customer Contact Failed; Advance Payment Pending; Waiting for Spare Parts; Under Repair; Price Modification; Re-Assembling; Repair Completed; Ready for Collection; Customer Contact Failed After Ready; Delivered; Cancelled; Unable to Repair.

All 17 statuses are represented by the existing enum; every change appends the prior/new value, actor, timestamp, and optional note. Status authorization is permission-checked, but no general transition graph is enforced. Step 6 specifically blocks `Under Repair` when an issued quotation lacks approval. Receipt output remains an HTML print view; job cards use the A5 portrait worksheet and support browser print/PDF; quotation output supports HTML and PDF. Physical print acceptance and final layout approval remain later work.

## Decisions and conditional requirements

- Pages 5-6 distinguish an automatic Job ID and manual Job Number, but also call the automatic value a Job Number. Preserve both concepts and agree their labels, formats, uniqueness, and lookup behavior in Step 2.
- Page 12 says supplier/purchase access is management-only, while page 13 lists purchases for the storekeeper. Resolve the permission scope with the business owner before implementing policies.
- Full pre-invoice advance/payment processing, job re-invoicing after cancellation, stock valuation, supplier settlement, and return rules need explicit design. Step 7 reads existing payment allocations for display only; do not infer that payment capture or ledger posting is implemented.
- Expenses reports are conditional on an expenses module (p. 14). Stock transfers are optional (p. 11). Neither creates a new Step 1 business module or table.
- Page 18 recommends warranty notes, repeat repairs, notifications, overdue alerts, workload reports, approval history, and other enhancements. Plan these as separately agreed extensions. SMS/WhatsApp requires a selected provider and service terms.
- Print layouts may be supplied by the owner (pp. 6-7). Physical print testing and final layout agreement remain later acceptance tasks.
- Deployment package, manuals, training, support, commercial terms, data ownership, and handover obligations occur on pp. 18-20. They belong to later delivery planning and are not completed by creating this foundation.
