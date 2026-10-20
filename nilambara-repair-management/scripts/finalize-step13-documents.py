"""Write the handover index after automated and visual document review."""
from pathlib import Path
import json, re, shutil, sys
import fitz

ROOT=Path(__file__).resolve().parents[1]
D=ROOT/'documentation'; E=D/'evidence'
counts=json.loads((E/'completion-counts.json').read_text())
verification=json.loads((E/'document-verification.json').read_text())
assert len(verification)==3 and all(not r['warnings'] for r in verification)
assert '--visual-review-complete' in sys.argv, 'Review the rendered pages before recording completion.'
text='\n'.join(page.get_text() for r in verification for page in fitz.open(r['file']))
secret_keys=['APP_KEY','DB_PASSWORD','BACKUP_GOOGLE_CLIENT_SECRET','BACKUP_GOOGLE_REFRESH_TOKEN']
exposed=[]
for line in (ROOT/'.env').read_text(encoding='utf-8-sig').splitlines():
    if '=' not in line:continue
    key,value=line.split('=',1);value=value.strip().strip('"\'')
    if key in secret_keys and len(value)>=8 and value in text:exposed.append(key)
assert not exposed, 'A private value was found in document text; redact before delivery: '+','.join(exposed)
for item in verification:
    item['visual_review']='Rendered-page contact sheets inspected; full-size samples checked for body, tables, diagrams, screenshots and Sinhala.'
    item['review_date']='2026-10-21'
    item['private_environment_value_scan']='No nonempty inspected secret value found in extracted PDF text.'
(E/'document-verification.json').write_text(json.dumps(verification,indent=2),encoding='utf-8')
pdfrows='\n'.join('| ['+Path(r['file']).name+'](final-pdfs/'+Path(r['file']).name+') | '+str(r['pages'])+' | '+str(round(r['bytes']/1024/1024,2))+' MB |' for r in verification)
report=f'''# Step 13 documentation completion report

Completed: 21 October 2026 (Asia/Colombo). Test/source/schema snapshot: 20 October 2026. The generation/review date does not imply the business suite was rerun on 21 October.

| Deliverable | Pages | Size |
| --- | ---: | ---: |
{pdfrows}

## Coverage and evidence

- {counts['module_areas']} module areas documented; {counts['features']} feature cards. {counts['verified_scenario_features']} cards link to at least one passing automated scenario. This is not certification of every field/path on a card. F-037 (invoice delivery metadata) is source-present but lacks a dedicated reviewed assertion and is labelled Not Verified.
- {counts['brd_rows']} original BRD requirement rows are preserved in the test-report traceability appendix. Field-only, external, optional and unaccepted requirements retain their distinct statuses.
- Fresh Step 13 automated execution: 218 cases, **218 Passed, 0 Failed, 0 Blocked, 0 Not Run**, 3566 assertions. JUnit summed time: 171.71 seconds; console duration: 172.52 seconds. Native concurrency/recovery checks passed separately and are not added to those totals.
- Manual/external register: **4 Blocked, 8 Not Run**. Combined declared register: 230 cases, 218 Passed, 0 Failed, 4 Blocked, 8 Not Run. Executed automated pass rate is 100%; this does not mean the BRD or business acceptance is 100% complete.
- {counts['screenshots']} real Chrome captures from an isolated fixture database; no requested screen capture failed. The invoice screen includes two eligible same-customer jobs selected without submitting an invoice. Long captures are displayed in balanced continuation panels; unmodified originals are preserved. The audit capture is limited to the first 2600 vertical pixels, explicitly captioned.
- Read-only operational audit: 69 InnoDB/utf8mb4 tables, 199 exact foreign-key constraints, zero orphan constraints, no floating financial columns found. Before/after table counts and FK metadata matched; this is not a byte-for-byte data comparison. Diagram arrows are checked against actual inspected FK pairs.

## Document verification

All three PDFs opened, every page rendered, and rendered-page contact sheets were inspected. Full-size samples covered body text, tables, diagrams, screenshot panels and existing Sinhala labels using embedded Noto Sans Sinhala. A4 portrait/landscape geometry, text bounds, all contents anchors, bookmarks/page numbers, required chapters, all 103 feature IDs and all 218 automated case IDs were checked. No final automated document warnings remain. File sizes, hashes, font names and page counts are in [document-verification.json](evidence/document-verification.json).

No nonempty private environment secret value was found in extracted PDF text. Screenshots show disposable non-sensitive fixtures, not operational customers or records. Screenshots demonstrate real page rendering; full browser transaction/UAT execution is not claimed.

## Remaining limitations

Operational backup destination and real Drive upload are blocked by missing approved configuration; Google folder/email/OAuth remain blank as requested. Purchase-wide freight/discount cost allocation and owner UAT/signatures remain blocked. Physical printing/scanning, real unattended scheduler execution, realistic-volume capacity, full browser workflow/session-expiry acceptance and exhaustive hostile-input testing remain Not Run. Partial single-issue repair returns, multi-invoice allocation UI and public self-service account/reset features are Not Implemented. Some application UI labels display untranslated keys (D13-01); no translation/business-logic fix was made in this documentation task. Profit estimates retain incomplete-cost/policy warnings.

## Source files and reproducibility

Editable HTML documents and JSON catalogues: [sources/](sources/). Original screenshots: [screenshots/](screenshots/). Raw JUnit/logs/schema/route-role metadata, copied BRD traceability and document verification: [evidence/](evidence/).

Documentation content definitions are `scripts/step13-content.py`; generation is `scripts/build-step13-documents.py`; PDF checks are `scripts/verify-step13-documents.py`. The scripts use existing installed libraries and Chrome, without a production asset build. The original operational database, application schema and business logic were not changed. Isolated test/screenshot databases were removed by their guarded helpers. No deployment, installer, hosting change or scheduler activation was performed.
'''
(D/'completion-report.md').write_text(report,encoding='utf-8')
(D/'README.md').write_text('# Step 13 documentation\n\n| Deliverable | Pages | Size |\n| --- | ---: | ---: |\n'+pdfrows+'\n\nSee [completion report](completion-report.md) for counts, verification and acceptance limits. Editable [sources](sources/), real [screenshots](screenshots/) and raw [evidence](evidence/) are preserved.\n',encoding='utf-8')
shutil.copyfile(ROOT/'docs/testing/brd-compliance-matrix.md',E/'brd-compliance-matrix.md')
shutil.copyfile(ROOT/'docs/testing/automated-test-inventory.md',E/'automated-test-inventory.md')
print('Step 13 documentation completion report and index written. Three PDFs verified; no private environment secret values found in extracted text.')
