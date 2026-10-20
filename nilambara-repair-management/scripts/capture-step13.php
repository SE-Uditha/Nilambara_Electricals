<?php
// Screenshots use a fresh disposable database. Never populate the shop database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (($argv[1] ?? '') === 'fixtures') {
    if (!app()->environment('testing') || !preg_match('/\Anilambara_step2_test_[0-9a-f]{12}\z/', DB::getDatabaseName())) {
        throw new RuntimeException('Screenshot fixtures require a fresh isolated test database.');
    }
    (new Database\Seeders\RoleSeeder)->run();
    $user = App\Models\User::factory()->create(['name'=>'Documentation Operator (Test)', 'username'=>'documentation.operator', 'password'=>getenv('STEP13_BROWSER_PASSWORD'), 'is_active'=>true]);
    $user->assignRole('administrator');
    $fixture = new class { use Tests\Feature\Database\CreatesDatabaseRecords;
        public function make($user) {
            $employee=$this->employee(['name'=>'Documentation Staff (Test)', 'user_id'=>$user->id, 'is_active'=>true]);
            $customer=$this->customer(['customer_name'=>'Documentation Customer (Test)', 'address'=>'Isolated documentation fixture', 'mobile_number'=>'0000000000', 'registration_date'=>now()->toDateString()]);
            $company=$this->customer(['customer_type'=>'company','customer_name'=>'Documentation Company (Test)','company_name'=>'Documentation Company (Test)','address'=>'Isolated documentation fixture','mobile_number'=>'0000000000','registration_date'=>now()->toDateString()]);
            $location=$this->location();
            $part=$this->part(['name'=>'Demonstration Bearing (Test)','sku'=>'DOC-BEARING','barcode'=>'DOC-100','default_location_id'=>$location->id,'selling_price'=>'10.0000','minimum_stock_level'=>'4.000']);
            $this->movement($part,$location,['quantity_delta'=>'20.000','unit_cost'=>'3.0000','occurred_at'=>now()]);
            return [$employee,$customer,$company,$part,$location];
        }
    };
    [$employee,$customer,$company,$part,$location]=$fixture->make($user);
    $receipts=[];
    foreach ([1,2,3,4] as $n) {
        $receipts[]=app(App\Modules\RepairReceipts\Services\RepairReceiptService::class)->register(['customer_id'=>$customer->id,'job_number'=>'DOC-JOB-'.$n,'item_name'=>'Demonstration Motor (Test)','brand'=>'Documentation','model'=>'DOC-01','job_type'=>'normal','received_by_employee_id'=>$employee->id,'labor_cost'=>'2.00','advance_payment'=>$n===1?'5.00':'0.00','payment_method'=>'cash','reported_fault'=>'Documentation fixture - inspection example','accessories_received'=>'Test cable','physical_condition'=>'Training fixture only'],$user);
    }
    $jobs=app(App\Modules\RepairJobs\Services\RepairJobService::class);
    $first=$receipts[0]->repairJob;
    $quoteData=['repair_job_id'=>$first->id,'quotation_date'=>now()->toDateString(),'valid_until'=>now()->addDays(7)->toDateString(),'proposed_repair_description'=>'Documentation fixture - replace bearing','labour_charge'=>'10.00','items'=>[['spare_part_id'=>$part->id,'description'=>'Demonstration bearing','quantity'=>'2.000','unit_price'=>'10.0000']],'intent'=>'issue'];
    $quote=app(App\Modules\Quotations\Services\QuotationService::class)->create($quoteData,$user);
    app(App\Modules\Quotations\Services\QuotationApprovalService::class)->recordDecision($quote,['decision'=>'approved','decision_date'=>now()->toDateString(),'customer_contact_name'=>'Documentation Contact (Test)','approval_method'=>'telephone'],$user);
    $usage=app(App\Modules\Inventory\Services\StockWorkflowService::class)->request(['repair_job_id'=>$first->id,'spare_part_id'=>$part->id,'location_id'=>$location->id,'quantity'=>'2.000','idempotency_key'=>(string)Str::uuid()],$user);
    app(App\Modules\Inventory\Services\StockWorkflowService::class)->issue($usage,$user);
    $jobs->updateStatus($first,['status'=>'Repair Completed'],$user);
    $invoice=app(App\Modules\Invoices\Services\InvoiceService::class)->create(['customer_id'=>$customer->id,'repair_job_ids'=>[$first->id],'invoice_date'=>now()->toDateString(),'payment_terms'=>'credit','due_date'=>now()->addDays(14)->toDateString(),'intent'=>'issue'],$user);
    $payment=app(App\Modules\Payments\Services\PaymentService::class)->receiveInvoicePayment(['invoice_id'=>$invoice->id,'amount'=>'10.00','method'=>'cash','payment_date'=>now()->toDateString(),'idempotency_key'=>(string)Str::uuid()],$user);
    $jobs->setLabourCharge($receipts[1]->repairJob,'15.00',$user);
    $jobs->updateStatus($receipts[1]->repairJob,['status'=>'Repair Completed'],$user);
    $jobs->setLabourCharge($receipts[3]->repairJob,'18.00',$user);
    $jobs->updateStatus($receipts[3]->repairJob,['status'=>'Repair Completed'],$user);
    $quoteData['repair_job_id']=$receipts[2]->repairJob->id;
    $pendingQuote=app(App\Modules\Quotations\Services\QuotationService::class)->create($quoteData,$user);
    $supplier=App\Modules\Suppliers\Models\Supplier::create(['supplier_code'=>'DOC-SUP','name'=>'Documentation Supplier (Test)','address'=>'Isolated documentation fixture','is_active'=>true]);
    $purchase=app(App\Modules\Purchases\Services\PurchaseWorkflowService::class)->create(['supplier_id'=>$supplier->id,'purchase_date'=>now()->toDateString(),'payment_terms'=>'credit','discount_amount'=>'0.00','other_costs'=>'0.00','idempotency_key'=>(string)Str::uuid(),'items'=>[['spare_part_id'=>$part->id,'quantity'=>'5.000','unit_cost'=>'3.0000']]],$user);
    app(App\Modules\Purchases\Services\PurchaseWorkflowService::class)->finalize($purchase,$user);
    $grn=app(App\Modules\Purchases\Services\PurchaseWorkflowService::class)->receive($purchase,['purchase_item_id'=>$purchase->items()->first()->id,'location_id'=>$location->id,'quantity'=>'2.000','idempotency_key'=>(string)Str::uuid()],$user);
    $sale=app(App\Modules\POS\Services\POSCheckoutService::class)->checkout(['payment_method'=>'cash','amount_tendered'=>'20.00','discount_amount'=>'0.00','idempotency_key'=>(string)Str::uuid()],[$part->id=>'1.000'],$user);
    $ids=['customer'=>$customer->id,'company'=>$company->id,'receipt'=>$receipts[0]->id,'job'=>$first->id,'open_job'=>$receipts[2]->repair_job_id,'quotation'=>$pendingQuote->id,'invoice'=>$invoice->id,'payment'=>$payment->id,'part'=>$part->id,'supplier'=>$supplier->id,'purchase'=>$purchase->id,'grn'=>$grn->id,'sale'=>$sale->id,'employee'=>$employee->id];
    file_put_contents(base_path('documentation/evidence/screenshot-fixtures.json'),json_encode($ids,JSON_PRETTY_PRINT));
    echo "Isolated screenshot fixtures created.\n";
    exit;
}
$cfg=config('database.connections.mysql');
if (app()->isProduction() || !in_array($cfg['host'],['127.0.0.1','localhost','::1'],true) || !empty($cfg['url'])) throw new RuntimeException('Local nonproduction only.');
$name='nilambara_step2_test_'.bin2hex(random_bytes(6));
$pdo=new PDO('mysql:host='.$cfg['host'].';port='.$cfg['port'].';charset=utf8mb4',$cfg['username'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$exists=$pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?'); $exists->execute([$name]);
if ($exists->fetchColumn()) throw new RuntimeException('Refusing existing database.');
$pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$env=array_merge(getenv(),['APP_ENV'=>'testing','APP_DEBUG'=>'false','APP_URL'=>'http://127.0.0.1:8087','DB_CONNECTION'=>'mysql','DB_DATABASE'=>$name,'DB_HOST'=>(string)$cfg['host'],'DB_PORT'=>(string)$cfg['port'],'DB_USERNAME'=>(string)$cfg['username'],'DB_PASSWORD'=>(string)$cfg['password'],'DB_URL'=>'','CACHE_STORE'=>'array','SESSION_DRIVER'=>'database','SESSION_COOKIE'=>'nilambara_step13_session','SESSION_SECURE_COOKIE'=>'false','QUEUE_CONNECTION'=>'sync','BCRYPT_ROUNDS'=>'4','STEP13_BROWSER_PASSWORD'=>bin2hex(random_bytes(16))]);
$run=function(array $args) use ($env) { $p=proc_open($args,[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes,dirname(__DIR__),$env); fclose($pipes[0]); if(proc_close($p)!==0) throw new RuntimeException('Screenshot preparation/capture failed.'); };
try {
    $run([PHP_BINARY,'artisan','migrate','--force','--no-interaction']);
    $run([PHP_BINARY,__FILE__,'fixtures']);
    $run(['python','scripts/capture-step13-browser.py']);
} finally {
    $pdo->exec('DROP DATABASE `'.$name.'`');
    echo "Removed screenshot-only disposable database.\n";
}
