# Development roadmap

The supplied BRD defines the eventual system. Steps 1-4 establish the application, database, employee, authentication, authorization, and customer registration foundations. Later business phases remain a plan; database tables alone do not imply that business workflows, reports, or operational capabilities are implemented.

## Step 1: project foundation

- Laravel 13 with Blade, Livewire, Tailwind CSS, Vite, Fortify authentication, and MySQL environment defaults.
- Login/logout, protected dashboard, disabled public employee self-registration, and interactive initial account provisioning.
- Responsive administrative shell, grouped navigation, and clearly labelled empty dashboard metrics.
- Business configuration, English/Sinhala translation structure, and 21 empty module skeletons.
- Route/view extension points, documentation, frontend build, and foundation verification where the local environment permits.

The completion report records actual command results and any environmental limitations. MySQL credentials must work before default migrations can be verified against MySQL; SQLite test success alone cannot satisfy that check.

## Step 2: database architecture and ERD

Implemented: 58 new entity tables/models, 16 additive migrations, enum definitions, SQL constraints/indexes, Eloquent relationships, a complete ERD, all-field documentation, BRD traceability, and isolated database tests. Read the [database guide](../database/README.md), [verification results](../database/verification.md), and [open decisions](../database/open-decisions.md).

No CRUD, stock/payment processing, reports, or full permission enforcement is part of this step. Business policy choices remain explicit decisions; the implementation does not silently select an inventory valuation policy.

## Step 3: employee management, authentication, and role-based access control

Implemented in the existing app: username Fortify sign-in, employee records and linked accounts, private administrator bootstrap, six seeded roles, granular permissions and per-user overrides, permission-checked routes/navigation, technician record-level job policy, account/session deactivation, audit events, automated tests, and module documentation. Read the [Step 3 summary](step-3-summary.md) and its [employee](../modules/employee-management.md), [authentication](../modules/authentication.md), and [role-permission](../modules/role-permissions.md) guides.

The permission defaults are conservative where the BRD does not name an approver. Confirm the decisions documented in the role-permission guide before adding financial approval workflows. Step 3 does not imply that repair, inventory, or payment modules already exist.

## Step 4: customer and company registration

Implemented against the existing `customers` table and account ledger: individual/company registration, validated contact details, unique ULID customer IDs with configurable `CUS-` prefix, database-backed partial search/filter/pagination, details/edit pages, audited activation/deactivation, customer history counts, and a reusable active-customer search selector. Nonzero opening balances create one source ledger entry; subsequent direct edits are blocked. Statement and financial data require `customers.statement`; default grants allow Manager/Administrator, while Receptionist customer intake defaults to zero opening balance. See the [Step 4 summary](step-4-summary.md) and [customer guide](../modules/customer-management.md).

The local MySQL database already had the schema and remained unchanged by this step; no migration or customer seeding was required. Repair, quotation, and payment workflows remain out of scope.

## Step 5: repair receipt management, job registration, job cards, and initial job status tracking

Implemented: atomic receipt/job registration with a database-generated `JOB-` reference and immutable manual job number; customer/item/receiving snapshots; optional advance payment through the existing payment/allocation/ledger records; initial `Received` history; technician assignment; inspection and repair notes; all 17 BRD statuses with append-only history; paginated job/status search; A5 receipt print/reprint; and a one-page A5 portrait technician worksheet with print history and cashier handover fields. HTML print views use the browser's print dialog and job cards can also be downloaded as PDF.

The additive inspection-parts migration is pending until explicitly applied to the configured database. This step does not implement quotation approval, inventory deduction, final billing/totals, delivery processing, or Step 6.

See the [Step 5 summary](step-5-summary.md), [receipt guide](../modules/repair-receipts.md), [job guide](../modules/repair-jobs.md), [job-card guide](../modules/job-cards.md), and [status guide](../modules/job-status-tracking.md).

## Step 6: quotations and repair approval

Implemented: transactionally created drafts with database-generated `QUO-` numbers, exact decimal line/header calculations, spare-part price lookup without inventory deduction, immutable issue snapshots, validity/terms, revisions and supersession, append-only customer decision and audit history, cashier visibility restrictions, and A4/A5 browser/PDF output with Sinhala fonts. Issuing a quotation moves the job to `Waiting for Customer Approval`; recording approval moves it to `Under Repair`, while rejection moves it to `Customer Contact Failed`. A job with an issued quotation cannot be moved directly to `Under Repair` until that quotation is approved.

