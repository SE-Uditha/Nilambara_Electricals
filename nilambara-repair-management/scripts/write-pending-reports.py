"""Refresh Step 12 findings only from a completed isolated verification run."""
from pathlib import Path
import json
import re

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'docs/testing'

def read(path):
    raw = (ROOT / path).read_bytes()
    return raw.decode('utf-16' if raw.startswith((b'\xff\xfe', b'\xfe\xff')) else 'utf-8-sig', errors='replace')

def write(name, text):
    (OUT/name).write_text(text.rstrip()+'\n', encoding='utf-8')

log = read('storage/logs/pending-complete-mysql.log')
if 'Disposable MySQL-compatible suite, rollback, and remigration succeeded.' not in log or re.search(r'^\s+FAILED\s', log, re.M):
    raise SystemExit('Final verification is incomplete; reports were not promoted to completed status.')
result = re.search(r'Tests:\s+([^\r\n]+)', log).group(1).strip()
duration = re.search(r'Duration:\s+([^\r\n]+)', log).group(1).strip()
races = '\n'.join('- '+line for line in log.splitlines() if line.startswith('PASS:'))
evidence = f'''Final native MySQL result: **{result}**, duration {duration}. Full evidence: `storage/logs/pending-complete-mysql.log`. The helper created its own randomized database, ran tests, rolled back/remigrated that disposable database, ran native multiuser/recovery checks, then removed only that database. Operational tables were not migrated/reset.

Focused evidence: `pending-focused-final.log`; PDF dimensions/fonts: `pending-pdf-review.json`; before/after operational schema/count evidence: `pending-schema-before.json`, `pending-schema-after.json`, `pending-preservation.log`; syntax/style evidence: `pending-syntax.log`, `pending-style-check.log`. All names are under `storage/logs/`.
'''
limits = '''An approved live-backup folder has not been supplied; Google Drive folder/account/OAuth fields remain blank as requested. No host/production scheduler, production build, installer, packaging or deployment was performed. Physical printer/scanner checks, realistic-volume capacity acceptance and owner signatures remain pending. Latest purchase cost was explicitly selected by the owner. Purchase-wide discount/freight allocation remains a separate unanswered decision; affected profit estimates remain incomplete rather than assuming a policy. Existing role permissions were preserved: storekeepers can receive/issue/count/adjust, while purchase approval and supplier money operations require `purchases.approve`.
'''

