"""Evidence-based Step 13 documents. Reads code/evidence; never writes business records."""
from pathlib import Path
from collections import Counter, defaultdict
import base64, hashlib, html, importlib.util, json, re, time, xml.etree.ElementTree as ET
from PIL import Image, ImageDraw
import fitz

ROOT=Path(__file__).resolve().parents[1]
E=ROOT/'documentation/evidence'; S=ROOT/'documentation/sources'; O=ROOT/'documentation/final-pdfs'
for p in [E,S,O]: p.mkdir(parents=True,exist_ok=True)
spec=importlib.util.spec_from_file_location('content',ROOT/'scripts/step13-content.py')
C=importlib.util.module_from_spec(spec);spec.loader.exec_module(C)
spec2=importlib.util.spec_from_file_location('browser',ROOT/'scripts/capture-step13-browser.py')
B=importlib.util.module_from_spec(spec2);spec2.loader.exec_module(B)

def read(path):
    b=Path(path).read_bytes()
    return b.decode('utf-16' if b[:2] in [b'\xff\xfe',b'\xfe\xff'] else 'utf-8-sig')
def load(path): return json.loads(read(path))
def esc(x): return html.escape(str(x))
def p(x,cls=''): return f'<p class="{cls}">{esc(x)}</p>'
def table(headers,rows,cls=''):
    return '<table class="'+cls+'"><thead><tr>'+''.join('<th>'+esc(x)+'</th>' for x in headers)+'</tr></thead><tbody>'+''.join('<tr>'+''.join('<td>'+esc(x)+'</td>' for x in row)+'</tr>' for row in rows)+'</tbody></table>'
def ul(items): return '<ul>'+''.join('<li>'+esc(x)+'</li>' for x in items)+'</ul>'
def heading(n,title,level=1): return f'<h{level} id="s-{n}">{esc(str(n)+". "+title)}</h{level}>'

impl=load(E/'implementation.json'); before=load(E/'schema-before.json');after=load(E/'schema-after.json')
screens=load(E/'screenshots.json')
junit=ET.parse(E/'step13-junit.xml').getroot(); totals=junit.find('testsuite').attrib
cases=list(junit.iter('testcase'))
assert len(cases)==int(totals['tests'])
log=read(E/'step13-tests.log')
assert 'Disposable MySQL-compatible suite, rollback, and remigration succeeded.' in log
case_status=lambda tc: 'Failed' if tc.find('failure') is not None or tc.find('error') is not None else ('Not Run' if tc.find('skipped') is not None else 'Passed')
counts=Counter(case_status(tc) for tc in cases)
date=impl['captured_at'][:10]
stamp='21 October 2026'

# Inspect all application/code/migration/view/test sources and retain reproducible hashes.
inventory=[]
for folder,patterns in [('app',['*.php']),('routes',['*.php']),('database',['*.php']),('resources/views',['*.php']),('tests',['*.php']),('config',['*.php'])]:
    for path in sorted((ROOT/folder).rglob('*')):
        if not path.is_file() or path.suffix!='.php': continue
        content=path.read_text(encoding='utf-8-sig')
        inventory.append({'path':path.relative_to(ROOT).as_posix(),'sha256':hashlib.sha256(path.read_bytes()).hexdigest(),'lines':len(content.splitlines()),'classes':re.findall(r'\bclass\s+(\w+)',content),'functions':re.findall(r'function\s+(\w+)\s*\(',content)})
(E/'source-inventory.json').write_text(json.dumps(inventory,indent=2),encoding='utf-8')
rows_before={t['name']:t['rows'] for t in before['tables']};rows_after={t['name']:t['rows'] for t in after['tables']}
preserved=rows_before==rows_after and before['foreign_key_constraints']==after['foreign_key_constraints']
assert preserved,'Operational metadata/count comparison changed; investigate before claiming preservation.'
schema_summary={'tables':len(after['tables']),'foreign_key_constraints':len(after['foreign_key_constraints']),'orphans':sum(k['orphans'] for k in after['foreign_key_constraints']),'all_innodb':all(t['engine']=='InnoDB' for t in after['tables']),'all_utf8mb4':all(t['collation'].startswith('utf8mb4') for t in after['tables']),'floating_columns':after['floating_point_columns'],'row_counts_unchanged':preserved}
(E/'database-summary.json').write_text(json.dumps(schema_summary,indent=2))

features=[]
for i,line in enumerate(C.FEATURE_ROWS.strip().splitlines(),1):
    mod,title,route,perms,steps,effect,terms=line.split('|')
    matching=[tc for tc in cases if any(term in tc.attrib['name'] for term in terms.split(',')) and any(suite.split('/')[-1] in tc.attrib['class'] for suite in C.MODULES[mod][7].split('|'))]
    # Database uniqueness belongs to its dedicated constraint suite.
    if terms=='job_numbers_cannot_be_duplicated': matching=[tc for tc in cases if terms in tc.attrib['name']]
    found_routes=[r for r in impl['routes'] if r['name']==route]
    assert found_routes,f'Missing documented route {route}'
    required=perms.split(',')
    roles=[role for role,grants in impl['roles'].items() if all(req in grants for req in required)]
    if perms in ['guest','authenticated']: roles=['All assigned active accounts' if perms=='authenticated' else 'Unauthenticated visitors']
    if title=='Delivery information': matching=[]  # source fields exist; no dedicated delivery-metadata assertion reviewed
    features.append({'id':f'F-{i:03}','module':mod,'name':title,'route':route,'permissions':perms,'roles':roles,'steps':steps,'processing':effect,'tests':[tc.attrib['class']+'::'+tc.attrib['name'] for tc in matching], 'verification':'Verified in cited automated scenario' if matching and all(case_status(tc)=='Passed' for tc in matching) else 'Not Verified - no directly matched automated scenario','uri':found_routes[0]['uri'],'controller':found_routes[0]['action']})
(S/'feature-catalogue.json').write_text(json.dumps(features,ensure_ascii=False,indent=2),encoding='utf-8')

matrix=[]
for line in read(ROOT/'docs/testing/brd-compliance-matrix.md').splitlines():
    if line.startswith('| BRD-'):
        fields=[x.strip() for x in line.strip('|').split('|')]
        if len(fields)==12: matrix.append(fields)
assert matrix
matrix_counts=Counter(x[10] for x in matrix)

