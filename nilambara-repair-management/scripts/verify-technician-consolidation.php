<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'nilambara_repair_management' || ! in_array(config('database.connections.mysql.host'), ['localhost', '127.0.0.1'], true) || $app->isProduction()) {
    throw new RuntimeException('Only the configured local development database may be inspected.');
}
$manifest = storage_path('app/private/technician-consolidation-snapshot.txt');
$mode = $argv[1] ?? '';
if ($mode === 'before') {
    $snapshot = ['tables' => [], 'ddl' => []];
    foreach (DB::select('SHOW TABLES') as $table) {
        $name = array_values((array) $table)[0];
        if (! preg_match('/\A[a-zA-Z0-9_]+\z/', $name)) {
            throw new RuntimeException('Unexpected table name.');
        }
        $snapshot['tables'][$name] = DB::table($name)->get()->map(fn ($row) => (array) $row)->all();
        $snapshot['ddl'][$name] = (array) DB::selectOne('SHOW CREATE TABLE `'.$name.'`');
    }
    $path = storage_path('app/private/pre-technician-consolidation-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json.enc');
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
$allowed = ['roles', 'permission_role', 'role_user', 'users', 'sessions', 'audit_logs', 'migrations', 'cache', 'cache_locks'];
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
$roles = collect($before['roles'])->keyBy('id');
$oldIds = $roles->filter(fn ($r) => in_array($r['name'], ['technician']))->keys()->all();
$cashierId = $roles->firstWhere('name', 'cashier')['id'];
$cashierUsers = collect($before['role_user'])->where('role_id', $cashierId)->pluck('user_id')->all();
$protectedIds = $roles->filter(fn ($r) => in_array($r['name'], ['administrator']))->keys()->all();
$protectedUsers = collect($before['role_user'])->whereIn('role_id', $protectedIds)->pluck('user_id')->all();
$disabledIds = collect($before['role_user'])->whereIn('role_id', $oldIds)->pluck('user_id')->diff(array_merge($cashierUsers, $protectedUsers))->unique()->all();
foreach ($before['users'] as $row) {
    $now = (array) DB::table('users')->where('id', $row['id'])->first();
    if (in_array($row['id'], $disabledIds)) {
        if ((int) $now['is_active'] !== 0) {
            throw new RuntimeException('Extra account remains enabled.');
        }
        unset($row['is_active'], $row['updated_at'], $now['is_active'], $now['updated_at']);
    }
    if ($canonical([$row]) !== $canonical([$now])) {
        throw new RuntimeException('Unexpected account/credential change.');
    }
}
if (DB::table('users')->count() !== count($before['users'])) {
    throw new RuntimeException('Account count changed.');
}
$expected = collect($before['role_user'])->map(function ($row) use ($oldIds, $cashierId) {
    return ['role_id' => in_array($row['role_id'], $oldIds) ? $cashierId : $row['role_id'], 'user_id' => $row['user_id'], 'model_type' => $row['model_type']];
})->unique(fn ($row) => json_encode($row))->values()->all();
$now = DB::table('role_user')->get(['role_id', 'user_id', 'model_type'])->map(fn ($row) => (array) $row)->all();
if ($canonical($expected) !== $canonical($now)) {
    throw new RuntimeException('Role memberships do not match the intended merge.');
}
$expected = collect($before['permission_role'])->map(function ($row) use ($oldIds, $cashierId) {
    return ['role_id' => in_array($row['role_id'], $oldIds) ? $cashierId : $row['role_id'], 'permission_id' => $row['permission_id']];
})->unique(fn ($row) => json_encode($row))->values()->all();
$now = DB::table('permission_role')->get(['role_id', 'permission_id'])->map(fn ($row) => (array) $row)->all();
if ($canonical($expected) !== $canonical($now)) {
    throw new RuntimeException('Permission grants do not match the exact original union.');
}
if (DB::table('roles')->whereIn('name', ['technician'])->exists()) {
    throw new RuntimeException('Obsolete roles remain.');
}
echo 'PASS: exact permission and membership union; extra logins disabled; credentials and '.$checked.' other tables preserved.'.PHP_EOL;
echo 'Recovery snapshot: '.basename($path).PHP_EOL;