# Keep all original requirement IDs and distinguish implemented code from owner acceptance.
matrix = read('docs/testing/brd-compliance-matrix.md')
updated = []
for line in matrix.splitlines():
    if line.startswith('| BRD-'):
        cells = [value.strip() for value in line.strip('|').split('|')]
        if len(cells) == 12:
            requirement, module = cells[2], cells[4]
            verified = cells[10] == 'Missing' and module in ['Inventory','Suppliers','Purchases']
            if requirement == 'Discount and other-cost allocation':
                cells[10] = 'Partially Implemented'
                cells[11] = 'Purchase total includes discount/other costs; unit-cost allocation awaits the owner decision.'
                verified = False
            if requirement in ['A5 receipt PDF download','A6 card PDF download','Supplier document printing','Approved Quotation Data Transfer']:
                verified = True
            if verified:
                cells[8] = 'tests/Feature/StockPurchasingWorkflowTest.php; tests/Feature/PrintGapCompletionTest.php; scripts/verify-pending-concurrency.php'
                cells[9] = 'Passed in final disposable MySQL verification'
                cells[10] = 'Implemented and Verified'
                cells[11] = 'Follow-up workflows preserve existing ledgers, original records and history. See pending-completion-report.md for bounded return/costing limitations.'
                if module == 'Inventory':
                    cells[6] = 'StockWorkflowService::register/request/issue/adjust/count/cancelUsage; PurchaseWorkflowService::receive/returnStock'
                elif module == 'Suppliers':
                    cells[6] = 'SupplierController::save/index/show; SupplierPaymentService::pay/payOpening/reverse'
                elif module == 'Purchases':
                    cells[6] = 'PurchaseWorkflowService::create/finalize/receive; SupplierPaymentService::pay'
            if requirement in ['One shared repair and POS inventory','Opening and current stock','Stock movement history']:
                cells[9],cells[10],cells[11] = 'Passed native shared-stock tests','Implemented and Verified','Same ledger and product lock are used by POS, GRNs, adjustments, counts and repair issues.'
            if requirement == 'Stock valuation':
                cells[8] = 'tests/Feature/LatestPurchaseCostTest.php'
                cells[9],cells[10],cells[11] = 'Latest purchase cost and old-return regression passed','Implemented and Verified','Per-location latest posted purchase cost, with explicit known opening-cost fallback. Unknown costs are not valued at zero.'
            if requirement == 'Profit estimate':
                cells[9],cells[10],cells[11] = 'Recorded-cost repair/POS reconciliation passed','Partially Implemented','Known complete fixtures produce numeric estimates; unknown costs, unmatched manual costs, unallocated purchase adjustments and ambiguous repair refunds remain incomplete.'
            if requirement in ['Parts used with quantity and cost','Completion and delivery workflow','Storekeeper purchase and stock operations','Complete integrated transaction lifecycle']:
                cells[9],cells[10],cells[11] = 'Passed complete workflow and native race checks','Implemented and Verified','StockPurchasingWorkflowTest and verify-pending-concurrency.php supersede the initial missing-workflow finding.'
                cells[8] = 'tests/Feature/StockPurchasingWorkflowTest.php; scripts/verify-pending-concurrency.php'
            if cells[10] == 'Partially Implemented' and module == 'Inventory':
                cells[11] = 'Operational stock workflows are implemented. Remaining limitations concern individual manual acceptance and special costing/return cases, not absence of the module.'
            if cells[10] == 'Partially Implemented' and module == 'Reports':
                cells[11] = 'Source workflows now exist and core reports execute. Each populated filter/export permutation and unresolved cost allocation are not certified by the general suite.'
            if requirement in ['Integrated repair and retail system','Permanent central customer and transaction records','Fourteen BRD modules','Complete thirteen-stage repair workflow','Independent POS workflow','Supplier purchase workflow','Complete integrated business solution']:
                cells[11] = 'Core operational workflows and selected cross-module scenarios now pass. Full owner/hardware/configuration acceptance remains pending.'
            if cells[1] == '19' and cells[10] == 'Partially Implemented':
                cells[11] = 'PDF/font/title/paper/history gaps have corrections and fixture evidence. Complete physical signature/page-break/layout acceptance still needs owner hardware testing.'
            if requirement == 'Physical printer output':
                cells[10],cells[11] = 'Blocked','No physical printer acceptance was performed.'
            if cells[1] == '23':
                cells[11] = 'Automated source workflows now pass; business-owner signatures, physical devices and approved operational backup configuration remain pending.'
            line = '| '+' | '.join(cells)+' |'
    updated.append(line)
write('brd-compliance-matrix.md','\n'.join(updated)+'\n\nFollow-up evidence (2026-10-20): '+result+'. See [pending completion report](pending-completion-report.md). Original scope exclusions and owner acceptance requirements remain applicable.')

write('pending-completion-report.md',f'''# Pending-work completion report

The missing operational modules have now been implemented and tested. Full business acceptance remains conditional on the external items below; this is not a 100% BRD-compliance declaration.

{evidence}

Implemented: supplier registration/edit/search and opening balances; inventory master, references, preferred suppliers and optional known opening costs; purchase drafts/finalization, partial GRNs and source-linked stock receiving; authorized repair requests/issues and complete unused-issue returns; corrections/damage/counts; supplier returns with explicitly agreed credit; supplier purchase/opening payments and reversals; purchase/GRN print/PDF/history; receipt/card PDF export; statement template/paper/Sinhala/history fixes; approved quotation charge transfer; unquoted labour charge entry; completion/delivery timestamps; latest-purchase valuation and recorded-cost profit estimates.

Financial corrections: accounting payment reversals reduce collections without reducing an issued sale; real refunds remain separate. Older returned goods preserve their original cost but cannot replace a newer purchase cost. Repair quote approval transfers selling charges without posting stock or payments. Financial and stock writes are transactional, permission checked, and retain original documents.

{limits}

## Native checks

{races}

## Practical boundaries

Repair return currently reverses an entire unused, resalable issue before billing; damaged goods use the damage workflow. Receiving posts one purchase line/location per GRN submission, supporting partial deliveries. Supplier returns for historical purchases containing repeated lines for the same part are refused until their source is reconciled. No financial records are deleted to simplify these cases. Profit is an estimate of recorded costs, not an audited net-income certification.
''')

