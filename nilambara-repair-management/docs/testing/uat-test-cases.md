# User acceptance test cases

Tester for automated evidence: Codex local test runner, 2026-10-20. Business tester/signature: pending. Automation does not substitute for business acceptance. Execute manual cases only in an approved isolated/UAT database.

## UAT-01: Register individual and company

- BRD reference: 4.
- Preconditions: Receptionist with customer permission.
- Steps: Enter required contacts; save both types; search by ID/name/mobile.
- Expected: Unique IDs, validation and matching search results.
- Actual: Automated customer suite; manual sign-off pending.
- Status: Blocked for business acceptance.
- Tester: automated runner where cited; business tester pending.
- Date: 2026-10-20 audit; manual date pending.
- Evidence: `CustomerManagementTest`; final execution logs.

## UAT-02: Atomic receipt and job/card

- BRD reference: 5–6.
- Preconditions: Registered customer and intake role.
- Steps: Enter manual number, appliance, advance; save; repeat number; print A5/A6.
- Expected: One linked job/receipt, no duplicate advance; correct duplicate label.
- Actual: Automated intake/advance assertions; physical output pending.
- Status: Blocked for business acceptance.
- Tester: automated runner where cited; business tester pending.
- Date: 2026-10-20 audit; manual date pending.
- Evidence: `RepairReceiptAndJobManagementTest; PaymentManagementTest`; final execution logs.

## UAT-03: Inspection and quotation approval

- BRD reference: 6–8.
- Preconditions: Assigned technician and authorized decision recorder.
- Steps: Record inspection; draft/issue quote; approve; revise another quote.
- Expected: History appended; exact prices; no stock or payment created by approval.
- Actual: Automated quotation/inspection suite; manual acceptance pending.
- Status: Blocked for business acceptance.
- Tester: automated runner where cited; business tester pending.
- Date: 2026-10-20 audit; manual date pending.
- Evidence: `QuotationManagementTest`; final execution logs.

## UAT-04: Repair with approved part issue

- BRD reference: 12,20.
- Preconditions: Stock available; approved quotation.
- Steps: Approve part request; issue once; repeat request; complete job.
- Expected: Only issued approved quantity deducted once; auditable cost.
- Actual: Issue workflow missing.
- Status: Blocked for business acceptance.
- Tester: automated runner where cited; business tester pending.
- Date: 2026-10-20 audit; manual date pending.
- Evidence: `G12-01`; final execution logs.

## UAT-05: Multi-job invoice and payment

- BRD reference: 9–11.
- Preconditions: Two completed same-customer jobs; one advance.
- Steps: Issue invoice; partial cash/bank payment; collect balance; reverse authorized payment.
- Expected: No double billing/advance; total minus net payments; immutable original.
- Actual: Automated invoice/payment tests; manual acceptance pending.
- Status: Blocked for business acceptance.
- Tester: automated runner where cited; business tester pending.
- Date: 2026-10-20 audit; manual date pending.
- Evidence: `InvoiceManagementTest; PaymentManagementTest`; final execution logs.

## UAT-06: Delivery and reports

- BRD reference: 6,20.
- Preconditions: Paid repair ready for collection.
- Steps: Record collector/vehicle/date; deliver; filter reports and export.
- Expected: Correct delivery status/history and totals.
- Actual: Status/report tests exist; full workflow blocked by stock issue.
- Status: Blocked for business acceptance.
- Tester: automated runner where cited; business tester pending.
- Date: 2026-10-20 audit; manual date pending.
- Evidence: `RepairReceiptAndJobManagementTest; Step11ReportingTest`; final execution logs.

## UAT-07: POS sale and return

- BRD reference: 12,14,23.
- Preconditions: Cashier; manager; isolated stock.
- Steps: Scan; sell; retry checkout; return resalable/damaged parts; refund discounted sale.
- Expected: One sale/payment/movement; authorized exact refund; original retained.
- Actual: Automated POS and two-worker races; physical scanner/manual approval pending.
- Status: Blocked for business acceptance.
- Tester: automated runner where cited; business tester pending.
- Date: 2026-10-20 audit; manual date pending.
- Evidence: `POSManagementTest; verify-pos-concurrency.php`; final execution logs.

## UAT-08: Supplier purchase and partial receiving

- BRD reference: 13.
- Preconditions: Storekeeper/management scope agreed.
- Steps: Create supplier and draft purchase; partial GRN twice; pay partially; reverse payment.
- Expected: Draft no stock; each GRN posts once; correct supplier balance.
- Actual: Supplier/GRN/settlement services missing.
- Status: Blocked for business acceptance.
- Tester: automated runner where cited; business tester pending.
- Date: 2026-10-20 audit; manual date pending.
- Evidence: `G12-02`; final execution logs.

## UAT-09: Backup and disposable recovery

- BRD reference: 17.
- Preconditions: Approved destination and administrator.
- Steps: Create dump; verify hash; alter disposable probe; confirm/authenticate restore.
- Expected: Probe recovered, safety copy and audit present; no live DB change.
- Actual: Real automated disposable recovery executed; operational configuration pending.
- Status: Blocked for business acceptance.
- Tester: automated runner where cited; business tester pending.
- Date: 2026-10-20 audit; manual date pending.
- Evidence: `verify-step11-recovery.php`; final execution logs.

## UAT-10: Permissions and concurrent users

- BRD reference: 14,17,23.
- Preconditions: All six test-role users.
- Steps: Try allowed/direct denied URLs; run two cashiers on same stock/invoice.
- Expected: No unauthorized access, duplicate posting or overpayment.
- Actual: Role tests and selected native races; whole-shop manual pass pending.
- Status: Blocked for business acceptance.
- Tester: automated runner where cited; business tester pending.
- Date: 2026-10-20 audit; manual date pending.
- Evidence: `EmployeeManagementTest; verify-step12-concurrency.php`; final execution logs.




## Pending-work follow-up

The earlier descriptions of missing repair stock issue and supplier/GRN services are historical and are superseded. Automated workflow cases now pass in StockPurchasingWorkflowTest (including pricing transfer and customer advances), LatestPurchaseCostTest and PrintGapCompletionTest. Native two-user races pass in verify-pending-concurrency.php. Manual UAT statuses remain blocked/pending owner/hardware participation; they were not converted into signed business approval by automation.
