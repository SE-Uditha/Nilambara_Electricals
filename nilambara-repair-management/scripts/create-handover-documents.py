"""Build the versioned operator handover sources; render using installed DomPDF."""
from pathlib import Path
import json, re, subprocess

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'output/pdf'
SRC = ROOT / 'docs/manuals'
OUT.mkdir(parents=True, exist_ok=True)
SRC.mkdir(parents=True, exist_ok=True)
DATE = '20 October 2026'

quick = '''# Daily user guidance

## Start and sign in
Ask the system operator to start MySQL and the Laravel application. Open http://127.0.0.1:8000/login on the same computer, or the address provided by your administrator. Sign in with your assigned username and password. Email is not the login identifier. Menu visibility follows your permissions. Contact an administrator if access is missing; do not share another employee's account.

## Receive an appliance
1. Search Customers by name, mobile or customer reference before registering another record.
2. Open Repair Receipts and register the customer, receiving employee, unique manual job number, appliance and reported fault.
3. Record accessories, visible damage and special instructions accurately. Enter an advance only if money was actually received and your account is authorized.
4. Save once. The receiving record, job and job card are created together. Open the saved record before retrying after a connection interruption.
5. Print the A5 customer receipt and A6 job card, or use their PDF actions. Reprints are marked duplicates.

## Repair, bill and collect
1. An authorized manager assigns the job. Technicians work on their assigned jobs and record inspection, repair details and status.
2. Where a quotation is used, prepare it and record the authorized approval decision before issuing parts. Approval transfers the agreed selling charges to the job.
3. Request parts, then have an authorized stock issuer issue them. A request alone does not deduct stock.
4. For an unquoted, unbilled job, authorized billing staff enter the labour selling charge. Actual labour cost and customer selling charge are separate values.
5. Prepare and issue the invoice for eligible jobs belonging to the same customer. Verify totals and already recorded advances.
6. Receive the remaining payment, choosing cash, bank transfer or cheque and supplying required references. Partial collections are supported; never enter an advance again as a new receipt.
7. Check the invoice balance, print the payment receipt and record completion/delivery through the job workflow.

## Sell over the counter
Open POS, scan or search a part, confirm quantity and location, and review the cart. Cash checkout records tender and change but posts only the sale total. Bank/cheque sales remain pending until an authorized manager verifies payment; pending sales do not post stock. Discounts and returns require their own permissions. Use the recorded sale when printing or processing a return.

## Close the day
Review receipts, collections, outstanding credit, POS transactions and stock exceptions with an authorized manager. Investigate differences through document histories. Do not delete financial records to correct a mistake. Sign out. Ask the administrator to verify backup success; a configured schedule or a local backup does not prove that a Google Drive copy exists.

## Help and unfinished acceptance
Read the User Manual for detailed workflows and the Administrator Manual for setup and recovery. Record the document reference, action and visible error when requesting help, without sharing passwords. Physical printer/scanner acceptance, owner UAT, an approved operational backup destination and purchase-wide discount/freight cost allocation are pending. Google Drive is intentionally unconfigured.
'''

user = '''# User manual

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

Give the customer the A5 receipt and attach/use the A6 job card internally. Browser print and PDF actions are available. Check customer, appliance, accessories and signatures before handover. Duplicate output is marked and recorded in print history. The receipt preserves an intake snapshot even if the customer master record changes.

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

Use the document's print/PDF action, check paper size/orientation and branding, then print at actual size where appropriate. Receipt A5 and job card A6 are supported; POS and other documents follow their configured templates. Preview long text and Sinhala characters. Print history means a document was prepared, not proof that a physical printer completed it. Physical printer/scanner acceptance remains pending.

## 11. Corrections, errors and end of day
For validation errors, correct the highlighted fields and resubmit. For insufficient stock, verify the location and actual balance. A forbidden action requires permission review. After a timeout, search the saved document before creating another. Record the reference, approximate time and error when escalating. Do not send passwords, database credentials or confidential dumps in ordinary support messages.

At close, reconcile actual collections to receipts, review outstanding credit and pending POS verification, check stock exceptions, and have an authorized operator verify backup state. Sign out. Use explicit reversals/returns/corrections with reasons instead of deleting posted history.

## 12. Training and acceptance checklist
In an approved UAT database, practice customer registration, intake/advance, assignment, quotation approval, part issue, invoice/remaining payment, delivery, POS sale/return, partial GRN and stock count. Compare document totals, balances and movement histories. Check A5/A6/A4/thermal output and an actual scanner with the shop hardware. Record expected result, actual result, tester and date. Owner sign-off is still pending; automated tests are not business acceptance.
'''