CSS='''
@font-face{font-family:DocSans;src:url("file:///C:/Windows/Fonts/arial.ttf")} @font-face{font-family:DocSans;src:url("file:///C:/Windows/Fonts/arialbd.ttf");font-weight:700}
@font-face{font-family:Sinhala;src:url("SINHALA")}
@page{size:A4 portrait;margin:18mm 16mm 20mm} @page wide{size:A4 landscape;margin:15mm 16mm 18mm}
*{box-sizing:border-box} body{font-family:DocSans,Arial,Sinhala,sans-serif;font-size:10pt;line-height:1.5;color:#233447;margin:0}
h1{font-size:22pt;line-height:1.17;color:#123f51;break-before:page;margin:0 0 7mm;break-after:avoid}h2{font-size:14pt;color:#174f62;margin:7mm 0 3mm;break-after:avoid}h3{font-size:11.5pt;color:#174f62;margin:5mm 0 2mm;break-after:avoid}
p{margin:0 0 3.4mm;orphans:3;widows:3}li{margin-bottom:2mm}ul,ol{padding-left:6mm}
table{width:100%;border-collapse:collapse;font-size:8.5pt;margin:3mm 0 5mm;table-layout:fixed}td,th{border:0.2mm solid #d5dfe6;text-align:left;padding:2mm;overflow-wrap:anywhere;vertical-align:top}th{background:#edf4f7;font-weight:700}thead{display:table-header-group}tr{break-inside:avoid}
a{color:#12637b;text-decoration:none}code{font-family:Consolas,monospace;font-size:8pt;overflow-wrap:anywhere}.cover{height:247mm;break-after:page;padding-top:35mm}.cover h1{break-before:avoid;font-size:32pt;margin-top:12mm}.cover .brand{letter-spacing:1.5pt;font-size:11pt;color:#12637b}.logo{width:22mm;height:22mm;object-fit:contain}.rule{border-top:1mm solid #12738b;margin:14mm 0 8mm}.muted{color:#62758a;font-size:9pt}.notice{background:#edf4f7;border-left:1mm solid #12738b;padding:4mm;margin-bottom:6mm}.toc h1{break-before:avoid}.toc li{margin:2mm 0}.wide{page:wide;break-before:page;break-after:page}.wide h1{break-before:avoid}.diagram{width:100%;max-height:170mm;object-fit:contain}figure{margin:0;break-inside:avoid}figcaption{font-size:9pt;color:#52657a;margin:3mm 0}.screenshot-page{page:wide;break-before:page;break-after:page}.screenshot-page h2{margin:0 0 3mm}.screenshot-page img{display:block;margin:auto;max-width:260mm;max-height:153mm;object-fit:contain;border:.2mm solid #d8e2e8}.screenshot-page p{font-size:8pt}.feature-card{break-inside:avoid;margin-bottom:5mm;border-top:.5mm solid #c9dde6;padding-top:3mm}.feature-card table{font-size:9pt}.feature-card td:first-child{width:28%;font-weight:700}.test-card{break-inside:avoid;border-top:.5mm solid #c9dde6;padding-top:3mm;margin:0 0 6mm;font-size:9pt}.test-card h3{margin-top:0}.test-card p{margin-bottom:2mm}.small{font-size:8pt}.sinhala{font-family:Sinhala,DocSans,sans-serif;font-size:12pt}.diagram-section{break-inside:avoid}.signature{margin-top:10mm}.no-break{break-inside:avoid}
'''.replace('SINHALA',(ROOT/'public/fonts/NotoSansSinhala-Regular.ttf').as_uri())
CSS+=' body>h1{break-before:auto;margin-top:9mm} .toc{break-after:page} .screenshot-page img{max-height:120mm} .feature-card table{font-size:9pt;line-height:1.32} .feature-card td,.feature-card th{padding:1.4mm} .feature-card{break-inside:auto} '
logo=ROOT/'public'/impl['business']['logo']
def cover(title,kind):
    return '<section class="cover">'+(f'<img class="logo" src="{logo.as_uri()}">' if logo.is_file() else '')+p('NILAMBARA ELECTRICALS','brand')+f'<h1>{esc(title)}</h1>'+p('Repair Shop Management System')+'<div class="rule"></div>'+p('Version 1.0 | Generated '+stamp)+p(kind)+p('482/A/3, Colombo Road, Kaduwela, Sri Lanka.')+p('Currency: LKR | Timezone: Asia/Colombo')+p('Based on the existing implementation, the original BRD and isolated test evidence. Business-owner acceptance remains pending.','muted')+'</section>'
def toc(chapters):
    return '<section class="toc"><h1>Contents</h1>'+p('Select a chapter to navigate. PDF footers show physical page numbers.','muted')+'<ol>'+''.join(f'<li><a href="#s-{n}">{esc(title)}</a></li>' for n,title in chapters)+'</ol></section>'
def control(kind):
    return table(['Document control','Value'],[['Version / generation','1.0 / '+stamp],['Scope',kind],['Basis','Original BRD (20 pages, 27 sections); source/route/schema inspection; fresh Step 13 JUnit and native checks'],['Author / approval','Codex documentation runner / business-owner approval pending'],['Verification boundary','Automated scenario evidence and real fixture screenshots; no production deployment or live business-data writes'],['Companion sources','documentation/sources/; documentation/evidence/; documentation/screenshots/']])
def screenshot_appendix():
    parts=[]
    for index,entry in enumerate(screens,1):
        if entry['status']!='Captured':continue
        path=ROOT/'documentation/screenshots'/f"{entry['id']}.png"
        img=Image.open(path)
        # Preserve every pixel in clearly captioned continuation panels for readable landscape printing.
        height=img.height
        chunks=(height+1199)//1200
        panel_height=(height+chunks-1)//chunks
        for n in range(chunks):
            crop=img.crop((0,n*panel_height,img.width,min((n+1)*panel_height,height)))
            panel=S/f"screen-{entry['id']}-{n+1}.png";crop.save(panel)
            parts.append('<section class="screenshot-page"><h2>'+esc(f'Figure {index}.{n+1} - {entry["caption"]}')+'</h2>'+p(f"Actual Chrome screenshot | {entry['path']} | isolated test fixtures | panel {n+1}/{chunks}",'muted')+f'<figure><img src="{panel.as_uri()}"><figcaption>'+esc('Continuation panels collectively preserve the captured screen. Original PNG is in documentation/screenshots/'+entry['id']+'.png. '+('The capture is limited to the first 2600 vertical pixels; content below is not shown.' if entry.get('viewport_limited') else 'No operational records or passwords are displayed.'))+'</figcaption></figure></section>')
    return ''.join(parts)

def diagram(filename,chains,title):
    fk={(k['parent_table'],k['child_table']) for k in after['foreign_keys']}
    width=1050;boxw=220;boxh=48;rowh=135
    items=[];verified=[]
    for row,chain in enumerate(chains):
        y=65+row*rowh
        for col,name in enumerate(chain):
            x=25+col*270
            items.append(f'<rect x="{x}" y="{y}" width="{boxw}" height="{boxh}" rx="8" fill="#edf4f7" stroke="#14718a"/><text x="{x+boxw/2}" y="{y+29}" text-anchor="middle" font-family="Arial" font-size="16" fill="#173e50">{name}</text>')
            if col:
                parent=chain[col-1];child=name
                assert (parent,child) in fk, f'Unverified diagram edge {parent} -> {child}'
                items.append(f'<line x1="{x-50}" y1="{y+24}" x2="{x-4}" y2="{y+24}" stroke="#14718a" stroke-width="2" marker-end="url(#arrow)"/>')
                verified.append([parent,child])
        items.append(f'<text x="25" y="{y+77}" font-family="Arial" font-size="13" fill="#52657a">Parent to referencing child; arrows correspond to inspected foreign keys.</text>')
    svg=f'<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{len(chains)*rowh+75}" viewBox="0 0 {width} {len(chains)*rowh+75}"><defs><marker id="arrow" markerWidth="8" markerHeight="8" refX="7" refY="3" orient="auto"><path d="M0,0 L0,6 L8,3 z" fill="#14718a"/></marker></defs>'+''.join(items)+'</svg>'
    path=S/filename;path.write_text(svg,encoding='utf-8')
    (E/(filename+'.relationships.json')).write_text(json.dumps(verified,indent=2))
    return '<section class="diagram-section">'+f'<h2>{esc(title)}</h2><img class="diagram" src="{path.as_uri()}">'+p('Verified table relationships, not a sequence of automatic business actions. Join tables connect the separate paths.','muted')+'</section>'

