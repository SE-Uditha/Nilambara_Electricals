# BRD module coverage

Source: [Repair Shop Management Final.pdf](../../../Repair%20Shop%20Management%20Final.pdf). Page references below use the physical PDF page number, starting with its cover as page 1. The PDF has 20 pages and is titled a project proposal internally. This map captures architecture ownership; it does not claim the business functionality is implemented in Step 1.

## Required module map

| Module directory | BRD source | Future responsibility |
| --- | --- | --- |
| `Dashboard` | Section 15, pp. 14-15; overview/objectives, p. 3 | Repair, sales, credit, and stock overview from real business data. Step 1 displays clearly marked empty statistics. |
| `Customers` | Section 4, p. 4 | Individual and company registration, required contact information, account history, balances, and customer statements. |
| `RepairReceipts` | Section 5, pp. 5-6 | Receiving details, automatic identifier and unique manual job number, internal priority, received-by user, advances, duplicate receipts, and atomic creation of the associated repair job. |
| `RepairJobs` | Section 6.1, p. 6; workflow, pp. 17-18 | Inspection, approval, technical details, assigned repairer, parts/work/labour, testing, completion, delivery, warranty-related extensions, and authoritative job lifecycle. |
| `JobCards` | Section 6.3, p. 7 | A6 job cards with essential job data and sufficient handwriting space; optional two-sided or two-page layout. |
| `Quotations` | Section 7, pp. 7-8 | Linked estimates, validity, terms, approval/rejection history, print/PDF, and reuse of approved details in jobs/invoices. |
| `JobTracking` | Section 6.2, pp. 6-7; section 8, p. 8 | Search by job/customer/mobile/item, status filters and current balances; display status history, with authorized changes delegated to the job lifecycle. |
| `Invoices` | Section 9, pp. 8-10 | Automatic invoice numbering, several jobs for the same customer, job-wise totals, cash/credit terms, delivery details, A5 preview/print, and duplicate labels. |
| `Payments` | Section 10, p. 10; invoice details, p. 9 | Advance, current, partial, and subsequent payments; cash/bank transfer/cheque methods, balances, payment receipts, controlled refunds/reversals, and account statements. |
| `CreditManagement` | Section 11, p. 10 | Credit invoices, payment history, remaining credit, collections by date, and customer credit monitoring using authoritative payment allocations. |
| `Inventory` | Section 12, pp. 10-11 | Spare-part catalogue, barcode/SKU, common repair/POS stock ledger, opening stock, receipts/issues/returns/adjustments/damage/counts, optional transfers, reorder alerts, and valuation. |
| `Suppliers` | Section 13, p. 12 | Supplier identity, contacts, opening balance, terms, account history, and outstanding balances. |
| `Purchases` | Section 13, p. 12 | GRNs, supplier invoices, purchase costs/discounts, supplier payments/credit, and stock increases on receipt. |
| `POS` | Objectives, p. 3; sections 12, 14-16 and 19, pp. 10-11, 13-15, 17; acceptance, p. 19 | Spare-parts sales against the shared stock ledger, linked payment and return flows, cashier access, and configurable receipt output. The BRD does not supply a separate detailed POS field list. |
| `Employees` | Section 14, pp. 12-13 | Staff registration, employment/contact information, active state, account linkage, and administrator-controlled account creation. |
| `RolesPermissions` | Section 14, p. 13; security, p. 16 | Administrator, receptionist, technician/repairer, cashier, storekeeper, and manager roles; per-user permissions and policy enforcement. Step 1 provides authentication only. |
| `Reports` | Section 15, pp. 14-15 | Repair, sales, income, financial, and stock reports; filters, print/PDF, selected Excel exports, and accurate cost-based profit estimates. |
| `AuditLogs` | Status history, p. 7; refunds, p. 10; database/security, pp. 15-16; recommendations, p. 18 | Actor/time history for significant changes, login/logout, financial reversals, stock actions, and sensitive operational actions. |
| `BackupRestore` | Section 17, p. 16 | Manual/scheduled backups, destination/history, file verification, confirmed restore, failure warnings, external copies, and restore testing. |
| `PrintTemplates` | Receipt/job/invoice details, pp. 6-10; section 19, p. 17 | Separate receipt, A6 card, quotation, A5 invoice, POS receipt, statement, payment receipt, and optional supplier templates; preview/PDF/reprint and layout settings. |
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

## Job status vocabulary to preserve for Step 2

Section 6.2 on pages 6-7 lists: Received; Pending Inspection; Under Inspection; Quotation Pending; Waiting for Customer Approval; Customer Contact Failed; Advance Payment Pending; Waiting for Spare Parts; Under Repair; Price Modification; Re-Assembling; Repair Completed; Ready for Collection; Customer Contact Failed After Ready; Delivered; Cancelled; Unable to Repair.

Each change must retain its actor and date/time. The transition rules and stable stored values will be designed before creating enums or tables. A label in the BRD does not establish which roles may perform each transition.

## Decisions and conditional requirements

- Pages 5-6 distinguish an automatic Job ID and manual Job Number, but also call the automatic value a Job Number. Preserve both concepts and agree their labels, formats, uniqueness, and lookup behavior in Step 2.
- Page 12 says supplier/purchase access is management-only, while page 13 lists purchases for the storekeeper. Resolve the permission scope with the business owner before implementing policies.
- The treatment of pre-invoice advances, payment allocation, job re-invoicing, cancellation, stock valuation, supplier settlement, and return rules needs explicit design. Do not infer these rules from a screen label.
- Expenses reports are conditional on an expenses module (p. 14). Stock transfers are optional (p. 11). Neither creates a new Step 1 business module or table.
- Page 18 recommends warranty notes, repeat repairs, notifications, overdue alerts, workload reports, approval history, and other enhancements. Plan these as separately agreed extensions. SMS/WhatsApp requires a selected provider and service terms.
- Print layouts may be supplied by the owner (pp. 6-7). Physical print testing and final layout agreement remain later acceptance tasks.
- Deployment package, manuals, training, support, commercial terms, data ownership, and handover obligations occur on pp. 18-20. They belong to later delivery planning and are not completed by creating this foundation.
