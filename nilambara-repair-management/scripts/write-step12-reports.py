"""Build reviewable audit documents from local evidence; never connect to MySQL."""
from pathlib import Path
import json
import re
from collections import Counter

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'docs/testing'
OUT.mkdir(parents=True, exist_ok=True)

def read(path):
    raw = (ROOT / path).read_bytes()
    return raw.decode('utf-16' if raw.startswith((b'\xff\xfe', b'\xfe\xff')) else 'utf-8-sig', errors='replace')

def write(name, content):
    (OUT / name).write_text(content.rstrip() + '\n', encoding='utf-8')

schema = json.loads(read('storage/logs/step12-schema.json'))
after_schema = json.loads(read('storage/logs/step12-schema-after.json')) if (ROOT/'storage/logs/step12-schema-after.json').exists() else None
logs = '\n'.join(read(p) for p in ['storage/logs/step12-mysql-final.log', 'storage/logs/step12-mysql-step12.log'] if (ROOT / p).exists())
suite_results = re.findall(r'Tests:\s+([^\r\n]+)', logs)
verified = 'passed' in logs and 'FAIL ' not in logs
V = 'Implemented and Verified' if verified else 'Implemented but Not Tested'
P = 'Partially Implemented'
M = 'Missing'
B = 'Blocked'
N = 'Not Applicable'
U = 'Implemented but Not Tested'
modules = {
 'Customers': ('customers; customer_account_entries', 'app/Modules/Customers', 'tests/Feature/CustomerManagementTest.php; tests/Feature/PaymentManagementTest.php'),
 'RepairReceipts': ('repair_receipts; repair_jobs; job_cards; payments', 'app/Modules/RepairReceipts', 'tests/Feature/RepairReceiptAndJobManagementTest.php; tests/Feature/PaymentManagementTest.php'),
 'RepairJobs': ('repair_jobs; job_inspections; job_status_histories', 'app/Modules/RepairJobs', 'tests/Feature/RepairReceiptAndJobManagementTest.php; tests/Feature/Database/BusinessConstraintsTest.php'),
 'Quotations': ('quotations; quotation_items; quotation_approvals; quotation_histories', 'app/Modules/Quotations', 'tests/Feature/QuotationManagementTest.php; tests/Feature/InvoiceManagementTest.php'),
 'Invoices': ('invoices; invoice_jobs; invoice_items; invoice_histories', 'app/Modules/Invoices', 'tests/Feature/InvoiceManagementTest.php; tests/Feature/PaymentManagementTest.php'),
 'Payments': ('payments; payment_allocations; payment_reversals; payment_allocation_releases; customer_account_entries', 'app/Modules/Payments', 'tests/Feature/PaymentManagementTest.php'),
 'Inventory': ('spare_parts; stock_movements; job_part_usages; stock_adjustments; stock_counts; stock_returns', 'app/Modules/Inventory', 'tests/Feature/Database/BusinessConstraintsTest.php; tests/Feature/POSManagementTest.php'),
 'Suppliers': ('suppliers; supplier_payments; supplier_account_entries', 'app/Modules/Suppliers', 'tests/Feature/Database/BusinessRelationshipsTest.php'),
 'Purchases': ('purchases; purchase_items; goods_received_notes; goods_received_note_items', 'app/Modules/Purchases', 'tests/Feature/Database/BusinessRelationshipsTest.php'),
 'POS': ('pos_sales; pos_sale_items; payments; stock_movements; stock_returns', 'app/Modules/POS', 'tests/Feature/POSManagementTest.php; scripts/verify-pos-concurrency.php'),
 'Employees': ('employees; users; roles; permissions; user_permission_overrides', 'app/Modules/Employees; app/Modules/RolesPermissions', 'tests/Feature/EmployeeManagementTest.php; tests/Feature/Auth/AuthenticationTest.php'),
 'Reports': ('repair_jobs; invoices; pos_sales; payments; stock_movements; account entries', 'app/Modules/Reports; app/Modules/Dashboard', 'tests/Feature/ReportsTest.php; tests/Feature/Step11ReportingTest.php; tests/Feature/Step12AuditTest.php'),
 'BackupRestore': ('backup_histories; backup_restores; audit_logs', 'app/Modules/BackupRestore; routes/console.php', 'tests/Feature/Step11BackupTest.php; tests/Feature/BackupManagementTest.php; scripts/verify-step11-recovery.php'),
 'PrintTemplates': ('print_templates; document_print_histories', 'app/Modules/PrintTemplates; resources/views/print', 'tests/Feature/PrintTemplateManagementTest.php; tests/Feature/InvoiceManagementTest.php; tests/Feature/POSManagementTest.php'),
 'System': ('system_settings; audit_logs', 'app/Modules/Settings; app/Modules/AuditLogs; app/Providers/FortifyServiceProvider.php', 'tests/Feature/SystemSupportTest.php; tests/Feature/Auth/AuthenticationTest.php; tests/Unit/AuditSafetyTest.php'),
}
rows = []
def add(section, module, status, items, remarks='', optional=False):
    tables, source, test = modules[module]
    for item in items.split(';'):
        if not item.strip(): continue
        index = sum(1 for r in rows if r[1] == section) + 1
        result = 'Passed within cited suite; see test inventory for individual assertions' if status == V and verified else 'Not verified end to end' if status in [P, U] else 'Not executed / unavailable'
        rows.append([f'BRD-{section:02}-{index:02}', section, item.strip(), 'Optional' if optional else 'Mandatory', module, tables, item.strip(), source, test, result, status, remarks])