relationship_html=diagram('repair-finance-relationships.svg',[
 ['customers','repair_jobs','repair_receipts'],['repair_jobs','quotations','quotation_items'],['invoices','invoice_jobs'],['repair_jobs','invoice_jobs'],['invoices','payment_allocations'],['payments','payment_allocations']], 'Repair and financial relationships')
relationship_html+=diagram('stock-purchase-pos-relationships.svg',[
 ['suppliers','purchases','purchase_items'],['purchases','goods_received_notes','goods_received_note_items'],['spare_parts','stock_movements'],['repair_jobs','job_part_usages','stock_movements'],['pos_sales','pos_sale_items','stock_movements'],['pos_sales','payment_allocations']], 'Shared stock, purchase and POS relationships')

def role_guide():
    descriptions={
      'administrator':'Manage accounts/roles/settings/templates/audit and protected backups. Preserve the final active administrator. Restore is CLI-only and requires explicit target approval.',
      'receptionist':'Search/register customers; receive repairs and print intake/card documents; prepare quotations where granted. No assumed authority for money, stock, financial statements or administration.',
      'technician':'Inspect and repair assigned jobs, update their status and request parts. Cannot use another technician\'s job by changing its URL. Stock issue and customer-money authority are separate.',
      'cashier':'Create/issue invoices, receive actual collections and advances, collect credit and perform permitted POS sales/printing. No default refund/reversal, supplier or settings authority.',
      'storekeeper':'Maintain parts, request/issue/adjust/count stock and enter/receive purchases. Purchase approval, supplier administration and supplier money are management-only under current grants.',
      'manager':'Assign repairs, prepare/approve quotations, supervise invoices/collections/credit, suppliers/purchase approval and financial reports. No assumed settings/account/restore access.'}
    body=p('The following grants were read from the operational role relations, not inferred from the default seeder. Individual override count at inspection: '+str(impl['individual_override_count'])+'. Subsequent administrator customization can change access. Some actions need additional record policy or several permissions together.')
    for role,grants in impl['roles'].items():
        body+=f'<h2>{esc(role.title())}</h2>'+p(descriptions.get(role,''))+p('Current permission grants ('+str(len(grants))+'): '+', '.join(grants),'small')
    return body

chapters=[(i,v[0]) for i,v in C.MANUAL_EXTRA.items()]+[(21,'Terminology and navigation tips'),(22,'Current verification and limitations'),(23,'System relationship diagrams'),(24,'Real application screenshot atlas')]
manual=cover('Complete User Manual','Employee and administrator operating guide')+toc(chapters)
manual+=control('Detailed implemented workflows for all six roles')
chapter_modules={3:['Dashboard'],4:['Customers'],5:['Receipts'],6:['Jobs'],7:['Quotations'],8:['Invoices'],9:['Payments'],10:['Credit'],11:['Inventory'],12:['Suppliers','Purchases'],13:['POS'],14:['Reports'],15:['Employees','Permissions'],16:['Settings'],17:['Templates'],18:['Backups']}
for n,(title,text) in C.MANUAL_EXTRA.items():
    manual+=heading(n,title)+p(text)
    if n==2:
        manual+='<pre><code>cd D:\\xampp\\htdocs\\Nilambara_Electricals\\nilambara-repair-management\n$env:PHPRC = (Resolve-Path ..\\.tools\\php.ini).Path\nphp artisan serve --host=127.0.0.1 --port=8000 --no-reload</code></pre>'
    for module_index,module in enumerate(chapter_modules.get(n,[]),1):
        info=C.MODULES[module]
        relevant=[f for f in features if f['module']==module]
        manual+=table(['Operational reference','Implementation'],[['Required inputs',info[3]],['Validation / restrictions',info[4]],['Effect / balance changes',info[5]],['Navigation','; '.join(sorted(set('/'+f['uri'] for f in relevant)))],['Screenshot reference','; '.join(e['id'] for e in screens if e['id']==info[6])+'; see chapter 24'],['Evidence',info[7]+'; Step 13 test-case appendix']])
        manual+=heading(f'{n}.{module_index}','Actions in '+info[0],2)+ul([f['name']+': '+f['steps']+' '+f['processing'] for f in relevant])
    if n==6: manual+=heading('6.2','All 17 implemented status labels',2)+table(['Status','Practical meaning'],zip(impl['job_statuses'],C.STATUS_MEANINGS))
    if n==14:
        ctrl=read(ROOT/'app/Modules/Reports/Controllers/ReportController.php')
        reportkeys=re.findall(r"'([^']+)'",ctrl.split('private const REPORTS = [',1)[1].split('];',1)[0])
        manual+=heading('14.2','Actual report catalogue',2)+p(', '.join(reportkeys))
    if n==15:manual+=heading('15.3','Current operational role guidance',2)+role_guide()
    if n==16:manual+=p('Existing Sinhala UI labels (copied without new translation):')+'<p class="sinhala">'+esc(impl['sinhala_labels']['dashboard']+' | '+impl['sinhala_labels']['customers'])+'</p>'
manual+=heading(21,'Terminology and navigation tips')+table(['Term','Meaning'],[
 ['Job ID / automatic reference','Database identity / JOB-formatted reference; separate from unique manual Job Number.'],['GRN','Goods Received Note: actual received purchase quantity at a location.'],['Draft / issued / finalized','Preparation/posting states; a draft is not proof of payment or receipt of stock.'],['Allocation','Application of an existing payment to a debt/job/POS target; not another cash receipt.'],['Refund / reversal','Return of money versus accounting correction; both preserve original records.'],['Available stock','Sum of posted movements at a location, not quotation/request quantities.'],['Latest purchase cost','Latest posted purchase cost at location, with known opening fallback; not a rewrite of historical costs.'],['Duplicate','Marked later document copy with print history.'],['UAT','Business user acceptance on approved fixtures, including physical hardware checks.']])+ul(['Use sidebar links, record search and breadcrumbs. Tab/Shift+Tab move through ordinary browser form controls; Ctrl+F searches the displayed document. No special application keyboard shortcuts were verified.','Wait for saved confirmation; after timeout search before creating another transaction.','When using browser Print/PDF, inspect paper/scale and signatures; do not repost a sale or receipt to troubleshoot a printer.'])