write('final-step-12-summary.md',f'''# Final Step 12 summary

The pending software workflows and confirmed integration/print defects have been implemented. **Business acceptance is still pending**; backups require an approved destination and physical tests require shop hardware.

{evidence}

The original 69-table schema is retained. No new application tables, parallel ledgers or operational migrations were introduced. All destructive verification was isolated. See [completion detail](pending-completion-report.md), [BRD matrix](brd-compliance-matrix.md) and [modified files](modified-files.md).

{limits}
''')
write('functional-test-report.md','# Functional test report\n\n'+evidence+'''
The final suite exercises the prior customer, intake, quotation, invoice, payment, POS, role/security/report/backup functionality and the new stock/purchasing/supplier workflows. StockPurchasingWorkflowTest covers the actual customer advance → receipt/job → quotation approval → stock issue → invoice → remaining payment → delivery path without directly setting billable job amounts. Inspection/technician authorization is also covered by the existing job suite and native assigned-job tests. Supplier edit/search preserves opening financial history. Preferred-supplier/opening-cost inputs are verified.

LatestPurchaseCostTest verifies newer purchase cost survives an older returned item's movement, exact valuation and refund-aware recorded margin; payment correction does not become a sales refund. PrintGapCompletionTest verifies supplier/GRN documents and statement settings/history. The complete source method inventory is in automated-test-inventory.md. A passing suite does not replace physical or owner UAT.
'''+limits)
write('integration-test-report.md','# Integration test report\n\n'+evidence+'''
Verified chains: intake/advance → approval pricing → repair stock issue → invoice → net payments → delivery; supplier purchase draft → approval/payable → partial receiving → stock ledger → supplier payment → reversal; supplier return → stock deduction → explicitly agreed supplier credit; POS versus repair competing for the same stock. Existing customer financial tables are untouched by supplier payment operations.

Draft purchase, quotation and part request saves do not change stock. Approval sets job prices but does not issue parts or collect money. Repeat posts are protected by stable keys and locked source documents. Failure cases and the native races exercise duplicate/over-receiving/overselling protection.

Native concurrency covers two receptionists, two assigned technicians on their respective jobs, two invoice/payment cashiers, POS versus repair issue, and two GRN receivers. This does not establish a whole-shop capacity limit or same-record optimistic conflict detection for arbitrary edits.
'''+races+'\n\n'+limits)
write('financial-reconciliation-report.md','# Financial reconciliation report\n\n'+evidence+'''
Exact disposable-fixture assertions include: approved quotation total 30.00, advance 5.00, outstanding 25.00 then 0.00; repair parts cost 6.00 and labour cost 2.00 produce recorded profit estimate 22.00. Supplier purchase total 16.00, payment 6.00 leaves 10.00; full reversal restores 16.00. Agreed supplier return credit 5.00 leaves 11.00. Opening balance 10.50 less payment 3.50 gives 7.00, restored to 10.50 after reversal. These are test fixtures only.

Latest-purchase fixture: an old sale costs 3.00, a new purchase costs 7.00, the old item returns at its original 3.00, and a subsequent sale still snapshots 7.00. Nine units are valued at 63.00. Net revenue 10.00 and net recovered/sold cost 7.00 produce a 3.00 recorded margin. Unknown costs remain unknown, not invented zero costs.

Invoice and customer payment calculations continue to derive net allocations/releases; advances are not counted twice. Supplier balances use the existing supplier ledger, with positive purchase/opening liabilities, negative payments/return credits, and linked positive reversals. Original entries remain append-only. No parallel accounting ledger was created.

Latest purchase cost is owner-approved. Purchase-level freight/discount allocation is not yet approved. Affected estimates, cost mismatches, unknown source costs, no-refund stock returns and ambiguous repair cash refunds remain labelled incomplete. Profit is not sales revenue or a certified accounting statement.
''')
write('inventory-reconciliation-report.md','# Inventory reconciliation report\n\n'+evidence+'''
One signed movement ledger supports opening stock, GRNs, authorized repair issues, POS, resalable returns, supplier returns, damage, corrections and physical counts. The part is locked before checking current ledger quantities, using the same product lock as POS. Requests do not reserve or deduct stock; approval-and-issue records both authorization and movement atomically. Complete unused issues may be returned once before billing, retaining the original negative movement and a linked positive return.

Native shared-stock race: repair and POS each request four from five; one succeeds and one rejects, leaving one unit. Native GRN race: two receivers request four against five ordered; exactly four are received once. Sequential replay, over-receiving, counts and damage cases also pass. Negative stock is rejected by the workflow. Counts record expected and observed quantities before posting their difference.

Valuation uses the latest posted purchase cost at the location, falling back to an explicitly known opening cost only when no purchase cost exists. Old returns retain original cost snapshots and do not alter the selected latest purchase cost. Unknown costs are visibly excluded from known valuation totals. Purchase-wide adjustment allocation remains pending.
''')
write('printing-test-report.md','# Printing and PDF verification\n\n'+evidence+'''
New fixture PDFs were generated, rendered to PNG and visually inspected with embedded regular/bold Noto Sinhala fonts: A5 repair receipt (one page), A6 job card (two pages), customized A5 landscape statement (one page), A4 supplier purchase and GRN (one page each). Exact dimensions/font metadata are in pending-pdf-review.json; artifacts are under storage/app/testing/pending-*.pdf and *-review-*.png. Earlier overflow and missing-glyph defects were corrected. Long documents may legitimately paginate beyond these short-fixture counts.

Statement paper/orientation/title now drive both browser CSS and PDF paper, and statement prints/duplicates preserve history. Invoice visible heading uses its configured title. POS receipt PDFs now embed Sinhala fonts and honor configured font size, including thermal output. Intake PDF download/reprint routes enforce original permissions and retain duplicate labels. Supplier/GRN documents support browser print, PDF, duplication, branding, margins, signatures and audit history using the existing purchase print template.

No physical printer/scanner or fresh interactive browser print-preview acceptance was performed. Physical margins, two-sided card handling, thermal roll behavior and native-language business approval remain owner/hardware UAT items. No asset build or packaging occurred.
''')