add(1, 'System', P, 'Integrated repair and retail system;Permanent central customer and transaction records', 'Persistent schema exists; stock/purchase workflows prevent full integration.')
add(2, 'System', P, 'All twelve business objectives', 'See individual objectives in sections 4–19; no blanket completion claim.')
add(3, 'System', P, 'Fourteen BRD modules', 'Database modules exist; supplier/purchase/stock administration absent.')
add(4, 'Customers', V, 'Individual registration;Company registration;Automatic unique customer ID;Required name address and mobile validation;Search by name;Search by mobile;Search by customer code;Customer editing;Activation and deactivation;Customer history links;Customer statement and credit ledger')
add(4, 'Customers', U, 'Contact person;Alternative telephone;Email;Registration date;Notes', 'Fields exist; each field has not received a separate manual UAT pass.')
add(4, 'Customers', V, 'Authorized opening balance posted once')
add(5, 'RepairReceipts', V, 'Atomic receipt and job creation;Automatic Job ID;Unique manual job number;Appliance and customer selection;Required brand or model;Advance payment linkage;Registered receiver validation;Labour cost;A5 receipt;Duplicate reprint and history;Job type excluded from receipt output;Rollback on failed advance')
add(5, 'RepairReceipts', U, 'Received timestamp;Reported fault;Accessories;Physical condition;Visible damage;Signature area;Special notes', 'Present in intake/snapshot or printed layout; physical acceptance pending.')
add(6, 'RepairJobs', V, 'Immutable job identifiers;Technician assignment;Assigned technician authorization;Inspection records;Repair notes;All seventeen status values;Append-only status history;Changed-by actor and timestamp;A6 two-page writable job card')
add(6, 'RepairJobs', P, 'Parts used with quantity and cost;Completion and delivery workflow', 'No approved repair-part issue UI/service; status exists but full physical workflow blocked.')
add(7, 'Quotations', V, 'Create quotation;Edit draft;Unique quotation number;Job and customer link;Part quantity and unit pricing;Exact labour and total;Validity validation;Issue snapshot;Customer approval;Customer rejection;Approval history;Revision and supersession;Approval-gated repair start;No stock deduction on approval;No payment on approval;A4 PDF;A5 PDF;Approved values reused by invoice')
add(7, 'Quotations', U, 'Owner acceptance of terms and signature layout', 'Physical layout approval not supplied.')
add(8, 'RepairJobs', V, 'Search automatic and manual job number;Search customer and mobile;Search item;Status filters;Paginated job details')
add(8, 'RepairJobs', P, 'Job totals advances and outstanding visibility', 'Invoice balances tested; complete job-summary presentation not separately verified.')
add(9, 'Invoices', V, 'Automatic invoice number;Single job invoice;Multiple jobs per invoice;Same customer enforced;Prevent duplicate job billing;Job-wise exact charges;Approved quote snapshot;Invoice total;Advance and allocation balance;Cash and credit terms;Cashier identity;A5 print;Duplicate print;PDF export;Cancellation history;Issued snapshot immutable;Failed line insertion rolls back transaction')
add(9, 'Invoices', U, 'Collector;Vehicle number;Delivery date;Delivery information;Signature spaces;Special notes', 'Fields persist; individual business acceptance pending.')
add(9, 'Invoices', P, 'Current payment during invoice workflow', 'Invoice issue and payment capture are separate authorized operations; cancellation retains job reservation.')
add(10, 'Payments', V, 'Cash payment;Bank transfer;Cheque payment;Customer advance;Job advance;Partial payment;Repeated payments;Idempotent payment;Cross-customer allocation rejection;Derived outstanding;Net reversals;Authorized refund;Reversal audit;Decimal precision;No double-counted advance;Payment receipt')
add(11, 'Payments', V, 'Credit invoice list;Credit collection permissions;Partial credit collections;Outstanding balance;Customer account statement')
add(11, 'Payments', U, 'Owner acceptance of overdue credit policy', 'Due-date filter exists; no invented overdue escalation policy.')
add(12, 'Inventory', P, 'One shared repair and POS inventory;Spare part master schema;Unique SKU and barcode;Opening and current stock;Stock movement history;Low stock and reorder alerts;Stock valuation', 'POS uses stock ledger and reports work; maintenance/intake workflow absent, valuation policy incomplete.')
add(12, 'Inventory', M, 'Spare part registration UI;Stock receiving workflow;Approved repair-part issue;Supplier stock return workflow;Controlled stock adjustments;Damage posting workflow;Physical stock count posting;Repair issue cancellation and undo', 'Tables/models do not constitute functioning operational workflows.')
add(12, 'Inventory', V, 'POS deduction once;POS resalable return;No quotation stock deduction;No print stock deduction;POS overselling prevention')
add(12, 'Inventory', N, 'Stock transfer between locations', 'Optional in BRD; not implemented or required in this verification scope.', True)
add(13, 'Suppliers', M, 'Supplier registration;Supplier editing;Supplier search;Supplier credit terms workflow;Supplier payment entry;Partial supplier payment;Supplier payment reversal;Supplier purchase history workflow', 'Schema/models exist; no controller/request/service operational implementation.')
add(13, 'Purchases', M, 'Purchase draft entry;Purchase item entry;Discount and other-cost allocation;GRN creation;Partial receiving;GRN stock increase once;Supplier purchase credit settlement', 'No operational purchase or GRN posting services.')
add(13, 'Reports', P, 'Supplier statement;Supplier outstanding report;Purchase report', 'Read-only reports execute against fixtures/empty data; no end-to-end supplier source workflow.')
add(13, 'Employees', B, 'Supplier and purchase management access policy agreement', 'Section 13 says management only; section 14 also grants storekeeper purchases. Owner decision remains open.')
add(14, 'Employees', V, 'Employee registration;Unique employee code;Hashed login account;Case-insensitive username uniqueness;Employee active state;Administrator role;Receptionist role;Technician role;Cashier role;Storekeeper role;Manager role;Per-user permission denial;Backend route authorization;Assigned-job record authorization;Last administrator protection')
add(14, 'Employees', U, 'Employee address phone title department and join date', 'Model/form fields exist; separate field-by-field UAT not performed.')
add(14, 'Employees', P, 'Storekeeper purchase and stock operations', 'Permission definitions exist; operation modules absent.')
add(15, 'Reports', V, 'Daily jobs report;Pending job status filter;Under repair status filter;Completed job status filter;Delivered job status filter;Technician jobs report;Customer filter;Date range filter;Daily sales;Monthly sales;Yearly sales;Invoice sales;Repair income report;POS sales;Cash and credit report;Cashier sales;Payment collections;Advance report;Customer outstanding;Overdue credit;Discounts;Refunds on reversal date;Report PDF;Excel workbook export;CSV export', 'Query execution and report suite assertions; not every populated report combination has a separate manual pass.')
add(15, 'Reports', U, 'Waiting for parts filter;Ready for collection filter;Cancelled jobs filter;Unable to repair filter;Completion time report;Daily cash summary;Daily cash collection;Status filter;Employee filter;Item and category filter;Browser report print', 'Implemented report/filter code; these individual populated combinations were not separately asserted in this audit.')
add(15, 'Reports', P, 'Current stock report;Stock valuation;Low stock;Out of stock;Stock movement report;Repair parts report;POS parts report;Stock adjustment report;Purchase history;Supplier-wise purchases;Supplier outstanding;Purchase summary;Supplier statement;Profit estimate', 'Queries execute; missing source workflows and unapproved valuation/cost policy prevent complete reconciliation. Profit explicitly labelled incomplete.')
add(15, 'Reports', N, 'Expense entry and expense report business acceptance', 'BRD conditional on an implemented expense workflow; schema/report query alone does not establish that workflow.')
add(16, 'System', V, 'Primary keys;Foreign keys;Unique invoice-job reservation;Same-customer associations;Append-only transaction history;Transactional receipt and invoice rollback;Decimal financial storage;InnoDB;utf8mb4', 'Read-only operational metadata plus disposable constraint tests.')
add(16, 'System', P, 'Complete integrated transaction lifecycle', 'Purchase/GRN/repair stock issue missing.')
add(17, 'BackupRestore', V, 'Manual backup service;Backup file verification;History;Private-path restriction;Retention logic;Failure handling;Restore confirmation;Administrator password check;Real disposable restore;Restore audit', 'Real recovery helper uses disposable database and temporary storage only.')
add(17, 'BackupRestore', P, 'Automatic daily backup;Backup destination;Off-computer backup', 'Schedule definition 02:00 Asia/Colombo exists. Destination and Drive credentials blank; host scheduler not activated by this task.')
add(17, 'System', V, 'Password hashing;Login rate limit;CSRF protection;Active-account middleware;Audit access control')
add(17, 'System', U, 'Configurable idle session timeout', 'Optional; session defaults reviewed but no time-elapse acceptance run.', True)
add(18, 'System', P, 'Windows multiuser application;Central MySQL;English and Sinhala UI;Scanner support;A4 A5 A6 output;PDF and Excel support;User-friendly responsive interface', 'Local app and two-process races verified; physical scanner/printer and complete browser/responsive sweep not executed.')
add(18, 'System', N, 'Production hosting and online deployment', 'Explicitly excluded from Step 12; no deployment performed.')
add(19, 'PrintTemplates', V, 'Versioned template settings;Authorized template edits;Footer updates;Paper-size validation;Duplicate marker history;Quotation PDF;Invoice PDF;POS thermal PDF;POS A4 PDF;POS A5 PDF;Customer statement PDF;Payment receipt output')
add(19, 'PrintTemplates', P, 'Every branding and title setting applied consistently;A5 receipt PDF download;A6 card PDF download;Supplier document printing;Physical printer output;All signature and page break acceptance', 'Receipt/card provide browser print; supplier templates cannot verify missing workflows. Per-template setting propagation needs further acceptance review.')
add(20, 'System', P, 'Complete thirteen-stage repair workflow;Independent POS workflow;Supplier purchase workflow', 'Repair flow stops at operational stock issue; POS tested; supplier flow missing.')
add(21, 'System', U, 'Warranty tracking;Repeat repair tracking;SMS or WhatsApp API;Ready-for-collection notification;Overdue job alerts;Technician workload enhancements', 'Recommended optional extensions, not silently added as mandatory scope.', True)
add(21, 'System', P, 'Low stock notification enhancements;Approval history enhancements;Custom branding enhancements', 'Core reports/history/settings exist; proactive notifications not verified.', True)
add(22, 'System', P, 'Source code and documentation handover;User manual;Administrator manual;Setup guidance', 'Existing module documentation exists; formal handover and completeness review pending.')
add(22, 'System', B, 'Training;Signed UAT;License and warranty agreement', 'Needs owner/vendor participation; no signatures fabricated.')
add(22, 'System', N, 'Installer or agreed deployment package', 'Packaging and deployment expressly excluded from this step.')
add(23, 'System', B, 'Full repair acceptance;Full POS acceptance;Supplier acceptance;Physical printing acceptance;Backup operational acceptance;Concurrent-user acceptance;Written owner approval', 'Technical automation is evidence, not a business-owner signature. Missing workflows block full acceptance.')
add(24, 'System', B, 'Agreed software cost;Third-party recurring charges;Payment and commercial terms', 'Commercial agreement not provided; no amounts invented.')
add(25, 'System', B, 'Warranty duration;Support hours;Remote support agreement;Support fees;Recovery assistance;Future updates and migration agreement', 'Contractual commitments require agreement outside technical testing.')
add(26, 'System', P, 'Business data ownership;Recoverable data export;Maintainable source and documentation;Confidentiality;License terms and fee disclosure', 'Source and backup mechanisms exist; complete handover/contract evidence unavailable; no operational export performed.')
add(27, 'System', P, 'Complete integrated business solution', 'Mandatory inventory, supplier and purchase workflows are incomplete. No 100% compliance claim.')

