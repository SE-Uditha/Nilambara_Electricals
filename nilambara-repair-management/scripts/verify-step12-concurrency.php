<?php

use App\Models\User;
use App\Modules\Invoices\Services\InvoiceService;
use App\Modules\Payments\Services\PaymentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Database\CreatesDatabaseRecords;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
});
if (! $app->environment('testing') || DB::getDriverName() !== 'mysql'
    || ! preg_match('/\Anilambara_step2_test_[0-9a-f]{12}\z/', DB::getDatabaseName())) {
    throw new RuntimeException('Requires a fresh disposable database owned by verify-mysql.php.');
}
if (($argv[1] ?? '') === 'worker') {
    $input = json_decode(base64_decode($argv[2]), true, flags: JSON_THROW_ON_ERROR);
    $actor = User::findOrFail($input['actor']);
    echo "READY\n";
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'GO') {
        throw new RuntimeException('Missing start barrier.');
    }
    try {
        $record = $input['operation'] === 'invoice'
            ? app(InvoiceService::class)->create($input['data'], $actor)
            : app(PaymentService::class)->receiveInvoicePayment($input['data'], $actor);
        echo 'SAVED:'.$record->getKey().PHP_EOL;
    } catch (ValidationException $exception) {
        echo 'REJECTED:'.json_encode($exception->errors()).PHP_EOL;
    }
    exit;
}
(new RoleSeeder)->run();
$fixture = new class
{
    use CreatesDatabaseRecords;

    public function make(): array
    {
        $actors = [];
        for ($i = 0; $i < 2; $i++) {
            $actor = User::factory()->create();
            $actor->assignRole('cashier');
            $this->employee(['user_id' => $actor->id, 'is_active' => true]);
            $actors[] = $actor->id;
        }
        $customer = $this->customer();
        $job = $this->job($customer, ['status' => 'Repair Completed', 'labour_charge' => '100.00', 'parts_charge' => '0.00', 'total_job_amount' => '100.00']);

        return [$actors, $customer->id, $job->id];
    }

    public function product(): int
    {
        $location = $this->location();
        $part = $this->part(['selling_price' => '10.0000', 'default_location_id' => $location->id, 'is_active' => true]);
        $this->movement($part, $location, ['quantity_delta' => '5.000']);

        return $part->id;
    }
};
[$actors, $customer, $job] = $fixture->make();
$race = static function (string $operation, array $data) use ($actors): array {
    $workers = [];
    foreach ($actors as $actor) {
        if ($operation === 'payment') {
            $data['idempotency_key'] = (string) Str::uuid();
        }
        $payload = base64_encode(json_encode(['actor' => $actor, 'operation' => $operation, 'data' => $data], JSON_THROW_ON_ERROR));
        $process = proc_open([PHP_BINARY, '-c', php_ini_loaded_file(), __FILE__, 'worker', $payload],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), getenv());
        if (! is_resource($process)) {
            throw new RuntimeException('Worker startup failed.');
        }
        stream_set_timeout($pipes[1], 30);
        if (trim((string) fgets($pipes[1])) !== 'READY') {
            throw new RuntimeException('Worker failed before barrier: '.stream_get_contents($pipes[2]));
        }
        $workers[] = [$process, $pipes];
    }
    $start = hrtime(true);
    foreach ($workers as [$process, $pipes]) {
        fwrite($pipes[0], "GO\n");
        fclose($pipes[0]);
    }
    $results = [];
    foreach ($workers as [$process, $pipes]) {
        $results[] = trim(stream_get_contents($pipes[1]));
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Worker failed: '.$error);
        }
    }
    if (count(array_filter($results, static fn ($value) => str_starts_with($value, 'SAVED:'))) !== 1) {
        throw new RuntimeException('Expected one save and one rejection: '.json_encode($results));
    }
    echo 'PASS: two simultaneous cashiers, '.$operation.', one save and one rejection; barrier-to-completion '.round((hrtime(true) - $start) / 1e6, 3)." ms.\n";

    return $results;
};
$results = $race('invoice', ['customer_id' => $customer, 'repair_job_ids' => [$job],
    'invoice_date' => now()->toDateString(), 'payment_terms' => 'cash', 'payment_method' => 'cash', 'intent' => 'issue']);
$invoiceId = (int) substr(current(array_filter($results, static fn ($value) => str_starts_with($value, 'SAVED:'))), 6);
$race('payment', ['invoice_id' => $invoiceId, 'amount' => '100.00', 'method' => 'cash',
    'payment_date' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid()]);
if ((string) DB::table('payment_allocations')->where('invoice_id', $invoiceId)->sum('amount') !== '100.00') {
    throw new RuntimeException('Concurrent payment allocation mismatch.');
}
echo "PASS: one invoice-job reservation and exactly 100.00 allocated, no overpayment.\n";

// One local observation per operation; fixture construction is excluded.
[$benchmarkActors, $benchmarkCustomer, $benchmarkJob] = $fixture->make();
$actor = User::findOrFail($benchmarkActors[0]);
$timings = [];
$measure = static function (string $operation, callable $callback) use (&$timings) {
    $start = hrtime(true);
    $result = $callback();
    $timings[$operation] = round((hrtime(true) - $start) / 1e6, 3);

    return $result;
};
$invoice = $measure('invoice-create-and-issue', static fn () => app(InvoiceService::class)->create([
    'customer_id' => $benchmarkCustomer, 'repair_job_ids' => [$benchmarkJob], 'invoice_date' => now()->toDateString(),
    'payment_terms' => 'cash', 'payment_method' => 'cash', 'intent' => 'issue',
], $actor));
$measure('payment-receive-and-allocate', static fn () => app(PaymentService::class)->receiveInvoicePayment([
    'invoice_id' => $invoice->id, 'amount' => '100.00', 'method' => 'cash',
    'payment_date' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid(),
], $actor));
$part = $fixture->product();
$measure('pos-checkout-including-stock-posting', static fn () => app(\App\Modules\POS\Services\POSCheckoutService::class)->checkout([
    'payment_method' => 'cash', 'amount_tendered' => '10.00', 'discount_amount' => '0.00', 'idempotency_key' => (string) Str::uuid(),
], [$part => '1.000'], $actor));
$pdf = $measure('a5-invoice-pdf-with-print-history', static function () use ($invoice, $actor): string {
    $data = app(\App\Modules\Invoices\Services\InvoicePrintService::class)->prepare($invoice, false, true, $actor);

    return \Barryvdh\DomPDF\Facade\Pdf::loadView('modules.invoices.print', $data)->setPaper('a5')->output();
});
if (! str_starts_with($pdf, '%PDF-')) {
    throw new RuntimeException('Benchmark invoice PDF failed to render.');
}
file_put_contents(storage_path('logs/step12-service-timings.json'), json_encode([
    'driver' => 'mysql', 'iterations' => 1, 'users' => 1, 'milliseconds' => $timings,
    'dataset_rows' => collect(['customers', 'repair_jobs', 'invoices', 'payments', 'pos_sales', 'spare_parts', 'stock_movements'])
        ->mapWithKeys(static fn ($table) => [$table => DB::table($table)->count()])->all(),
    'pdf_bytes' => strlen($pdf), 'limitations' => 'Single local small-fixture observations, no capacity claim; POS timing includes stock posting, not missing repair/GRN operations.',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'PASS: service/PDF observations recorded: '.json_encode($timings).PHP_EOL;