# Retain the prior read-only database audit and its full table/FK listing.
db = read('docs/testing/database-relationship-audit.md').split('\n## Pending-work follow-up')[0]
write('database-relationship-audit.md',db+'\n\n## Pending-work follow-up\n\nNo schema changes were necessary. All workflows reuse the existing 69 tables and declared keys. Follow-up before/after snapshots and exact composite-key orphan checks are recorded separately. See pending-preservation.log; row-count equality is not a byte-for-byte data comparison.\n')
security = read('docs/testing/security-test-report.md').split('\n## Pending-work follow-up')[0]
write('security-test-report.md',security+'''\n\n## Pending-work follow-up

New routes inherit auth/active-account/CSRF middleware. Services also enforce permissions for financial posting, stock operations and assigned-job requests. Cashiers cannot administer suppliers/stock; storekeepers cannot post supplier payments or approve purchases using the existing role matrix. Quoted, billed and closed job charges cannot be changed through the unquoted pricing endpoint. No credential values were introduced into settings or reports. New SQL filters are parameterized; print fields remain escaped. The prior text describing supplier/stock operations as absent is superseded by this section and the final workflow suite.
''')

timings=json.loads(read('storage/logs/step12-service-timings.json'))
performance='# Local performance test report\n\n'+evidence+'\nOne local service iteration per operation; fixture construction excluded; no browser/network latency.\n\n| Operation | ms |\n|---|---:|\n'
performance+='\n'.join(f'| {name} | {value} |' for name,value in timings['milliseconds'].items())
performance+='\n\nDataset row counts: `'+json.dumps(timings['dataset_rows'])+'`. Native race timings are in the completion report. These are small local observations, not percentiles or production capacity. Existing report/dashboard/customer/job timing fixture output remains in step12-local-timings.json. No realistic-volume capacity or statistically defensible slow-query benchmark was performed.\n'
write('performance-test-report.md',performance)