header = ['Requirement ID','BRD section','Requirement','Scope','Module','Tables','Function','Source','Test case / suite','Result','Status','Remarks']
matrix = '# BRD compliance matrix\n\nReference: `Repair Shop Management Final.pdf`, all 27 numbered sections (20 physical pages). Audit date: 2026-10-20. Each row is a traceable requirement; suite references describe evidence boundaries, not independent owner sign-off. Field-only rows are conservatively untested where no individual assertion was reviewed.\n\n'
matrix += '| ' + ' | '.join(header) + ' |\n|' + '|'.join(['---']*len(header)) + '|\n'
matrix += '\n'.join('| '+' | '.join(str(v).replace('|','/') for v in row)+' |' for row in rows)
write('brd-compliance-matrix.md', matrix)

inventory = '# Executed automated test inventory\n\nSource-level test method inventory. Actual execution totals come from the logs below; the presence of a method alone is not a pass.\n\n'
for path in sorted((ROOT/'tests').rglob('*Test.php')):
    methods = re.findall(r'public function (test_\w+)\(', path.read_text(encoding='utf-8'))
    if methods:
        inventory += '## '+str(path.relative_to(ROOT)).replace('\\','/')+'\n\n'+'\n'.join('- `'+name+'`' for name in methods)+'\n\n'
