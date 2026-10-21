<?php

use App\Models\User;
use App\Modules\Inventory\Models\JobPartUsage;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\StockWorkflowService;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Purchases\Services\PurchaseWorkflowService;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\RepairJobs\Services\RepairJobService;
use App\Modules\RepairReceipts\Services\RepairReceiptService;
use App\Modules\Suppliers\Models\Supplier;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Database\CreatesDatabaseRecords;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, $e->getMessage().PHP_EOL);
    exit(1);
});
if (! $app->environment('testing') || DB::getDriverName() !== 'mysql' || ! preg_match('/\Anilambara_step2_test_[0-9a-f]{12}\z/', DB::getDatabaseName())) {
    throw new RuntimeException('Fresh disposable MySQL database required.');
}

if (($argv[1] ?? '') === 'worker') {
    $input = json_decode(base64_decode($argv[2]), true, flags: JSON_THROW_ON_ERROR);
    $actor = User::findOrFail($input['actor']);
    echo "READY\n";
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'GO') {
        throw new RuntimeException('Missing barrier.');
    }
    try {
        $data = $input['data'];
        switch ($input['operation']) {
            case 'intake':
                Gate::forUser($actor)->authorize('receipts.create');
                $record = app(RepairReceiptService::class)->register($data, $actor);
                break;
            case 'status':
                $job = RepairJob::findOrFail($data['job_id']);
                Gate::forUser($actor)->authorize('updateStatus', $job);
                $record = app(RepairJobService::class)->updateStatus($job, ['status' => 'Under Inspection', 'notes' => 'Concurrent assigned technician update'], $actor);
                break;
            case 'issue':
                $record = app(StockWorkflowService::class)->issue(JobPartUsage::findOrFail($data['usage']), $actor);
                break;
            case 'grn':
                $record = app(PurchaseWorkflowService::class)->receive(Purchase::findOrFail($data['purchase']), $data, $actor);
                break;
            default: throw new RuntimeException('Unknown operation.');
        }
        echo 'SAVED:'.$record->id.PHP_EOL;
    } catch (ValidationException $e) {
        echo 'REJECTED:'.json_encode($e->errors()).PHP_EOL;
    }
    exit;
}

$app->make(RoleSeeder::class)->run();