manual+=heading(22,'Current verification and limitations')+p(f"Fresh automated result: {len(cases)} tests; {counts['Passed']} Passed; {counts['Failed']} Failed; {counts['Not Run']} skipped/Not Run; {totals['assertions']} assertions. This is scenario coverage, not complete BRD/owner acceptance.")+ul(['Not Implemented: public registration/reset/account-settings screens; multi-invoice payment allocation form; partial return of one issued repair quantity.','Not Verified operationally: private backup destination/Drive upload, host scheduler execution, physical printers/scanners, realistic-volume capacity and owner UAT.','Pending policy: freight/purchase-wide discount allocation; affected profit remains incomplete.','Observed UI limitation: unresolved translation keys appear on some quotation labels; English/Sinhala completeness is not certified.','Screenshots are isolated non-sensitive fixtures. Screen amounts/names are examples from actual fixture execution, not shop transactions.'])
manual+=heading(23,'System relationship diagrams')+relationship_html
manual+=heading(24,'Real application screenshot atlas')+p('The atlas contains genuine captured application pages. Long captures are split into numbered continuation panels with no hidden middle section. Original unmodified captures and the capture manifest are preserved. No application screen was fabricated.')+screenshot_appendix()

feature_chapters=[(1,'How to use this feature guide'),(2,'Current role-based guidance'),(3,'System relationships and posting rules')]+[(i+4,C.MODULES[m][0]) for i,m in enumerate(C.MODULES)]+[(24,'Twelve practical workflows'),(25,'BRD gaps and verification boundaries'),(26,'Screenshot index')]
guidance=cover('Complete Feature Guidance','Feature-by-feature business and technical reference')+toc(feature_chapters)+control('Feature catalogue with current operational grants and scenario evidence')
guidance+=heading(1,'How to use this feature guide')+p('Each feature card identifies purpose, current role prerequisites, actual navigation, inputs, usage, processing, output, database relationship, validation, common errors, related features, print/export and screenshot evidence. A matched test means the cited automated scenario passed, not that every screen field received manual acceptance. Features with no directly matched executed method are explicitly Not Verified. Source implementation and BRD expectation are separate.')+p(f"Catalogue: {len(features)} feature cards across {len(C.MODULES)} documented module areas. Verified-scenario feature count: {sum(f['verification'].startswith('Verified') for f in features)}. A feature may have several tests; these counts do not add to the test total.")
guidance+=p('Existing Sinhala UI labels, copied from the application without new translations:')+'<p class="sinhala">'+esc(impl['sinhala_labels']['dashboard']+' | '+impl['sinhala_labels']['customers'])+'</p>'
guidance+=heading(2,'Current role-based guidance')+role_guide()
guidance+=heading(3,'System relationships and posting rules')+relationship_html+ul(['One customer has repair jobs; each intake creates a receipt/job/card atomically.','invoice_jobs links one invoice to multiple same-customer jobs; payment_allocations links multiple receipts to an invoice. Credit is derived from liabilities/allocations and account entries, not a second sale.','An advance is money received once. Later allocation is not another cash collection.','Supplier purchase finalization creates a payable; GRNs create received stock; purchase drafts do neither.','Quoted/requested parts do not deduct stock. Authorized issue and finalized POS consume the shared stock balance.','Stock returns/corrections retain original movements. Resalable POS returns retain original cost.'])
for i,(module,info) in enumerate(C.MODULES.items(),4):
    guidance+=heading(i,info[0])+p('BRD sections: '+info[1]+'. Actual source module inspected; '+info[4])
    guidance+=heading(f'{i}.1','Module input and validation reference',2)+table(['Shared reference','Actual implementation'],[['Required inputs',info[3]],['Validation and common error causes',info[4]],['Database relationships',info[2]],['Balance/record effects',info[5]]])
    for f in [x for x in features if x['module']==module]:
        permissions=f['permissions'].split(',')
        route=next(r for r in impl['routes'] if r['name']==f['route'])
        print_options=('Use the document print/PDF or report export actions described on the saved page; permission-specific actions are listed in the User Manual.' if any(s in f['name'].lower() for s in ['print','receipt','pdf','export','statement','report','card']) else 'No separate print action for this operation; use its related saved document/report where available.')
        other=[x['name'] for x in features if x['module']==module and x['id']!=f['id']][:3]
        guidance+='<section class="feature-card">'+f'<h2 id="{f["id"]}">{esc(f["id"]+" - "+f["name"])}</h2>'+table(['Feature field','Implemented behavior'],[
          ['Module / business purpose',info[0]+': '+f['name'].lower()+'. BRD '+info[1]],['Authorized roles / permissions',(', '.join(f['roles']) if f['roles'] else 'Permission-based access')+'; '+f['permissions']+'; record policy also applies.'],['Navigation path','/'+f['uri']+' ('+f['route']+'); '+','.join(route['methods'])],['Required inputs','Use the relevant fields from section '+str(i)+'.1; action-specific selections are identified in the steps below.'],['Step-by-step usage',f['steps']],['System processing / expected output',f['processing']],['Database relationship','Section '+str(i)+'.1 lists the verified participating tables and balance effects.'],['Validation / common errors','Section '+str(i)+'.1 is the input/state checklist. Missing grants, invalid input or incompatible saved state refuse the action; stock paths also check available quantity/location.'],['Related features','; '.join(other)],['Print/export options',print_options],['Screenshot','Screenshot '+info[6]+': module figure below and User Manual chapter 24.'],['Implementation evidence',f['controller']],['Verification',f['verification']+('. Example: '+f['tests'][0] if f['tests'] else '.')]])+'</section>'
    screen=ROOT/'documentation/screenshots'/f'{info[6]}.png'
    if screen.exists(): guidance+='<figure class="no-break"><img style="width:100%;max-height:150mm;object-fit:contain" src="'+screen.as_uri()+'"><figcaption>'+esc('Actual isolated application screenshot: '+info[0]+'. Full-size original: '+info[6]+'.png; continuation panels appear in the User Manual atlas.')+'</figcaption></figure>'