write('automated-test-inventory.md', inventory)

table_count = len(schema['tables'])
fk_count = len(schema['foreign_keys'])
orphans = sum(key['orphans'] for key in schema['foreign_keys'])
bad_engine = [t['name'] for t in schema['tables'] if t['engine'] != 'InnoDB' or not t['collation'].startswith('utf8mb4_')]
dbdoc = f'''# Database relationship audit

Read-only audit of `nilambara_repair_management` on {schema['captured_at']}. No application migrations or business data writes were performed by this audit script.

- {table_count} tables inspected, {fk_count} foreign-key column mappings inspected, {orphans} orphan matches detected by per-column checks.
- Non-InnoDB/non-utf8mb4 tables: {bad_engine or 'none'}.
- Floating-point storage columns: {schema['floating_point_columns'] or 'none'}.
- Compound associations are additionally verified by BusinessConstraintsTest and BusinessRelationshipsTest in the disposable database. The per-column orphan scan alone does not prove compound-key integrity.
- Infrastructure tables may use string primary keys. No primary key/index changes were made.
- Operational migration state: `storage/logs/step12-migrations.log`; route inventory: `storage/logs/step12-routes.json`.
- Full PK/FK ERD: [existing ERD](../database/erd.md). No schema changes in Step 12, so no ERD relationship changes are necessary.

## Tables and indexes

| Table | Rows at read-only snapshot | Index names |
|---|---:|---|
'''
dbdoc += '\n'.join(f"| {t['name']} | {t['rows']} | {', '.join(t['indexes'])} |" for t in schema['tables'])
dbdoc += '\n\n## Declared foreign-key mappings\n\n| Child | Parent | Delete rule | Orphans |\n|---|---|---|---:|\n'
dbdoc += '\n'.join(f"| {k['child_table']}.{k['child_column']} | {k['parent_table']}.{k['parent_column']} | {k['delete_rule']} | {k['orphans']} |" for k in schema['foreign_keys'])
if after_schema:
    constraints = after_schema['foreign_key_constraints']
    dbdoc += f"\n\nFollow-up exact tuple audit inspected {len(constraints)} complete foreign-key constraints, including composite keys; orphan tuples: {sum(k['orphans'] for k in constraints)}. Before/after operational table row counts are identical (`step12-preservation.log`). Row-count equality is not a byte-for-byte data comparison.\n"
dbdoc += '\n\nFinancial, stock and history records use restrictive delete behavior tested in SchemaIntegrityTest; authentication/permission infrastructure has its own cascade rules. Polymorphic audit identities intentionally have no target FK. Models/relationships are tested separately; supplier/purchase relationships do not establish operational CRUD support.\n'
write('database-relationship-audit.md', dbdoc)