write('defect-register.md',f'''# Defect register

Final evidence: {result}; see pending-complete-mysql.log. Earlier failures remain in their original evidence logs and were not hidden.

| ID | Severity | Finding | Current state |
|---|---|---|---|
| D12-01 | Medium | Discounted refund cent rounding | Fixed and regression-tested. |
| D12-02 | Low | MySQL invoice rollback fault-injection coverage | Portable model-event injection passes. |
| G12-01 | High | Missing inventory issue/receiving/adjustment/count operations | Implemented and tested; full unused-issue return supported. |
| G12-02 | High | Missing suppliers/purchases/GRNs/settlement | Implemented and tested, including opening settlement and return credits. |
| D12-03 | High | Approved quotation did not transfer job selling totals | Fixed; receipt-to-paid-invoice test uses real service calls, without manually setting job prices. |
| D12-04 | Medium | Completion/delivery statuses could leave timestamps empty | Fixed; timestamp assertions pass. |
| D12-05 | Medium | Older returned stock could replace newer purchase cost | Fixed with owner-approved latest-purchase source selection. |
| D12-06 | Medium | Accounting payment reversal reduced reported sales | Fixed; collections change while issued sale remains intact. |
| G12-04 | Medium | Intake PDF gaps, fonts, statement title/paper propagation | Implemented and tested; short fixture PDFs visually reviewed. Physical acceptance pending. |
| G12-03 | High | Operational backup destination/cloud account unset | Awaiting approved private folder; Drive remains blank by request. No host scheduler activated. |
| G12-05 | Medium | Purchase-wide cost allocation / ambiguous financial treatment | Latest purchase method approved; freight/discount allocation still awaits answer. Affected profit stays incomplete. |
| G12-06 | Medium | Reception/technician/repair-POS/GRN concurrency | Two-worker native cases pass. Realistic-volume capacity remains unmeasured. |
| G12-07 | Medium | Physical printing/scanning and business sign-off | External UAT pending; no signatures fabricated. |
| L12-01 | Low | Partial return of one repair issue / repeated-part historical purchase return | Full unused repair issue can be reversed; ambiguous repeated-part purchase return is refused pending source reconciliation. |
''')
write('final-brd-compliance-report.md','# Final BRD compliance report\n\n'+evidence+'''
The missing stock, supplier and purchase software workflows are now implemented and verified. Customer/repair/quotation/billing/POS flows integrate with the existing inventory and financial ledgers. Receipt/card, supplier/GRN and statement printing gaps have concrete PDF and history evidence. Latest purchase cost is owner-approved and applied to new transaction snapshots and valuation; existing records are preserved.

The matrix retains every original requirement ID across all 27 BRD sections. Missing operational-module rows now reference their workflow tests. No 100% compliance claim is made: owner UAT, physical devices, approved operational backup storage and unresolved purchase-cost allocation remain external limitations. Optional recommended features and excluded deployment/commercial items retain their explicit statuses.

Database audit: same schema/ERD, native constraint coverage and read-only before/after checks; no operational migrations. Financial/stock accuracy: fixture assertions plus native races, not certification of real accounts. Security: inherited route protections plus service authorization, retained audit history and existing authentication/CSRF/rate-limit tests. Performance: actual bounded local timings; no capacity claim. Printing: rendered fixture PDFs reviewed; no physical test. Backup: real disposable recovery passes; live/cloud configuration remains incomplete. UAT cases are prepared; business owner approval has not been fabricated.

See pending-completion-report.md for implemented functions and evidence, defect-register.md for remaining limitations, and modified-files.md for the full follow-up file list. Stop after this local completion work; no deployment/build/installer was run.
'''+limits)

