# Development roadmap

The supplied BRD defines the eventual system. Steps 1 and 2 establish the application and database foundations. Later phases remain a plan; database tables alone do not imply that business workflows, reports, or operational capabilities are implemented.

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

Implement administrator-controlled staff accounts, employee management, agreed role/permission evaluation, per-user overrides, and authorization policies. Verify denied access as well as allowed actions. Customer CRUD and later business workflows require their own authorized stage.

## Step 4: repair intake and job lifecycle

Implement atomic receipt/job creation, concurrency-safe identifiers, receiving details and advances, repair details, technician assignment, inspection, the agreed status transitions/history, status search, A5 receiving output, and A6 handwritten job cards. Verify receipt reprints and duplicate labels where required.

## Step 5: quotations and repair approval

Implement quotation versions/history, validity and terms, customer approval/rejection, reusable approved job/invoice details, and print/PDF output. Enforce approval before repair for the jobs that require it.

## Step 6: inventory and purchasing

Implement shared spare-part records and movement ledger, suppliers, GRNs/purchases, supplier credit/payments, issue-once repair parts, returns, damage/adjustments, counts, reorder alerts, and the agreed valuation rules. Verify atomicity and concurrent stock usage.

## Step 7: billing, payments, and credit

Implement multi-job invoices for one customer, payment/advance allocation, partial collections, outstanding balances, credit monitoring, controlled refund/reversal, delivery details, statements, and A5 invoice/payment output. Reconcile every total against payment and job records.

## Step 8: POS

Implement spare-part sales using the same inventory and payment operations, barcode workflow where required, returns, cashier authorization, and the agreed POS receipt sizes. Confirm that repair issues and POS sales cannot deduct the same stock twice.

## Step 9: reporting and dashboard

Replace dashboard empty states with authorized queries. Implement the BRD repair, sales, financial, and stock reports; filters, print/PDF, and the selected Excel exports. Use actual costs for profit estimates, and add an expenses capability only if separately agreed.

## Step 10: operational controls and document customization

Implement audit history, scheduled/manual backup, backup verification, restore with confirmation, restore testing, external backup destinations, failure warnings, and authorized system/print settings. Validate Sinhala/English document fonts and A4/A5/A6 or thermal layouts using real print output.

## Step 11: acceptance and deployment

Execute the BRD's physical page 19 acceptance scenarios, including persistent history, concurrent users, numbering, multi-job billing, partial payments, credit, repair/POS stock deductions, returns, reports, backup restoration, and permissions. Prepare the agreed deployment package, installation/admin/user guides, training, ownership/handover documentation, and maintenance terms described on pages 18-20.

Business-owner acceptance is a later delivery milestone. Passing foundation tests does not establish acceptance of the entire BRD.

## Separately agreed enhancements

The recommendations on page 18 include warranty notes, repeat repair identification, SMS/WhatsApp, collection/overdue alerts, technician workload reporting, low-stock notifications, and approval history. Identify required overlaps with core audit/history first, then agree the remaining scope, provider dependencies, and cost before implementation.