evidence = '\n'.join('- `'+s.strip()+'`' for s in suite_results) or '- Final execution not yet recorded.'
common = '''Evidence logs are under `storage/logs/`: `step12-mysql.log` (initial baseline), `step12-mysql-final.log` (full regression plus native concurrency/recovery), `step12-mysql-injection-failure.log` (retained intermediate test-injection failure), `step12-sqlite-targeted.log`, `step12-schema.json`, `step12-schema-after.json`, `step12-preservation.log`, `step12-syntax.log`, `step12-style.log`, `step12-composer.log`, `step12-dependency-audit.log`. Test fixtures are created only in newly randomized disposable databases. `scripts/verify-mysql.php` refuses production/nonlocal connections and never reuses an existing database.
'''
write('functional-test-report.md', '# Functional test report\n\n'+common+'\n## Recorded suite summaries\n\n'+evidence+'''

Initial MySQL baseline: 195 passed, one SQLite-only rollback test skipped, 3381 assertions, 163.65 seconds. An intermediate expanded run had 199 passes and one failure: MySQL trigger fault injection implicitly committed the test transaction (`step12-mysql-injection-failure.log`). Portable isolated model-event fault injection replaced the trigger; final totals above supersede these historical runs. No failing test was disabled.

Customer/company, intake/job/card, quotation/revision/approval, same-customer multi-job invoice, advances/partial/reversed payments, credit collection, POS return/refund, reports/settings/audit and backup code are exercised. See [test methods](automated-test-inventory.md) and [requirement matrix](brd-compliance-matrix.md) for bounded coverage.

Supplier registration, purchase drafts, GRNs, supplier settlement, inventory master UI, repair-part issue, adjustments and stock counts cannot be functionally accepted: tables/models exist but workflows are missing. No substitute financial or stock records were added to the operational database.

HTTP render assertions cover many pages but are not a complete manual click-through, responsive-browser or assistive-technology review. Existing UI was preserved.
''')
write('integration-test-report.md', '''# Integration test report

The tested path is customer → atomic receipt/job/card → inspection/quotation → approval → completed fixture job → multi-job invoice → advance/payment allocation → credit balance → reports. A completed fixture is not evidence that physical repair work or inventory issue occurred.

PaymentManagementTest verifies an advance posts once and is not counted again on invoice issue; InvoiceManagementTest verifies cross-customer prevention, quote price reuse, immutable issued snapshots and transaction rollback. QuotationManagementTest verifies no payment/stock side effects. POSManagementTest verifies sale/payment/allocation/movement atomicity, returns and refunds.

Native POS concurrency uses two PHP/MySQL workers: competing stock, unique sale numbers and identical retries. Additional Step 12 native invoice and payment races have their actual results in `step12-mysql-final.log`. Read the log before interpreting a race as passed. These checks do not establish a general multiuser capacity limit.

Blocked integrations: repair issue ↔ POS competition, purchase → GRN → inventory → supplier settlement, multiple concurrent GRNs and storekeeper issues. Two receptionist/technician races have not been executed. Source transactions alone are not concurrency evidence.
''')
write('security-test-report.md', '''# Security test report

Safe local testing only. AuthenticationTest executes password rejection, CSRF enforcement, five-attempt rate limiting, session regeneration, logout invalidation, disabled-account rejection and no public registration. EmployeeManagementTest tests all six role definitions, per-user denial precedence, direct route restrictions, assigned technician access and last-administrator protection. Payment/POS/backup tests exercise sensitive operation permissions.

Step12AuditTest sends an SQL-looking search value and verifies it remains data, then verifies stored script markup is escaped in customer details. Report sort fields are allowlisted. These narrow probes do not constitute exhaustive penetration testing of every field.

SystemSupportTest rejects unknown/secret editable settings; AuditSafetyTest covers redaction and historical log safety. Backup tests reject web-root destinations and traversal, conceal private paths, verify checksums and require authenticated restore confirmation. Google Drive transport is mocked; no actual account access is configured.

PHP syntax and Composer validation results are in their evidence logs. Composer audit reported no security vulnerability advisories at execution time; this is a dependency advisory result, not proof the application has no vulnerabilities. No package versions were changed.

Unverified: full hostile upload corpus, every stored/reflected XSS sink, end-to-end browser session timeout, local filesystem permission guarantees for every Windows account, production HTTPS/cookie configuration (outside this phase). No security mechanism was disabled to pass business tests. Supplier/stock authorization cannot be accepted for absent workflows. The conflicting management/storekeeper purchase rule requires owner agreement.
''')
write('financial-reconciliation-report.md', '''# Financial reconciliation report

Disposable-fixture assertions verify invoice total minus net applied allocations/eligible advances, allocation releases and reversals. Partial invoices remain outstanding; advances are applied once. Same-customer constraints prevent transferring another customer's money. Payment idempotency blocks duplicate collection and overpayment.

Report tests count a multi-job invoice once, exclude drafts, and use reversal date for negative collections without rewriting the original day. POS refund tests retain the original sale, reverse linked payments and preserve return audit/movement history. Step 12 adds exact final-cent tests for repeated partial returns and cross-line discounts.

Fixed rounding policy: allocate the discounted sale total cumulatively across ordered lines, then across cumulative returned quantity, rounding down to currency precision at each boundary. The final eligible return receives the remaining cent. Total entitlements cannot exceed sale total. The existing payment reversal ceiling remains enforced. Historical recorded refunds are preserved; no retroactive ledger edits or catch-up refunds are performed.

This is fixture reconciliation, not certification of operational accounts. No real financial records were changed. Supplier settlement/purchase costing cannot be reconciled end to end because posting workflows are absent. Profit estimates remain explicitly incomplete until costing, expense and valuation policies are agreed; revenue is not certified as profit. Mixed payment allocation refund classification deserves further business review.
''')
write('inventory-reconciliation-report.md', '''# Inventory reconciliation report

Stock is derived from one signed `stock_movements` ledger, not a new inventory database. POS tests assert exact sale deductions, one movement per source, current locking reads, rollback on failure, resalable returns only, damaged-return non-restocking, SKU/barcode lookup and no overselling. Quotation/invoice tests assert no unintended movement on approval/printing.

Native test: two cashiers each request four units from five available; exactly one succeeds and one unit remains. Further native scenarios verify distinct sale numbers and one sale/payment on identical retries. These are two concurrent processes, not a measured full-shop capacity.

Missing workflows: stock master maintenance, opening/receiving UI, approved repair issue, supplier returns, adjustment/damage/count posting and GRN stock receiving. Model integrity tests do not replace these. Repair/POS competing-stock and concurrent GRN scenarios are blocked. No manual operational stock adjustment was made for this audit.
''')
timing_path = ROOT/'storage/logs/step12-local-timings.json'
timing_doc = '# Local performance test report\n\n'
if timing_path.exists():
    timing = json.loads(read('storage/logs/step12-local-timings.json'))
    timing_doc += f"Driver: {timing['driver']}; fixture: {timing['fixture_customers']} customer, {timing['fixture_jobs']} jobs; {timing['users']} authenticated user; {timing['iterations']} iteration per measurement. Fixture construction excluded. Report timings include builder/summary and at most 50 returned rows; HTTP timings include test-kernel rendering. No network or browser latency is included.\n\n| Operation | Milliseconds |\n|---|---:|\n"
    timing_doc += '\n'.join(f'| {k} | {v} |' for k,v in timing['milliseconds'].items())