$fixtures = new class
{
    use CreatesDatabaseRecords;

    public function actor(string $role): User
    {
        $actor = User::factory()->create();
        $actor->assignRole($role);
        $this->employee(['user_id' => $actor->id, 'is_active' => true]);

        return $actor;
    }

    public function customerRecord()
    {
        return $this->customer();
    }

    public function jobRecord($customer, array $data = [])
    {
        return $this->job($customer, $data);
    }

    public function product()
    {
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id, 'selling_price' => '10.0000']);
        $this->movement($part, $location, ['quantity_delta' => '5.000']);

        return $part;
    }
};
$race = static function (string $label, array $inputs, int $expected): array {
    $workers = [];
    foreach ($inputs as $input) {
        $payload = base64_encode(json_encode($input, JSON_THROW_ON_ERROR));
        $process = proc_open([PHP_BINARY, '-c', php_ini_loaded_file(), __FILE__, 'worker', $payload], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), getenv());
        if (! is_resource($process)) {
            throw new RuntimeException('Worker startup failed.');
        }
        stream_set_timeout($pipes[1], 30);
        if (trim((string) fgets($pipes[1])) !== 'READY') {
            throw new RuntimeException('Worker not ready: '.stream_get_contents($pipes[2]));
        }
        $workers[] = [$process, $pipes];
    }
    $start = hrtime(true);
    foreach ($workers as [$process,$pipes]) {
        fwrite($pipes[0], "GO\n");
        fclose($pipes[0]);
    }
    $results = [];
    foreach ($workers as [$process,$pipes]) {
        $results[] = trim(stream_get_contents($pipes[1]));
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Worker failure: '.$error);
        }
    }
    if (count(array_filter($results, fn ($r) => str_starts_with($r, 'SAVED:'))) !== $expected) {
        throw new RuntimeException($label.' results: '.json_encode($results));
    }
    echo 'PASS: '.$label.'; 2 concurrent workers; '.round((hrtime(true) - $start) / 1e6, 3).' ms; '.json_encode($results).PHP_EOL;

    return $results;
};
$customer = $fixtures->customerRecord();
$receivers = [$fixtures->actor('cashier'), $fixtures->actor('cashier')];
$before = DB::table('repair_receipts')->count();
$inputs = [];
foreach ($receivers as $index => $actor) {
    $inputs[] = ['operation' => 'intake', 'actor' => $actor->id, 'data' => ['customer_id' => $customer->id, 'job_number' => 'RACE-'.Str::uuid(), 'item_name' => 'Motor', 'brand' => 'Test', 'job_type' => 'normal', 'received_by_employee_id' => $actor->employee->id, 'labor_cost' => '0.00', 'advance_payment' => '0.00']];
}
$race('two cashiers create atomic receipts and unique jobs', $inputs, 2);
if (DB::table('repair_receipts')->count() !== $before + 2) {
    throw new RuntimeException('Receipt race count mismatch.');
}
$inputs = [];
$jobs = [];
foreach (range(1, 2) as $index) {
    $actor = $fixtures->actor('technician');
    $job = $fixtures->jobRecord($customer, ['technician_id' => $actor->employee->id]);
    $jobs[] = $job->id;
    $inputs[] = ['operation' => 'status', 'actor' => $actor->id, 'data' => ['job_id' => $job->id]];
}
$race('two technicians update their assigned jobs with retained histories', $inputs, 2);
if (DB::table('job_status_histories')->whereIn('repair_job_id', $jobs)->count() !== 2) {
    throw new RuntimeException('Technician history count mismatch.');
}
$admin = $fixtures->actor('administrator');
$storekeepers = [$fixtures->actor('storekeeper'), $fixtures->actor('storekeeper')];
$part = $fixtures->product();
$job = $fixtures->jobRecord($customer);
$usage = app(StockWorkflowService::class)->request(['repair_job_id' => $job->id, 'spare_part_id' => $part->id, 'location_id' => $part->default_location_id, 'quantity' => '4.000', 'idempotency_key' => (string) Str::uuid()], $admin);
$otherJob = $fixtures->jobRecord($customer);
$otherUsage = app(StockWorkflowService::class)->request(['repair_job_id' => $otherJob->id, 'spare_part_id' => $part->id, 'location_id' => $part->default_location_id, 'quantity' => '4.000', 'idempotency_key' => (string) Str::uuid()], $admin);
$race('two repair issues compete for the same five units', [['operation' => 'issue', 'actor' => $storekeepers[0]->id, 'data' => ['usage' => $usage->id]], ['operation' => 'issue', 'actor' => $storekeepers[1]->id, 'data' => ['usage' => $otherUsage->id]]], 1);
if ((string) app(InventoryService::class)->availableAtDefaultLocation($part) !== '1.000') {
    throw new RuntimeException('Shared stock was oversold.');
}
$supplier = Supplier::create(['supplier_code' => 'RACE-'.Str::ulid(), 'name' => 'Disposable race supplier']);
$purchase = app(PurchaseWorkflowService::class)->create(['supplier_id' => $supplier->id, 'purchase_date' => now()->toDateString(), 'payment_terms' => 'credit', 'discount_amount' => '0.00', 'other_costs' => '0.00', 'idempotency_key' => (string) Str::uuid(), 'items' => [['spare_part_id' => $part->id, 'quantity' => '5.000', 'unit_cost' => '2.0000']]], $admin);
app(PurchaseWorkflowService::class)->finalize($purchase, $admin);
$inputs = [];
foreach ($storekeepers as $actor) {
    $inputs[] = ['operation' => 'grn', 'actor' => $actor->id, 'data' => ['purchase' => $purchase->id, 'purchase_item_id' => $purchase->items()->sole()->id, 'location_id' => $part->default_location_id, 'quantity' => '4.000', 'idempotency_key' => (string) Str::uuid()]];
}
$race('two storekeepers cannot over-receive a purchase', $inputs, 1);
if ($purchase->goodsReceivedNoteItems()->sum('quantity') !== '4.000') {
    throw new RuntimeException('Concurrent GRN quantity mismatch.');
}
echo "PASS: receipt, technician, shared stock and GRN race invariants verified in disposable MySQL.\n";