uat=read('docs/testing/uat-test-cases.md').split('\n## Pending-work follow-up')[0]
write('uat-test-cases.md',uat+'''\n\n## Pending-work follow-up

The earlier descriptions of missing repair stock issue and supplier/GRN services are historical and are superseded. Automated workflow cases now pass in StockPurchasingWorkflowTest (including pricing transfer and customer advances), LatestPurchaseCostTest and PrintGapCompletionTest. Native two-user races pass in verify-pending-concurrency.php. Manual UAT statuses remain blocked/pending owner/hardware participation; they were not converted into signed business approval by automation.
''')
backup=read('docs/testing/backup-restore-test-report.md').split('\n## Pending-work follow-up')[0]
write('backup-restore-test-report.md',backup+'\n\n## Pending-work follow-up\n\nReal native dump/restore and confirmation/password refusal checks were repeated successfully in pending-complete-mysql.log after the new modules. Operational destination/Drive/scheduler status remains as above.\n')

inventory='# Automated test source inventory\n\nActual final execution: '+result+'. Source presence alone is not evidence of owner acceptance.\n\n'
for path in sorted((ROOT/'tests').rglob('*Test.php')):
    methods=re.findall(r'public function (test_\w+)\(',path.read_text(encoding='utf-8'))
    if methods: inventory+='## '+str(path.relative_to(ROOT)).replace('\\','/')+'\n\n'+'\n'.join('- `'+method+'`' for method in methods)+'\n\n'
write('automated-test-inventory.md',inventory)

modified = [
'app/Modules/Inventory/Controllers/InventoryController.php','app/Modules/Inventory/Services/InventoryService.php','app/Modules/Inventory/Services/StockWorkflowService.php',
'app/Modules/Purchases/Controllers/PurchaseController.php','app/Modules/Purchases/Services/PurchaseWorkflowService.php','app/Modules/Purchases/Services/PurchasePrintService.php',
'app/Modules/Suppliers/Controllers/SupplierController.php','app/Modules/Suppliers/Services/SupplierPaymentService.php','app/Modules/PrintTemplates/Services/IntakePdfService.php',
'app/Modules/Customers/Controllers/CustomerController.php','app/Modules/RepairReceipts/Controllers/RepairReceiptController.php','app/Modules/JobCards/Controllers/JobCardController.php',
'app/Modules/RepairJobs/Controllers/RepairJobController.php','app/Modules/RepairJobs/Services/RepairJobService.php','app/Modules/Quotations/Services/QuotationApprovalService.php',
'app/Modules/POS/Services/POSCheckoutService.php','app/Modules/POS/Services/POSReceiptService.php','app/Modules/Reports/Services/ReportDataService.php',
'config/navigation.php','config/business.php','config/inventory.php','lang/en/reports.php','lang/si/reports.php',
'routes/modules/stock-purchasing.php','routes/modules/repair-jobs.php','routes/modules/repair-receipts.php','routes/modules/job-cards.php',
'tests/Feature/DashboardTest.php','tests/Feature/StockPurchasingWorkflowTest.php','tests/Feature/LatestPurchaseCostTest.php','tests/Feature/PrintGapCompletionTest.php',
'scripts/verify-mysql.php','scripts/verify-pending-concurrency.php','scripts/write-pending-reports.py','scripts/write-step12-reports.py','docs/modules/stock-supplier-purchase-workflows.md','README.md','docs/modules/brd-coverage.md',
'resources/views/modules/repair-jobs/show.blade.php','resources/views/modules/repair-receipts/print.blade.php','resources/views/modules/job-cards/print.blade.php',
'resources/views/modules/customers/statement-print.blade.php','resources/views/modules/pos/receipt.blade.php','resources/views/modules/invoices/print.blade.php']
for module in ['inventory','suppliers','purchases']:
    modified += [str(path.relative_to(ROOT)).replace('\\','/') for path in sorted((ROOT/'resources/views/modules'/module).glob('*.blade.php'))]
modified += [str(path.relative_to(ROOT)).replace('\\','/') for path in sorted(OUT.glob('*.md'))]
write('modified-files.md','# Pending-work modified and added files\n\nThis lists the follow-up changes; it does not attribute earlier uncommitted Step 10/11 work to this task. Runtime evidence under ignored storage/logs and storage/app/testing is described in the reports. No schema migration or operational environment credential was changed.\n\n'+'\n'.join('- `'+path+'`' for path in sorted(set(modified))))
print('Updated Step 12 follow-up reports from '+result+'.')