timing_doc += '''

These are small local observations with mostly empty financial/stock tables, not percentiles or load/capacity claims. Native two-cashier invoice/payment barrier timings are recorded separately in the concurrency log. The operating system and server are shared with test runs; timings may vary substantially.

Invoice creation, standalone payment allocation, stock updates, POS checkout and PDF generation have correctness tests and whole-test durations, but isolated operation latency was not instrumented. Do not treat PHPUnit test duration as a service benchmark. Missing repair issue/GRN services cannot be benchmarked. No statistically defensible slow-query/N+1 baseline at realistic volume was established, so no speculative index or calculation changes were made. Existing schema/index inventory is in the database audit.
'''
write('performance-test-report.md', timing_doc)
write('printing-test-report.md', '''# Printing and PDF verification

Automated checks: A5 invoice PDF signature/format and snapshot contents; A4/A5 quotation PDFs with Sinhala font; thermal/A4/A5 POS receipt PDFs; customer statement PDF; report PDF/workbook Unicode and money. Receipt and A6 card tests verify HTML paper styles, two-page writable card, duplicate label, print history, and snapshots. Versioned template changes and supported sizes are tested.

A5 receipt and A6 card currently provide browser printing; dedicated server PDF export is not verified. Payment receipt HTML exists. Supplier printing cannot be accepted without supplier/purchase workflows. Business title/font/logo/signature settings have not all been traced across every renderer, and statement paper settings require further acceptance review.

Prior Step 11 report PDF/dashboard screenshot artifacts exist under `storage/app/testing/`; they are historical visual evidence, not a new Step 12 browser sweep. This step generated PDFs through automated tests, but no fresh browser print-preview/manual page-break sweep or physical printer/scanner test was performed. English/Sinhala PDF tests establish renderability; they do not certify visual quality on every form, font, paper or device. No assets were built.
''')
write('backup-restore-test-report.md', '''# Backup and restore test report

Schedule definition: daily `0 2 * * *`, Asia/Colombo; configured retention 30 days, maximum 30 scheduled daily copies. Manual copies are protected. `step12-schedule.log` records schedule inspection. No production/Windows scheduler was installed or enabled.

Step11BackupTest verifies retention, latest recoverable copy protection, history retention, changed/missing/path-traversal detection, private destination checks, blank Drive disabling and mocked remote checksum/size verification. BackupManagementTest tests authorization, history and failures.

Native helper actually creates a nonempty MySQL dump, verifies SHA-256, rejects absent confirmation/wrong password, creates a safety dump, restores altered fixture data, checks recovered data/audit/history and maintenance cleanup. It runs only against the newly created disposable MySQL database and an isolated temporary storage path. Evidence: `PASS: real MySQL dump...` and `PASS: confirmation and wrong-password refusals...` in full-run logs. This is not a restore over the operational database.

Operational destination remains blank pending an explicitly approved location. Earlier automatic approval review rejected exporting live data to an unspecified/unapproved backup destination; no such export was performed in Step 12. Google Drive folder/account/OAuth credentials remain blank as requested. Real cloud upload, operational scheduled execution and off-computer recovery are therefore blocked, not passed. No credential values are reproduced here.
''')
write('defect-register.md', '''# Defect register

| ID | Severity | Module | Finding / root cause | Correction / verification | State |
|---|---|---|---|---|---|
| D12-01 | Medium | POS refunds | Rounding each return independently down can strand a cent; independent discounted line shares can also sum below paid total. Example: LKR 1 total, three units, three caps of 0.33. | Cumulative line and quantity entitlements; two regression tests assert 0.33+0.33+0.34 and 0.49+0.50 complete refunds without overpayment. No existing records changed. | Fixed in code; see final MySQL log for regression result. |
| D12-02 | Low | Test coverage | Invoice insert-failure rollback test was SQLite-only, leaving MySQL atomicity untested. First attempted trigger adaptation implicitly committed MySQL fixtures and failed. | Isolated model-event fault injection raises QueryException inside the transaction; original event dispatcher restored in finally, with no DDL. | Corrected and retested; final MySQL log is authoritative. |
| G12-01 | High | Inventory | Missing approved repair issue/receiving/adjustment/count workflows. | Record as missing; a full new module is outside a small audit correction. | Open |
| G12-02 | High | Suppliers / purchases | Missing supplier CRUD, purchase drafts, GRNs and settlement workflows. | BRD matrix records individual gaps. | Open |
| G12-03 | High | Recovery readiness | Operational backup destination and Drive account unset; no active host scheduler evidenced. | Isolated recovery verified; no unauthorized operational export or scheduler activation. | Blocked on configuration/approval |
| G12-04 | Medium | Print settings | Complete title/font/paper propagation and physical print acceptance not established; receipt/card PDF download not verified. | Retain working output, document bounded coverage. | Open / unverified |
| G12-05 | Medium | Accounting policy | Profit/valuation and mixed-allocation refund classification need agreed business treatment. | Incomplete-profit warning retained; no invented accounting assumptions. | Open |
| G12-06 | Medium | Concurrency and performance | Reception/technician races and realistic-volume capacity not measured. | POS and cashier races bounded to two workers; no unsupported throughput claims. | Open / untested |
| G12-07 | Medium | UAT | No owner approval or hardware print/scanner acceptance. | Prepared cases; no fabricated signatures. | Blocked |

D12-01 reproduction follows the original arithmetic and request-validation path; no historical refund was edited. Executed regression output is authoritative. No failed tests were hidden or removed.
''')