admin = '''# Administrator manual

## 1. Scope and current state
This handover describes the existing local Laravel application as verified on 20 October 2026. It is not a production deployment or approval. The active workspace used for this verification is D:/xampp/htdocs/Nilambara_Electricals/nilambara-repair-management. Earlier F: and D: workspace references should be adjusted to the actual installation. Do not create another database merely to resolve a path mismatch.

The application uses Laravel 13, PHP 8.5 (dependencies require at least PHP 8.4), the MySQL connection with local XAMPP MariaDB 10.4.32, InnoDB and utf8mb4, and phpMyAdmin as an administration interface. The operational database is nilambara_repair_management. Application tables are defined by migrations; phpMyAdmin is not a second schema-authoring workflow.

## 2. Start the existing local installation
Start the existing MySQL service through XAMPP or the approved service configuration. Avoid starting a duplicate server on port 3306. In PowerShell, change to the application directory and use the tested PHP executable/configuration:

```powershell
cd D:\\xampp\\htdocs\\Nilambara_Electricals\\nilambara-repair-management
$env:PHPRC = (Resolve-Path ..\\.tools\\php.ini).Path
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Open http://127.0.0.1:8000/login. Existing compiled assets are sufficient if present; Vite development can be run separately with npm run dev when developing. Restart Artisan after .env changes. Ctrl+C stops the development server. This local development command does not establish unattended production hosting.

If the port is occupied, check the existing process before changing ports. A missing driver usually means the wrong PHP/configuration is loaded. If assets are missing, refer to README prerequisites and involve the developer; no production build/deployment is performed by this documentation task.

## 3. Database and change protection
Keep credentials in the local .env file, never this manual or a shared screenshot. Confirm DB_CONNECTION=mysql, DB_HOST/DB_PORT for the existing server and DB_DATABASE=nilambara_repair_management. Inspect php artisan migrate:status before upgrades. Inspect existing tables/data and pending SQL before any additive migration. Preserve APP_KEY and authentication tables. Do not use migrate:fresh, reset, operational rollback or demo seeders on the shop database.

phpMyAdmin can view the same tables when connected to the same server/database. Do not manually recreate application tables already described by migrations. The latest audit recorded 69 tables, 199 exact foreign-key constraints and zero orphan relationships. Before/after row counts matched; this is not a byte-for-byte record comparison.

## 4. Employees, accounts and permissions
Use Administration employee/user screens to create staff and optional linked accounts. An employee may have no login. Assign the minimum necessary role and a unique username; do not reuse another person's account. Use administrator password reset for recovery. Disabling an employee or account invalidates access and stored sessions while preserving history. Re-enabling an employee does not automatically re-enable a separately disabled login.

Only if no active administrator exists, php artisan app:create-user bootstraps the initial linked administrator. The interactive command requests a strong confirmed password. app:assign-initial-admin can promote an existing unassigned account with explicit confirmation when no active administrator exists. These commands refuse while an active administrator is present.

Use Roles & Permissions to review grants and individual overrides. Explicit denial takes precedence. An administrator account cannot receive individual overrides; final administrator protection prevents locking out all administration. Technicians remain restricted to assigned jobs. Existing customized role grants are preserved. Storekeeper receiving/stock permission does not grant purchase approval or supplier payments. Any change to approval authority requires an explicit business decision and a review of existing accounts.

## 5. Business settings and print templates
Authorized settings users maintain business name/address/telephone, PNG/JPEG logo, currency, timezone, language, formats, paper and footer. Database secrets and OAuth tokens are not ordinary editable settings. The supplied address is 482/A/3, Colombo Road, Kaduwela; verify any contact number rather than inventing one. Uploaded logos need writable storage and the configured public storage link.

Review active templates for each document: title, logo, margins, paper/orientation, font size, footer and duplicate marker. Template overrides may supersede business defaults for new documents. Check English/Sinhala, long descriptions, page breaks and signature areas. Receipt/card PDFs and statement/purchase/GRN PDFs were visually checked in fixtures; physical printer and scanner checks still need shop acceptance.

## 6. Backup configuration and daily checks
An approved private operational BACKUP_ROOT is still pending. Select a protected absolute folder outside application/web roots with the owner; restrict filesystem access. SQL dumps contain confidential business/authentication data. Destination metadata encryption does not encrypt the SQL dump itself. Use protected/encrypted storage. MYSQLDUMP_BINARY and MYSQL_BINARY must point to the actual clients, locally D:/xampp/mysql/bin.

Authorized staff use /admin/backups to create backups, inspect history, verify integrity and download only with download permission. Check completion, nonempty size and recorded SHA-256. A checksum is not proof of restore. Local backup success and cloud success are separate states. Retention defaults to 30 days/30 scheduled versions and protects manual copies; review failures and missed schedules.

Google Drive is intentionally inactive. Keep BACKUP_GOOGLE_DRIVE_FOLDER_ID, BACKUP_GOOGLE_DRIVE_EMAIL, BACKUP_GOOGLE_CLIENT_ID, BACKUP_GOOGLE_CLIENT_SECRET and BACKUP_GOOGLE_REFRESH_TOKEN blank until an approved account/folder and OAuth setup are provided. Do not claim an off-computer copy exists while blank.

Default policy time is 02:00 Asia/Colombo. The host scheduler has not been activated in this phase. After approval, an operator can review scripts/install-windows-scheduler.ps1 and configure one task invoking Laravel every minute with an appropriate account and private-folder permissions. Do not activate duplicate daily jobs. Confirm the computer, MySQL and service account remain available at the scheduled time.

## 7. Restore and incident handling
Restore is an authorized destructive recovery operation, not a routine test on live data. Obtain explicit approval for the target and recovery point and ensure a verified safety copy. The CLI command php artisan backup:restore ID requires confirmation, the exact database name, active administrator identity, restore permission and password verification. It creates a safety backup and uses maintenance mode.

Successful recovery reconnects, checks core tables/administrator identity and records recovery/audit history. Failure leaves maintenance mode enabled for investigation; preserve logs and involve the maintainer before reopening. Validate sample customer balances, stock, document histories, administrator access and backup metadata against the recovery plan. The isolated test proved fixture recovery, not recovery of the live shop database.

## 8. Audit, testing and support
Audit access is permission restricted. Search by actor/action/date/reference and retain relevant events for investigation. Posted financial and stock corrections preserve original records. Never clear logs or delete documents to hide an error. Report the business reference and time; redact credentials and confidential customer information when sharing support evidence.

The final disposable MySQL suite passed 218 tests and 3566 assertions. Native concurrency and actual test dump/restore checks passed separately. See the Test Report for evidence and limits. Tests must use isolated fixtures, not operational customer data. The developer verification helper is php scripts/verify-mysql.php --all-step11 --step12 --pending; review its safeguards and use only an approved local test environment. Do not change the runtime database to a test fixture database.

## 9. Open decisions and handover
Owner-selected costing is latest purchase cost. Purchase-wide discount/delivery allocation is not yet decided, so affected profit remains incomplete. Preserve the existing role matrix pending any approval-authority decision. Obtain the private backup destination, configure/verify real off-computer backup later, complete physical hardware checks and realistic-volume acceptance, then obtain owner UAT signatures. None of these approvals are implied by this manual.
'''