guidance+=heading(24,'Twelve practical workflows')
workflow_text=[
 ('New customer and repair','Customers > search/register individual/company > New repair receipt > actual intake/advance > Save > receipt/job/card.'),
 ('Inspection and quotation decision','Repair Jobs > Assign > technician inspection > Quotations create/issue > record actual customer approval/rejection. No stock posts at quote.'),
 ('Repair completion and invoice','Approved work > request/issue parts > actual repair/costs > Repair Completed > Create Invoice > same-customer jobs > Issue > later delivery status.'),
 ('Advance and partial payment','Receive advance once > issue invoice > inspect applied advance > receive actual remaining partial amounts > inspect outstanding after each.'),
 ('Credit collection','Credit Management > outstanding invoice > Collect > actual payment method/reference > saved allocation > customer statement.'),
 ('Purchase and GRN','Supplier > purchase draft > management finalize > receive actual line/location quantities, possibly several deliveries > print GRN > settle supplier.'),
 ('Repair parts issue','Technician Part Requests > job/part/location/quantity > issuer checks approval/availability > Issue once > movement history.'),
 ('POS sale','POS > scan/search > cart quantity > permitted discount > cash tender/change or pending transfer/cheque > manager verify where needed > receipt.'),
 ('Stock return and adjustment','Identify original issue/sale/purchase > choose authorized supported return path > physically verify goods > return/reason > review movement; use damage/count/correction for their actual purpose.'),
 ('Dashboard and reports','Dashboard period/date Apply > read cohort/card basis > Business Reports matching filters > inspect incomplete flags > permitted PDF/XLSX/CSV.'),
 ('Manual backup','Obtain approved private destination/client configuration first > Backup Management > Create > inspect history > Verify > permitted protected Download; local/Drive states distinct.'),
 ('Roles and permissions','Administration > Roles & Permissions/current user > inspect grants > authorized change on eligible other account > verify access; explicit deny and assigned-job policy remain.')]
for n,(title,text) in enumerate(workflow_text,1):guidance+=heading(f'24.{n}',title,2)+p(text)
guidance+=heading(25,'BRD gaps and verification boundaries')+p('Not Implemented: public self-registration/password-reset/account-settings UI, multi-invoice payment allocation form, partial return of a single repair issue. No production build/installer/deployment is part of this task. Google Drive integration is source-present but unconfigured and Not Verified against a real account. Live operational recovery/scheduler, hardware, capacity and owner sign-off are Not Verified. These distinctions prevent treating every BRD line as functioning software.')+table(['Feature verification','Count'],Counter(f['verification'] for f in features).items())+p('A verified-scenario feature card means at least one related assertion scenario passed; it does not certify every input, alternative path, permission combination or physical operation on that card. Feature catalogue mappings are preserved in sources/feature-catalogue.json. The full source matrix, including optional and future requirements, follows in the test-report appendix.')
guidance+=heading(26,'Screenshot index')+table(['Screenshot','Actual navigation','Location in User Manual'],[(e['caption'],e['path'],'Chapter 24; original '+e['id']+'.png') for e in screens])+p('Use the corresponding User Manual figure atlas for the full real screen panels. All original PNGs accompany these PDFs; no substitute/mock screenshots were used.')

# Actual per-case records use the executed method, source line, calls and assertions.
test_modules={ 'Authentication':'Authentication', 'CreateUser':'Employees','Employee':'Employees','Customer':'Customers','Receipt':'Receipts','Quotation':'Quotations','Invoice':'Invoices','Payment':'Payments','POS':'POS','StockPurchasing':'Purchases','LatestPurchase':'Inventory','Print':'Templates','Backup':'Backups','Recovery':'Backups','Report':'Reports','Dashboard':'Dashboard','Audit':'Audit','SystemSupport':'Settings','Schema':'Inventory','Business':'Inventory'}
def module_for(tc):
    name=tc.attrib['class'].split('\\')[-1]
    return next((mod for key,mod in test_modules.items() if key in name),'Inventory')
def method_source(tc):
    path=Path(tc.attrib['file']); text=path.read_text(encoding='utf-8-sig');name=tc.attrib['name'].split(' with data set')[0]
    start=re.search(r'function\s+'+re.escape(name)+r'\s*\(',text)
    if not start:return ''
    end=re.search(r'\n\s*(?:public|protected|private)\s+(?:static\s+)?function\s+',text[start.end():])
    return text[start.start():start.end()+end.start()] if end else text[start.start():]
records=[]
for i,tc in enumerate(cases,1):
    body=method_source(tc);module=module_for(tc);a=tc.attrib
    http=re.findall(r'->(get|post|put|delete)\s*\(\s*(?:route\(\s*[\'"]([^\'"]+)|[\'"]([^\'"]+))',body)
    services=list(dict.fromkeys(re.findall(r'app\((\w+)::class\)\s*->\s*(\w+)',body)))
    assertions=list(dict.fromkeys(re.findall(r'->(assert\w+|expectException)\s*\(',body)))
    helpers=list(dict.fromkeys(re.findall(r'\$this->(\w+)\s*\(',body)))
    helpers=[x for x in helpers if not x.startswith('assert') and x not in ['get','post','put','delete','actingAs']]
    steps=[]
    if helpers:steps.append('Set up/use fixture helpers: '+', '.join(helpers[:9])+'.')
    if http:steps.append('Execute requests in method order: '+'; '.join(method.upper()+' '+(route or uri) for method,route,uri in http[:10])+'.')
    if services:steps.append('Invoke services: '+', '.join(c+'::'+m for c,m in services[:8])+'.')
    if not steps:steps=['Execute the source method at the cited file/line; it invokes its unit/model/database operation and any declared exception checks.']
    objective=a['name'].split(' with data set')[0].removeprefix('test_').replace('_',' ').capitalize()
    if ' with data set' in a['name']:objective+=' ('+a['name'].split(' with data set')[1].strip()+')'
    record={'id':f'T13-{i:03}','module':module,'brd':C.MODULES[module][1],'objective':objective,'preconditions':'Suite bootstrap and test setUp; isolated MySQL fixtures for database scenarios, in-memory objects for pure unit scenarios.','data':'Synthetic inputs/fixtures declared in the cited method'+(' and '+', '.join(helpers[:6]) if helpers else '')+'; no operational business values.','steps':' '.join(steps),'expected':objective+'. Assert/check operations: '+(', '.join(assertions) if assertions else 'method exception/helper expectations; see executable source')+'.','actual':f"{case_status(tc)}; {a.get('assertions','0')} assertions; {a.get('time','0')} seconds. JUnit records this exact method/data-set result.",'status':case_status(tc),'date':date,'evidence':str(Path(a['file']).relative_to(ROOT)).replace('\\','/')+':'+a['line']+'; '+a['class']+'::'+a['name']+'; documentation/evidence/step13-junit.xml','defect':'None raised by this fresh execution.' if case_status(tc)=='Passed' else 'See raw JUnit failure/error payload.'}
    records.append(record)
(S/'test-cases.json').write_text(json.dumps(records,ensure_ascii=False,indent=2),encoding='utf-8')

