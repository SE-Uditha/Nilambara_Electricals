<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Read-only verification of the owner-requested operational role consolidation.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (DB::getDriverName() !== 'mysql' || DB::getDatabaseName() !== 'nilambara_repair_management') {
    throw new RuntimeException('Requires the named operational database for read-only verification.');
}
$tables = ['users', 'employees', 'customers', 'repair_jobs', 'repair_receipts', 'quotations', 'invoices', 'payments', 'stock_movements', 'purchases', 'suppliers', 'pos_sales', 'permission_user', 'permissions'];
$current = ['captured_at' => date(DATE_ATOM), 'protected_tables' => []];
foreach ($tables as $table) {
    $rows = DB::table($table)->orderBy('id')->get();
    $current['protected_tables'][$table] = ['rows' => $rows->count(), 'sha256' => hash('sha256', $rows->toJson())];
}
$current['roles'] = DB::table('roles')->orderBy('id')->get()->toArray();
$current['role_permissions'] = DB::table('permission_role')->orderBy('id')->get()->toArray();
$current['assignments'] = DB::table('role_user')->orderBy('id')->get()->toArray();
$path = storage_path('logs/cashier-role-consolidation-before.json');
if (($argv[1] ?? '') === 'before') {
    if (file_exists($path)) {
        throw new RuntimeException('Before snapshot already exists; do not overwrite original evidence.');
    }
    file_put_contents($path, json_encode($current, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo "Read-only role and protected-record fingerprint snapshot saved.\n";
    exit;
}
if (($argv[1] ?? '') !== 'after') {
    throw new RuntimeException('Specify before or after.');
}
$before = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
if ($before['protected_tables'] !== $current['protected_tables']) {
    throw new RuntimeException('Protected records changed; inspect fingerprints before claiming preservation.');
}
$roles = collect($current['roles']);
if ($roles->contains('name', 'receptionist') || $roles->count() !== count($before['roles']) - 1) {
    throw new RuntimeException('Retired role removal was not verified.');
}
$cashier = $roles->firstWhere('name', 'cashier');
$legacyIds = collect($before['roles'])->whereIn('name', ['cashier', 'receptionist'])->pluck('id')->all();
$expectedPermissions = collect($before['role_permissions'])->whereIn('role_id', $legacyIds)->pluck('permission_id')->unique()->sort()->values()->all();
$actualPermissions = DB::table('permission_role')->where('role_id', $cashier->id)->pluck('permission_id')->sort()->values()->all();
$expectedUsers = collect($before['assignments'])->whereIn('role_id', $legacyIds)->pluck('user_id')->unique()->sort()->values()->all();
$actualUsers = DB::table('role_user')->where('role_id', $cashier->id)->pluck('user_id')->sort()->values()->all();
if ($expectedPermissions !== $actualPermissions || $expectedUsers !== $actualUsers) {
    throw new RuntimeException('Exact permission or account union does not match.');
}
if (! DB::table('audit_logs')->where('action', 'roles.receptionist_merged_into_cashier')->where('entity_id', $cashier->id)->exists()) {
    throw new RuntimeException('Merge audit evidence is missing.');
}
file_put_contents(storage_path('logs/cashier-role-consolidation-after.json'), json_encode($current, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'PASS: '.count($actualPermissions).' combined permissions; '.count($actualUsers).' cashier accounts; five roles remain; protected records and credentials unchanged; audit retained.'.PHP_EOL;
