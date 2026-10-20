<?php

use App\Models\User;
use App\Modules\POS\Models\PosSale;
use App\Modules\POS\Services\POSCheckoutService;
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
    throw new RuntimeException('Concurrency fixtures require the disposable verify-mysql.php database.');
}

if (($argv[1] ?? '') === 'worker') {
    $input = json_decode(base64_decode($argv[2]), true, flags: JSON_THROW_ON_ERROR);
    $actor = User::findOrFail($input['actor']);
    $ready = false;
    try {
        $sale = DB::transaction(function () use ($input, $actor, &$ready) {
            // Establish a repeatable-read snapshot before either cashier wins.
            DB::table('stock_movements')->count();
            if (! $ready) {
                echo "READY\n";
                fflush(STDOUT);
                if (trim((string) fgets(STDIN)) !== 'GO') {
                    throw new RuntimeException('Missing concurrency start signal.');
                }
                $ready = true;
            }

            return app(POSCheckoutService::class)->checkout([
                'payment_method' => 'cash', 'amount_tendered' => '100.00',
                'discount_amount' => '0.00', 'idempotency_key' => $input['key'],
            ], [$input['part'] => $input['quantity']], $actor);
        }, 5);
        echo 'SOLD:'.$sale->sale_number."\n";
    } catch (ValidationException $exception) {
        echo 'REJECTED:'.json_encode($exception->errors())."\n";
    }
    exit;
}

(new RoleSeeder)->run();
$fixtures = new class
{
    use CreatesDatabaseRecords;

    public function actor(): User
    {
        $actor = User::factory()->create();
        $actor->assignRole('cashier');
        $this->employee(['user_id' => $actor->id, 'is_active' => true]);

        return $actor;
    }

    public function product(string $stock): int
    {
        $location = $this->location();
        $part = $this->part(['selling_price' => '10.0000', 'default_location_id' => $location->id, 'is_active' => true]);
        $this->movement($part, $location, ['quantity_delta' => $stock]);

        return $part->id;
    }
};
$actors = [$fixtures->actor()->id, $fixtures->actor()->id];
$race = function (int $part, string $quantity, bool $sameKey = false) use ($actors): array {
    $processes = [];
    $key = (string) Str::uuid();
    foreach ($actors as $index => $actor) {
        $payload = base64_encode(json_encode([
            'actor' => $sameKey ? $actors[0] : $actor, 'part' => $part, 'quantity' => $quantity,
            'key' => $sameKey ? $key : (string) Str::uuid(),
        ], JSON_THROW_ON_ERROR));
        $process = proc_open([PHP_BINARY, '-c', php_ini_loaded_file(), __FILE__, 'worker', $payload],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), getenv());
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start checkout worker.');
        }
        stream_set_timeout($pipes[1], 30);
        $processes[] = [$process, $pipes];
        if (trim((string) fgets($pipes[1])) !== 'READY') {
            throw new RuntimeException('Checkout worker did not become ready: '.stream_get_contents($pipes[2]));
        }
    }
    foreach ($processes as [$process, $pipes]) {
        fwrite($pipes[0], "GO\n");
        fclose($pipes[0]);
    }
    $results = [];
    foreach ($processes as [$process, $pipes]) {
        $results[] = trim(stream_get_contents($pipes[1]));
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Checkout worker failed: '.$error);
        }
    }

    return $results;
};
$assert = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$part = $fixtures->product('5.000');
$results = $race($part, '4.000');
$assert(count(array_filter($results, fn ($result) => str_starts_with($result, 'SOLD:'))) === 1, 'Exactly one cashier must win: '.json_encode($results));
$assert((string) DB::table('stock_movements')->where('spare_part_id', $part)->sum('quantity_delta') === '1.000', 'Concurrent checkout oversold stock.');
echo "PASS: two cashiers buying four of five units; one sale, one rejection, one unit remains.\n";
$part = $fixtures->product('10.000');
$before = PosSale::count();
$results = $race($part, '1.000');
$assert(count(array_unique($results)) === 2 && PosSale::count() === $before + 2, 'Concurrent sale numbers must be distinct.');
echo "PASS: simultaneous independent checkouts receive distinct sale numbers.\n";
$before = PosSale::count();
$results = $race($part, '1.000', true);
$assert(count(array_unique($results)) === 1 && PosSale::count() === $before + 1, 'Concurrent retries must create only one sale.');
$assert(DB::table('payments')->where('payment_type', 'pos')->count() === PosSale::count(), 'Payment and sale counts differ.');
echo "PASS: simultaneous identical retries create one sale and one payment.\n";