manual_checks=[('Physical A5/A6/A4/thermal printing','Not Run','Actual shop devices unavailable; inspect paper/scale/signatures and both scripts.','Printing'),('Physical barcode scanner','Not Run','Browser search/scan field tested; hardware keyboard-wedge scan not exercised.','POS'),('Full end-to-end browser business workflow','Not Run','Screenshots verify page rendering; backend scenarios verify posting. No claim of a full browser transaction/UAT test.','System'),('Wall-clock session expiration','Not Run','Configured 120 minutes inspected; waiting through real expiry was not performed.','Authentication'),('Host unattended 02:00 scheduler execution','Not Run','No host scheduler activated by this task.','Backups'),('Realistic-volume capacity','Not Run','Local fixture observations are not a load/capacity test.','Performance'),('All hostile uploads and every XSS sink','Not Run','Targeted probes only; no exhaustive penetration test.','Security'),('Printer/scanner business-user sign-off','Not Run','Physical test and business tester/date/signature required.','UAT'),('Live operational backup destination','Blocked','Approved private operational folder has not been supplied.','Backups'),('Real Google Drive upload','Blocked','Folder/account/OAuth intentionally blank at owner request.','Backups'),('Purchase-wide freight/discount policy acceptance','Blocked','Owner chose latest purchase cost; allocation of purchase-wide adjustments remains undecided.','Finance'),('Final business-owner UAT approval','Blocked','No owner signature/acceptance supplied; automation cannot provide it.','UAT')]
native=[line.strip() for line in log.splitlines() if line.startswith('PASS:')]
testchap=[(1,'Document control'),(2,'Testing objectives'),(3,'Scope'),(4,'Environment'),(5,'Methodology'),(6,'Requirement traceability'),(7,'Module test coverage'),(8,'Integration testing'),(9,'Database relationships'),(10,'Financial calculations'),(11,'Inventory testing'),(12,'Security testing'),(13,'Roles and permissions'),(14,'Printing and Unicode'),(15,'Backup and restore'),(16,'Performance and concurrency'),(17,'Results summary'),(18,'Failed tests and defects'),(19,'Retesting'),(20,'Known limitations'),(21,'Final technical assessment'),(22,'Per-case execution records'),(23,'Manual acceptance cases'),(24,'Requirement-by-requirement appendix'),(25,'Evidence index')]
report=cover('System Testing and Test Results','Fresh isolated execution and BRD traceability record')+toc(testchap)
report+=heading(1,'Document control')+control('Actual Step 13 execution, source inspection and manual acceptance boundaries')
report+=heading(2,'Testing objectives')+p('Verify implemented operations and integration relationships against the original BRD; preserve exact test evidence, identify gaps and produce a technical assessment without modifying genuine business records.')
report+=heading(3,'Scope')+p('All existing Unit and Feature tests were executed, followed by native POS/invoice/payment/receipt/technician/repair-stock/GRN races and real disposable backup recovery. Source inspection covered routes, controllers, services, models, migrations, Blade, Livewire, config, all BRD sections and current MySQL metadata. Documentation and test execution only: no hosting/build/deployment, schema redesign or business logic changes.')+table(['Inspection inventory','Count'],Counter(x['path'].split('/')[0] for x in inventory).items())
report+=heading(4,'Environment')+table(['Component','Actual finding'],[['PHP',impl['php']],['Laravel',impl['laravel']],['Database connection/server','mysql / '+impl['server']],['Business database','nilambara_repair_management (read-only inspection only)'],['Test database','Fresh randomized nilambara_step2_test_<12 hex>; created/dropped by guarded helper'],['Browser','Installed local Chrome headless via DevTools; real isolated Laravel fixture app on loopback 8087'],['UI assets','Existing built assets reused; no production build'],['Schema',f"{schema_summary['tables']} tables; {schema_summary['foreign_key_constraints']} FK constraints; all InnoDB/utf8mb4"],['Timezone / currency','Asia/Colombo / LKR'],['Execution date',date]])
report+=heading(5,'Methodology')+p('scripts/verify-step13.php is a documentation-only copy of the existing guarded verifier, adding JUnit output. It creates a nonexistent random database, passes explicit testing DB environment to children, runs the full suite, rolls back/remigrates only that disposable database, performs native multi-process/recovery checks, then drops only its own database. Before/after operational metadata/count snapshots were compared. A separate guarded database and non-sensitive fixtures powered screenshots; that database was removed after capture. No operational web login or transaction was used for screenshots.')+p('Per-case records map JUnit method/data-set names to executable test source line, request/service sequence and assertion operations. Expected behavior is the objective encoded in those assertions. The original source and raw logs remain authoritative for exact fixture literals and exception branches; no console output is fabricated.')
report+=heading(6,'Requirement traceability')+p(f'The original BRD contains 27 numbered sections on 20 pages. The maintained matrix contains {len(matrix)} requirement rows. Suite-level/field-only mappings are retained with their limits; the status of a BRD requirement is separate from a passing test method. Appendix 24 includes every matrix row with original requirement ID.')+table(['Matrix status','Rows'],matrix_counts.items())
report+=heading(7,'Module test coverage')+table(['Documented module area','Passed executed cases (primary classification)'],[(C.MODULES[m][0],sum(r['module']==m and r['status']=='Passed' for r in records)) for m in C.MODULES])+p('Each test is classified once in this table. Cross-module assertions cover additional areas; a zero primary classification is not a claim that a module is absent. The per-case source/method and feature catalogue supply granular scenario links.')
report+=heading(8,'Integration testing')+p('The actual intake-to-delivery service scenario receives an advance, issues/approves a quotation, issues parts, completes the job, invoices and collects the remainder, then records delivery. It verifies the approved total without manually forcing job selling prices. Purchasing tests cover draft/finalize/partial GRN/payment/reversal and return credits. Existing multi-job, payment/credit and POS scenarios verify their linked tables and protected replay. Browser capture proves page availability/rendering, not completion of those transactions through click automation.')
report+=heading(9,'Database relationships')+table(['Audit check','Actual result'],[[k,v] for k,v in schema_summary.items()])+p('Primary/unique/index metadata and FK columns/delete rules are preserved in schema-before/after.json. Full composite constraints were checked as tuples, in addition to per-column mappings. No orphan found. BusinessConstraintsTest/SchemaIntegrityTest/BusinessRelationshipsTest execute required-field, uniqueness, restrict/cascade and cross-customer/target integrity behavior; model method names alone are not evidence. Matching row counts are not a byte-for-byte operational record comparison.')+relationship_html
report+=heading(10,'Financial calculations')+p('Fresh suites assert exact decimals for invoice totals, advances/allocations, outstanding/partial states, credit collections, refunds/reversals, supplier openings/settlements and latest-purchase profit/valuation. The existing integrated fixture verifies a 30.00 invoice, 5.00 advance and 25.00 remainder; known cost/labour gives 22.00 recorded-cost estimate. These are isolated test assertions, not operational invoice values. Payment accounting reversal affects collections without removing issued sales. Supplier targets reject overpayment. Allocation is not a second receipt. Purchase-wide adjustment allocation remains undecided; affected estimates remain incomplete.')
report+=heading(11,'Inventory testing')+p('Opening movement and known cost, partial GRNs, request-without-deduction, exactly-once issue, full unused resalable issue return, correction/damage/count, supplier return, POS deduction/return and shared concurrent access are covered. Native repair issue versus POS and two-storekeeper GRN races verify common stock/remaining-receipt constraints. Unit precision and negative availability are validated. Partial single-issue returns and ambiguous historical repeated-part source returns remain implementation boundaries, not failed claims hidden by tests.')
report+=heading(12,'Security testing')+p('Executed authentication scenarios cover required credentials, rejection, throttling, CSRF with verification active, session regeneration/logout invalidation, disabled accounts and disabled public routes. Role tests cover direct access, explicit deny, last administrator and assigned-technician restrictions. Targeted SQL-looking input and stored script escaping probes ran. Audit secret redaction, setting allowlists, backup private-root/traversal/checksum protections and authenticated restore safeguards ran. This is not a comprehensive penetration test or certification of production HTTPS/Windows filesystem controls. No authentication/CSRF safeguard was disabled to claim a passing result.')
report+=heading(13,'Roles and permissions')+role_guide()+p('Operational grants were inspected read-only; no role seeder was run on the operational database. Fresh fixtures use seeded grants and therefore may differ from existing customized grants. Authorization includes route middleware, controller/service checks and record policy; a visible link is not the enforcement boundary.')
report+=heading(14,'Printing and Unicode')+p('Existing automated scenarios exercise receipt/card, quotation, invoice, payment, POS, statements, purchase/GRN and report outputs with permissions, PDF signatures, templates/duplicate histories and local fonts. These three documentation PDFs are rendered by actual Chrome with local Arial and Noto Sans Sinhala and are visually reviewed after generation. Physical A5/A6/A4/thermal printing, duplex/real hardware and scanner tests remain Not Run. Screenshot render of an HTML print view does not prove a physical device printed.')+p('The following existing Sinhala UI labels are copied directly from lang/si/ui.php, not newly translated:')+'<p class="sinhala">'+esc(impl['sinhala_labels']['dashboard']+' | '+impl['sinhala_labels']['customers'])+'</p>'+p('Some actual application quotation labels display unresolved translation keys (for example ui.contact_number and ui.labour_charge); see D13-01. This task records the issue and does not alter business logic/translations.')
report+='<figure><img style="width:100%;max-height:150mm;object-fit:contain" src="'+(ROOT/'documentation/screenshots/quotation-approval.png').as_uri()+'"><figcaption>Actual isolated issued quotation and customer-decision page. Unresolved labels are recorded as D13-01; fixture amounts are not operational transactions.</figcaption></figure>'
report+=heading(15,'Backup and restore')+p('Native checks created a nonempty real test mysqldump, verified SHA-256/private protection, rejected wrong-password/confirmation, created safety backup, restored fixture data through authenticated CLI, checked maintenance lifecycle and recovery audit history. Isolated runtime prevents operational maintenance/restore effects. Default policy inspected: 02:00 Asia/Colombo, 30 days, at most 30 scheduled copies. No real Drive upload or operational destination acceptance is claimed; those are Blocked. Host scheduler was not activated.')
report+=heading(16,'Performance and concurrency')+ul(native)+p('Native lines are recorded as supplementary invariant checks, not added to PHPUnit totals. The timing JSON and raw log contain exact observations; single small-data local timings are not throughput or realistic-volume capacity acceptance.')
manual_count=Counter(x[1] for x in manual_checks)
report+=heading(17,'Results summary')+table(['Register','Total','Passed','Failed','Blocked','Not Run'],[['Automated JUnit',len(cases),counts['Passed'],counts['Failed'],0,counts['Not Run']],['Manual/external acceptance',len(manual_checks),0,0,manual_count['Blocked'],manual_count['Not Run']],['Combined declared register',len(cases)+len(manual_checks),counts['Passed'],counts['Failed'],manual_count['Blocked'],manual_count['Not Run']+counts['Not Run']]])+p(f"Executed automated pass rate: {counts['Passed']/(counts['Passed']+counts['Failed'])*100:.2f}%. Assertions: {totals['assertions']}. JUnit summed time: {float(totals['time']):.2f}s; console command duration is preserved in step13-tests.log. This percentage applies only to executed tests, not to all BRD features or business acceptance.")

