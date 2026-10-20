"""Real Chrome DevTools screenshots of an isolated Laravel fixture application."""
from pathlib import Path
import base64, json, os, subprocess, time, urllib.request
import websocket

ROOT=Path(__file__).resolve().parents[1]

class Browser:
    def __init__(self, port=9337):
        self.port=port
        profile=ROOT/'tmp/pdfs'/f'step13-chrome-{port}'
        profile.mkdir(parents=True,exist_ok=True)
        self.proc=subprocess.Popen([r'C:\Program Files\Google\Chrome\Application\chrome.exe','--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check',f'--remote-debugging-port={port}','--remote-allow-origins=http://localhost:'+str(port),f'--user-data-dir={profile}','--allow-file-access-from-files','about:blank'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,creationflags=subprocess.CREATE_NO_WINDOW)
        for _ in range(80):
            try:
                targets=json.load(urllib.request.urlopen(f'http://127.0.0.1:{port}/json',timeout=1))
                self.ws=websocket.create_connection(next(t['webSocketDebuggerUrl'] for t in targets if t['type']=='page'),origin=f'http://localhost:{port}',timeout=30)
                break
            except Exception: time.sleep(.25)
        else: raise RuntimeError('Chrome DevTools unavailable')
        self.counter=0
        self.call('Page.enable')
        self.call('Runtime.enable')
        self.call('Emulation.setDeviceMetricsOverride',{'width':1440,'height':1000,'deviceScaleFactor':1,'mobile':False})
    def call(self,method,params=None):
        self.counter+=1
        n=self.counter
        self.ws.send(json.dumps({'id':n,'method':method,'params':params or {}}))
        while True:
            reply=json.loads(self.ws.recv())
            if reply.get('id')==n:
                if 'error' in reply: raise RuntimeError(reply['error'])
                return reply.get('result',{})
    def evaluate(self,js):
        result=self.call('Runtime.evaluate',{'expression':js,'returnByValue':True,'awaitPromise':True})
        if 'exceptionDetails' in result: raise RuntimeError(str(result['exceptionDetails']))
        return result.get('result',{}).get('value')
    def goto(self,url):
        self.call('Page.navigate',{'url':url})
        for _ in range(150):
            time.sleep(.1)
            if self.evaluate('document.readyState')=='complete' and self.evaluate('location.href')==url: break
        self.evaluate('document.fonts.ready.then(()=>true)')
        time.sleep(.25)
    def close(self):
        try: self.call('Browser.close')
        except Exception: pass
        self.ws.close()
        try: self.proc.wait(timeout=5)
        except subprocess.TimeoutExpired: self.proc.terminate()

def capture():
    if os.environ.get('APP_ENV')!='testing' or not os.environ.get('DB_DATABASE','').startswith('nilambara_step2_test_'):
        raise RuntimeError('Isolated environment required')
    log=open(ROOT/'documentation/evidence/screenshot-server.log','w')
    server=subprocess.Popen(['php','-S','127.0.0.1:8087',str(ROOT/'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],cwd=ROOT/'public',env=os.environ,stdout=log,stderr=log,creationflags=subprocess.CREATE_NO_WINDOW)
    browser=None
    entries=[]
    def shot(slug,path,title,js=None):
        browser.goto('http://127.0.0.1:8087'+path)
        if js: browser.evaluate(js); time.sleep(.3)
        if slug=='invoice-create':
            selected=browser.evaluate("""(async()=>{const s=document.getElementById('customer-search');s.value='Documentation Customer';s.dispatchEvent(new Event('input',{bubbles:true}));for(let n=0;n<50;n++){await new Promise(r=>setTimeout(r,100));const b=document.querySelector('#customer-results button');if(b){b.click();break}}for(let n=0;n<50;n++){await new Promise(r=>setTimeout(r,100));const c=[...document.querySelectorAll('[data-job-option]')];if(c.length>=2){c.slice(0,2).forEach(x=>x.click());return document.getElementById('summary-job-count').textContent}}return 'not loaded'})()""")
            if selected!='2': raise RuntimeError('Two-job invoice selection was not rendered; do not fabricate a populated screenshot.')
            title='Invoice creation - two eligible same-customer jobs selected (not submitted)'
        text=browser.evaluate('document.body.innerText')
        if 'Server Error' in text or 'Forbidden'==text.strip() or (slug!='login' and '/login' in browser.evaluate('location.pathname')):
            entries.append({'id':slug,'path':path,'caption':title,'status':'Not captured','reason':text[:100]})
            return
        layout=browser.call('Page.getLayoutMetrics')['cssContentSize']
        height=min(max(layout['height'],1000),2600)
        data=browser.call('Page.captureScreenshot',{'format':'png','captureBeyondViewport':True,'clip':{'x':0,'y':0,'width':1440,'height':height,'scale':1}})['data']
        target=ROOT/'documentation/screenshots'/f'{slug}.png'
        target.write_bytes(base64.b64decode(data))
        entries.append({'id':slug,'path':path,'caption':title,'status':'Captured','width':1440,'height':height,'viewport_limited':layout['height']>2600,'fixture_only':True})
        print('Captured '+slug,flush=True)
    try:
        for _ in range(60):
            try: urllib.request.urlopen('http://127.0.0.1:8087/login',timeout=2); break
            except Exception: time.sleep(.2)
        browser=Browser()
        ids=json.loads((ROOT/'documentation/evidence/screenshot-fixtures.json').read_text())
        shot('login','/login','Login page - empty credentials')
        password=os.environ['STEP13_BROWSER_PASSWORD']
        browser.evaluate("document.querySelector('[name=username]').value='documentation.operator';document.querySelector('[name=password]').value="+json.dumps(password)+";document.querySelector('form').submit();true")
        time.sleep(1)
        routes=[('dashboard','/dashboard','Dashboard'),('customers','/customers','Customer list'),('customer-create','/customers/create','Register individual customer'),('company-create','/customers/create','Register company',"document.querySelector('[name=customer_type]').value='company';document.querySelector('[name=customer_type]').dispatchEvent(new Event('change',{bubbles:true}));true"),('customer-profile',f"/customers/{ids['customer']}",'Customer profile'),('customer-statement',f"/customers/{ids['customer']}/statement",'Customer statement'),('receipt-create','/repair-receipts/create','Repair receipt registration'),('receipts','/repair-receipts','Repair receipt list'),('receipt-preview',f"/repair-receipts/{ids['receipt']}/print",'A5 receipt preview'),('jobs','/repair-jobs','Repair jobs'),('job-detail',f"/repair-jobs/{ids['open_job']}",'Job details and status history'),('job-assign',f"/repair-jobs/{ids['open_job']}/assign",'Technician assignment'),('job-card',f"/repair-jobs/{ids['job']}/job-card",'A6 job card preview'),('job-status','/repair-jobs/status','Job status tracking'),('quotation-create','/quotations/create','Quotation creation'),('quotation-preview',f"/quotations/{ids['quotation']}/print",'Quotation preview'),('quotation-approval',f"/quotations/{ids['quotation']}",'Quotation details and customer decision'),('invoice-create',f"/invoices/create?customer_id={ids['customer']}",'Invoice creation and eligible job selection'),('invoice-preview',f"/invoices/{ids['invoice']}/print",'A5 invoice preview'),('payment-create',f"/payments/create?invoice_id={ids['invoice']}",'Payment entry'),('payment-detail',f"/payments/{ids['payment']}",'Payment details'),('credit','/credit-invoices','Credit invoice management'),('inventory','/inventory','Inventory list'),('part-create','/inventory/create','Spare part registration'),('stock-movements',f"/inventory/{ids['part']}",'Part details and movements'),('stock-issues','/stock-issues','Repair part requests and issue'),('supplier-create','/suppliers/create','Supplier registration'),('supplier-detail',f"/suppliers/{ids['supplier']}",'Supplier account'),('purchases','/purchases','Purchase management'),('purchase-create','/purchases/create','Purchase draft entry'),('grn',f"/purchases/{ids['purchase']}",'Purchase details and GRN receiving'),('grn-preview',f"/goods-received-notes/{ids['grn']}/document/html",'GRN document preview'),('pos','/pos','POS checkout'),('pos-receipt',f"/pos/sales/{ids['sale']}/print",'POS receipt preview'),('pos-sales','/pos/sales','POS sales history'),('reports','/reports','Report catalogue'),('report-detail','/reports/stock','Stock report and export actions'),('employees','/employees','Employee management'),('employee-create','/employees/create','Employee registration'),('roles','/admin/roles','Roles and permissions'),('settings','/settings','System settings'),('print-templates','/print-templates','Print template management'),('backups','/admin/backups','Backup management - unconfigured destination'),('audit','/audit-logs','Audit log')]
        for entry in routes: shot(*entry)
        # Actual English/Sinhala UI label comes from the application translation file.
        shot('sinhala-login','/login','Login route after authentication (redirect may apply)') if False else None
    finally:
        (ROOT/'documentation/evidence/screenshots.json').write_text(json.dumps(entries,indent=2),encoding='utf-8')
        if browser: browser.close()
        server.terminate()
        server.wait(timeout=10)
        log.close()

if __name__=='__main__': capture()