reports = ['pending-completion-report', 'integration-test-report', 'functional-test-report',
 'database-relationship-audit', 'security-test-report', 'financial-reconciliation-report',
 'inventory-reconciliation-report', 'performance-test-report', 'printing-test-report',
 'backup-restore-test-report', 'defect-register', 'uat-test-cases', 'final-brd-compliance-report']
test = '''# System test report

## Executive result and evidence date
The final isolated local verification on 20 October 2026 passed **218 tests with 3566 assertions in 175.11 seconds**, plus native multi-process concurrency and actual disposable database dump/restore checks. This report records the executed software checks. Business acceptance remains pending and no production readiness, exhaustive penetration testing or capacity certification is claimed.

## Reading the consolidated evidence
The following chapters reproduce the maintained test reports, with whitespace normalized. Some chapters retain original Step 12 findings followed by a Pending-work follow-up. The follow-up and the first completion chapter supersede earlier statements that stock/supplier workflows were absent or native database checks were still pending. This preserves traceability rather than erasing the audit history. UAT manual statuses remain pending even when automated coverage exists.

The authoritative execution log is storage/logs/pending-complete-mysql.log. Schema snapshots, preservation, syntax, style and PDF evidence are referenced by the completion chapter. The full requirement-by-requirement matrix is docs/testing/brd-compliance-matrix.md and the method inventory is docs/testing/automated-test-inventory.md in the delivered repository; these are detailed companion evidence rather than assertions that every BRD requirement is accepted.

## Environment and safety
Laravel 13 / PHP 8.5, the MySQL driver against local XAMPP MariaDB 10.4.32, InnoDB/utf8mb4. The helper created a randomized local disposable test database, exercised migrations/recovery there and removed only that fixture database. Operational tables were not reset or migrated during this verification. Matching before/after row counts do not prove byte-identical operational records.

## Acceptance still required
Approved operational backup destination; actual Google Drive configuration/upload; physical printer/scanner tests; realistic-volume capacity acceptance; owner UAT/signatures; purchase-wide discount/freight costing decision. No live operational restore, production build, scheduler activation, installer or deployment was performed.
'''
for name in reports:
    body = (ROOT / f'docs/testing/{name}.md').read_text(encoding='utf-8-sig')
    body = re.sub(r'\n\s*\n(?:\s*\n)+', '\n\n', body).strip()
    body = re.sub(r'(?m)^(\|[^\n]*)\n(?:[ \t]*\n)+(?=\|)', r'\1\n', body)
    test += '\n\n' + body + '\n'

documents = {'daily-user-guidance': quick, 'user-manual': user,
             'administrator-manual': admin, 'system-test-report': test}
manifest = []
for slug, content in documents.items():
    path = SRC / (slug + '.md')
    path.write_text(content, encoding='utf-8')
    manifest.append({'slug': slug, 'title': content.splitlines()[0][2:], 'date': DATE})
(SRC / 'manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
subprocess.run(['php', str(ROOT / 'scripts/render-handover-documents.php')], cwd=ROOT, check=True)