uat = '# User acceptance test cases\n\nTester for automated evidence: Codex local test runner, 2026-10-20. Business tester/signature: pending. Automation does not substitute for business acceptance. Execute manual cases only in an approved isolated/UAT database.\n\n'
cases = [
 ('UAT-01','4','Register individual and company','Receptionist with customer permission','Enter required contacts; save both types; search by ID/name/mobile','Unique IDs, validation and matching search results','Automated customer suite; manual sign-off pending','Blocked','CustomerManagementTest'),
 ('UAT-02','5–6','Atomic receipt and job/card','Registered customer and intake role','Enter manual number, appliance, advance; save; repeat number; print A5/A6','One linked job/receipt, no duplicate advance; correct duplicate label','Automated intake/advance assertions; physical output pending','Blocked','RepairReceiptAndJobManagementTest; PaymentManagementTest'),
 ('UAT-03','6–8','Inspection and quotation approval','Assigned technician and authorized decision recorder','Record inspection; draft/issue quote; approve; revise another quote','History appended; exact prices; no stock or payment created by approval','Automated quotation/inspection suite; manual acceptance pending','Blocked','QuotationManagementTest'),
 ('UAT-04','12,20','Repair with approved part issue','Stock available; approved quotation','Approve part request; issue once; repeat request; complete job','Only issued approved quantity deducted once; auditable cost','Issue workflow missing','Blocked','G12-01'),
 ('UAT-05','9–11','Multi-job invoice and payment','Two completed same-customer jobs; one advance','Issue invoice; partial cash/bank payment; collect balance; reverse authorized payment','No double billing/advance; total minus net payments; immutable original','Automated invoice/payment tests; manual acceptance pending','Blocked','InvoiceManagementTest; PaymentManagementTest'),
 ('UAT-06','6,20','Delivery and reports','Paid repair ready for collection','Record collector/vehicle/date; deliver; filter reports and export','Correct delivery status/history and totals','Status/report tests exist; full workflow blocked by stock issue','Blocked','RepairReceiptAndJobManagementTest; Step11ReportingTest'),
 ('UAT-07','12,14,23','POS sale and return','Cashier; manager; isolated stock','Scan; sell; retry checkout; return resalable/damaged parts; refund discounted sale','One sale/payment/movement; authorized exact refund; original retained','Automated POS and two-worker races; physical scanner/manual approval pending','Blocked','POSManagementTest; verify-pos-concurrency.php'),
 ('UAT-08','13','Supplier purchase and partial receiving','Storekeeper/management scope agreed','Create supplier and draft purchase; partial GRN twice; pay partially; reverse payment','Draft no stock; each GRN posts once; correct supplier balance','Supplier/GRN/settlement services missing','Blocked','G12-02'),
 ('UAT-09','17','Backup and disposable recovery','Approved destination and administrator','Create dump; verify hash; alter disposable probe; confirm/authenticate restore','Probe recovered, safety copy and audit present; no live DB change','Real automated disposable recovery executed; operational configuration pending','Blocked','verify-step11-recovery.php'),
 ('UAT-10','14,17,23','Permissions and concurrent users','All six test-role users','Try allowed/direct denied URLs; run two cashiers on same stock/invoice','No unauthorized access, duplicate posting or overpayment','Role tests and selected native races; whole-shop manual pass pending','Blocked','EmployeeManagementTest; verify-step12-concurrency.php'),
]
for test_id, ref, objective, pre, steps, expected, actual, status, evidence_item in cases:
    uat += f'## {test_id}: {objective}\n\n- BRD reference: {ref}.\n- Preconditions: {pre}.\n- Steps: {steps}.\n- Expected: {expected}.\n- Actual: {actual}.\n- Status: {status} for business acceptance.\n- Tester: automated runner where cited; business tester pending.\n- Date: 2026-10-20 audit; manual date pending.\n- Evidence: `{evidence_item}`; final execution logs.\n\n'
write('uat-test-cases.md', uat)

