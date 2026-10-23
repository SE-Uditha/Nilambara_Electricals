<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'nilambara_repair_management' || ! in_array(config('database.connections.mysql.host'), ['localhost', '127.0.0.1'], true) || $app->isProduction()) {
    throw new RuntimeException('Only the configured local development database may be inspected.');
}
$manifest = storage_path('app/private/final-billing-snapshot.txt');
$mode = $argv[1] ?? '';
if ($mode === 'before') {
    if (Schema::hasTable('repair_job_charges')) {
        echo 'Existing charge table rows: '.DB::table('repair_job_charges')->count().PHP_EOL;
        throw new RuntimeException('The table already exists; inspect migration status before proceeding.');
    }
    $snapshot = ['tables' => [], 'ddl' => []];
    foreach (DB::select('SHOW TABLES') as $table) {
        $name = array_values((array) $table)[0];
        if (! preg_match('/\A[a-zA-Z0-9_]+\z/', $name)) {
            throw new RuntimeException('Unexpected table name.');
        }
        $snapshot['tables'][$name] = DB::table($name)->get()->map(fn ($row) => (array) $row)->all();
        $snapshot['ddl'][$name] = (array) DB::selectOne('SHOW CREATE TABLE `'.$name.'`');
    }
    $path = storage_path('app/private/pre-final-billing-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json.enc');
    $json = json_encode($snapshot, JSON_THROW_ON_ERROR);
    file_put_contents($path, Crypt::encryptString($json));
    if (! hash_equals(hash('sha256', $json), hash('sha256', Crypt::decryptString(file_get_contents($path))))) {
        throw new RuntimeException('Snapshot validation failed.');
    }
    file_put_contents($manifest, basename($path));
    echo 'Verified encrypted recovery snapshot: '.basename($path).PHP_EOL;
    exit;
}
if ($mode !== 'after') {
    throw new RuntimeException('Use before or after.');
}
$path = storage_path('app/private/'.basename(trim(file_get_contents($manifest))));
$before = json_decode(Crypt::decryptString(file_get_contents($path)), true, flags: JSON_THROW_ON_ERROR)['tables'];
$canonical = function (array $rows): array {
    $rows = array_map(function ($row) {
        ksort($row);

        return json_encode($row, JSON_THROW_ON_ERROR);
    }, $rows);
    sort($rows);

    return $rows;
};
$allowed = ['sessions', 'migrations', 'cache', 'cache_locks'];
$checked = 0;
foreach ($before as $table => $rows) {
    if (in_array($table, $allowed, true)) {
        continue;
    }
    $now = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
    if ($canonical($rows) !== $canonical($now)) {
        throw new RuntimeException('Unexpected record change in '.$table);
    }
    $checked++;
}
if (! Schema::hasTable('repair_job_charges') || DB::table('repair_job_charges')->count() !== 0) {
    throw new RuntimeException('Expected a new, empty charge table.');
}
echo 'PASS: all pre-existing records in '.$checked.' tables are unchanged; the new charge table is empty.'.PHP_EOL;
echo 'Recovery snapshot: '.basename($path).PHP_EOL;