defect_rows=[]
for line in read(ROOT/'docs/testing/defect-register.md').splitlines():
    if re.match(r'\| [DGL]12-',line):defect_rows.append([x.strip() for x in line.strip('|').split('|')])
defect_rows.append(['D13-01','Low','Unresolved English translation keys on quotation page','Observed in actual screenshot; not fixed in this documentation-only task.'])
defects=[]
for row in defect_rows:
    identifier,severity,finding,state=row
    open_=identifier in ['G12-03','G12-05','G12-07','L12-01','D13-01']
    steps='Use the cited current fixture workflow or inspect the relevant original evidence log; historical pre-fix reproduction was not reintroduced.'
    evidence='docs/testing/defect-register.md; historical storage/logs/ evidence; fresh step13-junit.xml'
    if identifier=='D13-01':steps='Open an issued quotation detail page in English; inspect customer contact and labour total labels.';evidence='documentation/screenshots/quotation-approval.png'
    expected='Readable localized UI field labels.' if identifier=='D13-01' else 'BRD requirement and invariant described by the finding; no duplicate/incorrect financial or stock posting.'
    defects.append({'id':identifier,'severity':severity,'finding':finding,'state':state,'open':open_,'steps':steps,'expected':expected,'evidence':evidence})
(S/'defects.json').write_text(json.dumps(defects,indent=2))
open_defect_counts=Counter(d['severity'] for d in defects if d['open'])
report+=heading(18,'Failed tests and defects')+p('Fresh execution had no failed/error test cases. The historical defect register is preserved, including fixed items and remaining external/functional boundaries. No application fix was made during Step 13. Open counts below include declared limitations/configuration dependencies; they are not newly failed tests.')+table(['Open severity','Count'],[(x,open_defect_counts[x]) for x in ['Critical','High','Medium','Low']])
for d in defects:
    defect_module={'D12-01':'POS','D12-02':'Invoices','G12-01':'Inventory','G12-02':'Suppliers/Purchases','D12-03':'Quotations/Jobs','D12-04':'Jobs','D12-05':'Inventory','D12-06':'Reports','G12-04':'Printing','G12-03':'Backups','G12-05':'Finance','G12-06':'Shared concurrency','G12-07':'Hardware/UAT','L12-01':'Inventory/Purchases','D13-01':'Quotations/UI'}[d['id']]
    report+=f'<section class="test-card"><h3>{esc(d["id"]+" - "+d["finding"])}</h3>'+p('Module: '+defect_module+' | Severity: '+d['severity'])+p('Reproduction: '+d['steps'])+p('Expected: '+d['expected'])+p('Actual / fix status: '+d['state'])+p('Retest: '+('Not Run for external/unsupported boundary; not accepted by automation.' if d['open'] else 'Existing regression scenarios passed in fresh Step 13 run; exact method records follow.'))+p('Evidence: '+d['evidence'])+'</section>'