counts = Counter(row[10] for row in rows)
summary = f'''# Final Step 12 summary

Step 12 local verification is complete to the documented extent; the business system is **not fully BRD compliant or ready for unconditional acceptance**. Mandatory stock, supplier and purchase workflows remain missing. No production build, packaging, deployment or operational database reset/migration was performed.

## Executed evidence

{evidence}

{common}

Read-only schema audit: {table_count} tables, {fk_count} FK column mappings, {orphans} per-column orphan matches; follow-up exact composite constraint checks are in the database audit. No binary floating-point storage. All observed tables use InnoDB/utf8mb4. Real backup/restore and POS races have explicit PASS lines in logs. Additional invoice/payment race results are in the final MySQL log.

Two defects corrected: discounted POS return rounding and MySQL invoice rollback test coverage. Added security/query execution checks and bounded local timings. No operational business records were intentionally modified; the audit uses metadata/count reads and randomized disposable test databases.

## Coverage and remaining limits

The matrix contains {len(rows)} requirement rows across all 27 original BRD sections. Counts: {dict(counts)}. These are audit row counts, not weighted completion percentages. Recommended optional extensions and excluded production/commercial obligations are identified separately.

Supplier/GRN/repair issue workflows, complete financial costing policy, real Drive backup, approved local backup destination, physical printing/scanning, broad concurrency/capacity testing and owner UAT remain open. See [defects](defect-register.md), [UAT](uat-test-cases.md) and [final compliance report](final-brd-compliance-report.md). Stop after Step 12; no next-phase implementation is claimed.
'''
write('final-step-12-summary.md', summary)
write('final-brd-compliance-report.md', '''# Final BRD compliance report

## Executive assessment

The app has substantial tested repair, billing, POS and reporting functionality, but mandatory inventory issue, supplier and purchase/GRN workflows are incomplete. Full acceptance and 100% compliance are not established. This report covers all 27 BRD sections through the linked requirement matrix, with optional recommendations kept separate.

## Evidence and scope

'''+evidence+'\n\n'+common+'''

## Implemented modules

Authentication/employees/RBAC, customer/company records, repair intake/job/card, quotation approvals/revisions, same-customer multiple-job invoices, payments/credit/reversals, POS sale/returns, dashboard/reports, safe settings/versioned print settings, audit history and isolated backup/restore mechanisms have executable test evidence. Coverage boundaries are explicit in the matrix; module presence alone is not acceptance.

## Missing and partial functionality

Missing: operational supplier maintenance, purchase drafts/items, partial GRNs, supplier payments/reversals, stock administration and approved repair issue/count/adjustment workflows. Partial: shared-stock full repair integration, source-populated supplier reports, costing/profit, full print-setting propagation and field-by-field manual acceptance. Optional notifications/warranty/extensions remain outside mandatory completion unless separately agreed. Purchase role policy conflicts require owner resolution.

## Database and integration

Read-only live metadata and disposable native schema/relationship tests verify keys, decimals, engines and transaction constraints. The current ERD remains applicable because no schema changed. Receipt/job atomicity, quote/invoice snapshots, advances, reversals and POS ledgers are covered. The full repair→stock→supplier lifecycle is blocked by missing operations.

## Functional and security results

See functional, integration and security reports plus individual test inventory. Tests cover direct authorization, disabled accounts, session/CSRF/rate limit behavior, SQL-looking search input and stored HTML escaping. Dependency advisories and syntax were checked. This is bounded local assurance, not exhaustive penetration testing.

## Financial and stock accuracy

Fixture balances/net allocations, no double advances, POS refund ceilings, exact final-cent returns and selected stock races are tested. No operational accounting certification is asserted. Supplier cost/settlement and full repair stock issue reconciliation remain blocked; profit remains an incomplete estimate with policy warnings.

## Printing, backup and performance

PDF render assertions and HTML paper styles execute, including Sinhala font support. Fresh browser previews and physical hardware acceptance remain unverified. Native recovery tests use disposable MySQL and private temporary storage. Live destination/Drive credentials remain unset and no host scheduler was configured. Small-fixture report and HTTP timings are recorded, with no inferred capacity or unmeasured service latency claims.

## UAT and defects

Full repair, POS and supplier cases are prepared, with manual business acceptance marked blocked/pending. No tester signature or owner approval was fabricated. D12-01 fixes return rounding; D12-02 removes the MySQL rollback test coverage gap. Remaining defects/gaps are listed with severity in the defect register.

## Final technical assessment

Step 12 produced reviewable tests, corrections and evidence, while preserving existing data and UI. Acceptance requires the missing mandatory workflows, unresolved business policies, operational backup configuration and business/hardware UAT. No installer, build, deployment or next step was executed.
''')
modified = ['app/Modules/POS/Services/POSReturnService.php', 'tests/Feature/POSManagementTest.php', 'tests/Feature/InvoiceManagementTest.php', 'tests/Feature/Step12AuditTest.php', 'scripts/audit-step12-schema.php', 'scripts/verify-step12-concurrency.php', 'scripts/verify-mysql.php', 'scripts/write-step12-reports.py']
modified += [str(p.relative_to(ROOT)).replace('\\','/') for p in sorted(OUT.glob('*.md'))]
write('modified-files.md', '# Step 12 modified and added files\n\nThis lists only Step 12 changes; earlier uncommitted step work is not attributed to this audit. Runtime evidence under ignored storage/logs is listed separately in the reports.\n\n'+'\n'.join('- `'+p+'`' for p in modified))
print(f'Wrote {len(list(OUT.glob("*.md")))} reports and {len(rows)} matrix requirement rows.')