No invoice, payment, stock movement, or parts issue is created by quotation approval. Reuse of an approved quotation in later billing remains a subsequent workflow. See the [Step 6 summary](step-6-summary.md) and [quotation guides](../modules/quotation-management.md), [approval guide](../modules/quotation-approval.md), and [printing guide](../modules/quotation-printing.md).

## Step 7: invoices

Implemented: same-customer multi-job drafts and invoices, unique database-generated numbering, job-wise totals, approved-quotation price integration, issued job-part usage itemization where it reconciles to the job amount, draft updates, issue/cancellation lifecycle, audit/history, derived allocation-based payment balances, and A5 print/PDF with duplicate history. Invoice records may display prior advances and payments but do not accept or record tender. Full payment processing and credit collection are Step 8. See the [Step 7 summary](step-7-summary.md) and [invoice guides](../modules/invoice-management.md).

## Step 8: payments and credit

Implemented: transaction-safe cash/bank/cheque payment receipt; active employee receiver and acting-user audit; job/customer advances integrated with Step 5; advance application on invoice issue; multiple partial and credit collections; derived payment/invoice balances; credit invoice search/history; ledger statements with date filters/print/PDF; authorized partial refunds and full accounting reversal; allocation releases; A5 payment receipts/PDF/duplicate history; localized UI, role permissions, audit events, tests, and guides. Existing payment and ledger schema is reused without a new Step 8 migration or historical backfill. See the [Step 8 summary](step-8-summary.md) and [payment guides](../modules/payment-management.md), [advance guide](../modules/advance-payments.md), [credit guide](../modules/credit-invoice-management.md), [reversal guide](../modules/payment-reversals.md), and [statement guide](../modules/customer-account-statements.md).

MySQL deployment contention still needs dedicated verification; the feature suite runs against isolated SQLite and does not certify row-lock behavior under concurrent MySQL cashiers.

## Step 9: inventory and purchasing

Implement shared spare-part records and movement ledger, suppliers, GRNs/purchases, supplier credit/payments, issue-once repair parts, returns, damage/adjustments, counts, reorder alerts, and agreed valuation rules. Verify atomicity and concurrent stock usage. Invoice line snapshots do not post stock movements.

## Step 10: POS

Implemented: searchable/barcode-assisted spare-part checkout, cashier/manager permissions, exact decimal totals, cash tender/change, pending bank-transfer and cheque verification, idempotent checkout, POS sale/payment records, returns with resalable/damaged disposition, authorized refunds, sale history, duplicate-aware receipts/PDF, and operating summaries. POS uses the existing `stock_movements`, `payments`, `payment_allocations`, `stock_returns`, and `document_sequences` ledgers; it does not create parallel stock or payment balances. Pending bank/cheque transactions do not post a payment or stock movement until an authorized manager confirms receipt; cheque acceptance is not cheque clearing. See the [Step 10 summary](step-10-summary.md) and [POS guides](../modules/pos-management.md).

Step 9's broader purchasing, receiving, stock counts, adjustments, supplier returns, and valuation workflows remain planned. POS uses the existing shared movement ledger and does not claim those workflows are complete.

## Step 11: reporting and dashboard

Replace dashboard empty states with authorized queries. Implement the BRD repair, sales, financial, and stock reports; filters, print/PDF, and the selected Excel exports. Use actual costs for profit estimates, and add an expenses capability only if separately agreed.

## Step 12: operational controls and document customization

Implement audit history, scheduled/manual backup, backup verification, restore with confirmation, restore testing, external backup destinations, failure warnings, and authorized system/print settings. Validate Sinhala/English document fonts and A4/A5/A6 or thermal layouts using real print output.

## Step 13: acceptance and deployment

Execute the BRD's physical page 19 acceptance scenarios, including persistent history, concurrent users, numbering, multi-job billing, partial payments, credit, repair/POS stock deductions, returns, reports, backup restoration, and permissions. Prepare the agreed deployment package, installation/admin/user guides, training, ownership/handover documentation, and maintenance terms described on pages 18-20.

Business-owner acceptance is a later delivery milestone. Passing foundation tests does not establish acceptance of the entire BRD.

## Separately agreed enhancements

The recommendations on page 18 include warranty notes, repeat repair identification, SMS/WhatsApp, collection/overdue alerts, technician workload reporting, low-stock notifications, and approval history. Identify required overlaps with core audit/history first, then agree the remaining scope, provider dependencies, and cost before implementation.