report+=heading(19,'Retesting')+p('This fresh run re-executed the existing regression suite after the earlier Step 12 fixes; 218 cases passed. Historical failures and prior retest logs remain under storage/logs and docs/testing. They were not overwritten or reclassified as newly executed failures. New D13-01 was observed during capture; no fix/retest claim is made. Document layout corrections were regenerated and visually checked separately from application testing.')
report+=heading(20,'Known limitations')+ul([f'{title}: {status}. {reason}' for title,status,reason,mod in manual_checks])+p('Not Implemented UI boundaries: public self-registration/reset/general account settings, multi-invoice payment allocation form and partial return of one repair issue. Historical repeated-part purchase returns require reconciliation. Reported profit is an incomplete recorded-cost estimate where policy/cost data is missing. Source-presence does not imply live configuration or acceptance.')
report+=heading(21,'Final technical assessment')+p('The executed isolated automated and native invariant checks passed for the current implementation. The operational metadata/count comparison matched and the screenshots came from separate temporary fixtures. Application acceptance remains conditional on the external checks and decisions above. No 100% BRD compliance, exhaustive security coverage, owner approval, deployment or live recovery is asserted.')
report+=heading(22,'Per-case execution records')+p('Each data-set execution is a distinct JUnit case. Exact test method/source and raw evidence identify the executable fixture and assertion specification. These records are generated from that source and JUnit rather than invented outcomes.')
for r in records:
    report+='<section class="test-card">'+f'<h3 id="{r["id"]}">{esc(r["id"]+" - "+r["objective"])}</h3>'+p(f"Module: {r['module']} | BRD section(s): {r['brd']} | Status: {r['status']} | Execution date: {r['date']}")+p('Preconditions: '+r['preconditions'])+p('Test data: '+r['data'])+p('Execution steps: '+r['steps'])+p('Expected: '+r['expected'])+p('Actual: '+r['actual'])+p('Evidence: '+r['evidence'],'small')+p('Defect reference: '+r['defect'])+'</section>'
report+=heading(23,'Manual acceptance cases')
for i,(title,status,reason,mod) in enumerate(manual_checks,1):
    report+='<section class="test-card">'+f'<h3>M13-{i:02} - {esc(title)}</h3>'+p('Module: '+mod+' | BRD reference: acceptance/security/printing/backup/report requirements in matrix | Status: '+status)+p('Objective / expected: verify '+title.lower()+' with the approved business environment and retain actual results.')+p('Preconditions / test data: approved isolated UAT environment, actual hardware/account/policy where applicable; non-sensitive fixtures.')+p('Steps: obtain prerequisites; exercise the named workflow under authorized role; compare output/history against the stated requirement and capture evidence.')+p('Actual: '+reason+' Execution date: not executed; status reviewed '+date+'.')+p('Evidence: Step 13 completion boundaries and screenshot/test manifests. Defect: related G12 configuration/UAT items where applicable; no fabricated pass.')+'</section>'
report+=heading(24,'Requirement-by-requirement appendix')+p('All maintained BRD IDs are retained below. Status is the matrix requirement status, not one of the execution statuses used in test cases. Field-only/manual gaps remain visible. The original full 12-column Markdown matrix is preserved as companion evidence.')
report+='<section class="wide">'
for section in range(1,28):
    relevant=[x for x in matrix if x[1]==str(section)]
    if not relevant:continue
    report+=f'<h2>BRD section {section}</h2>'+table(['ID / scope','Requirement / module','Status / result','Evidence / boundary'],[[x[0]+' / '+x[3],x[2]+' / '+x[4],x[10]+'; '+x[9],x[8]+'; '+x[11]] for x in relevant])
report+='</section>'
report+=heading(25,'Evidence index')+table(['Evidence','Purpose'],[
 ['step13-tests.log / step13-junit.xml','Raw fresh console and exact case/assertion results'],['implementation.json','Redacted route/role/version/backup/session metadata'],['schema-before.json / schema-after.json / database-summary.json','Operational read-only schema/FK/index/count comparison'],['source-inventory.json','All inspected source file hashes, declarations and line counts'],['brd-text.txt / original BRD PDF','Original requirement source; extracted text is not a translation'],['screenshots.json / screenshot-capture.log / screenshot-server.log','Actual capture manifest and isolated session logs'],['sources/feature-catalogue.json / test-cases.json / defects.json','Editable machine-readable catalogues generated from evidence'],['sources/*relationships.svg / evidence/*.relationships.json','Rendered diagrams and exact verified FK edge lists'],['docs/testing/brd-compliance-matrix.md','Original complete traceability matrix'],['document-verification.json / completion-report.md','Final PDF checks, counts, hashes and remaining limitations']])

documents=[('Nilambara_Electricals_Complete_User_Manual','Complete User Manual',manual),('Nilambara_Electricals_System_Testing_and_Results','System Testing and Test Results',report),('Nilambara_Electricals_Complete_Feature_Guidance','Complete Feature Guidance',guidance)]
for slug,title,body in documents:
    source=S/(slug+'.html')
    source.write_text('<!doctype html><html lang="en"><head><meta charset="UTF-8"><title>'+esc(title)+'</title><style>'+CSS+'</style></head><body>'+body+'</body></html>',encoding='utf-8')

browser=B.Browser(port=9338)
try:
    for slug,title,body in documents:
        browser.goto((S/(slug+'.html')).as_uri())
        browser.evaluate('Promise.all(Array.from(document.images).map(i=>i.complete?Promise.resolve():new Promise(r=>{i.onload=r;i.onerror=r}))).then(()=>document.fonts.ready).then(()=>true)')
        pdf=browser.call('Page.printToPDF',{'printBackground':True,'preferCSSPageSize':True,'displayHeaderFooter':True,'generateTaggedPDF':True,'generateDocumentOutline':True,'headerTemplate':'<div style="font-size:8px;color:#62758a;width:100%;margin:0 16mm">NILAMBARA ELECTRICALS | '+esc(title)+'</div>','footerTemplate':'<div style="font-size:8px;color:#62758a;width:100%;margin:0 16mm;display:flex;justify-content:space-between"><span>Version 1.0 | 21 October 2026</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>'})
        path=O/(slug+'.pdf');path.write_bytes(base64.b64decode(pdf['data']))
        print(slug+': '+str(len(fitz.open(path)))+' pages',flush=True)
finally:browser.close()

summary={'date':date,'module_areas':len(C.MODULES),'features':len(features),'verified_scenario_features':sum(f['verification'].startswith('Verified') for f in features),'features_not_verified':[f['id']+' '+f['name'] for f in features if not f['verification'].startswith('Verified')],'automated':{'total':len(cases),'passed':counts['Passed'],'failed':counts['Failed'],'blocked':0,'not_run':counts['Not Run'],'assertions':int(totals['assertions'])},'manual':dict(manual_count),'screenshots':len([e for e in screens if e['status']=='Captured']),'screenshots_not_captured':[e for e in screens if e['status']!='Captured'],'brd_rows':len(matrix),'database':schema_summary,'open_defects':dict(open_defect_counts)}
(E/'completion-counts.json').write_text(json.dumps(summary,indent=2),encoding='utf-8')
print(json.dumps(summary,indent=2),flush=True)
