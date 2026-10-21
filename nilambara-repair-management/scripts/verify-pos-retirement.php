<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only business-record verification and protected snapshot for POS retirement. */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true) || DB::getDatabaseName() !== 'nilambara_repair_management') {
        throw new RuntimeException('The configured local repair-management database is required.');
    }
    $mode = $argv[1] ?? '';
    $manifestPath = storage_path('logs/pos-retirement-before.json');
    $definitions = json_decode(file_get_contents(database_path('schema/pos-retirement/mysql.json')), true, flags: JSON_THROW_ON_ERROR);
    $tables = array_column(DB::select("SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME"), 'name');
    $permissionTables = config('permission.table_names');
    $permissionIds = DB::table($permissionTables['permissions'])->where('name', 'like', 'pos.%')->pluck('id')->all();
    $templateIds = DB::table('print_templates')->where('document_type', 'pos_receipt')->pluck('id')->all();
    $snapshot = ['database' => DB::getDatabaseName(), 'created_at' => now()->toIso8601String(), 'tables' => []];
    $state = [];
    foreach ($tables as $table) {
        $rows = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        if ($mode === 'before') {
            $ddl = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
            $snapshot['tables'][$table] = ['schema' => array_values($ddl)[1], 'rows' => $rows];
        }
        if (in_array($table, ['pos_sales', 'pos_sale_items', 'migrations', 'cache', 'cache_locks', 'sessions'], true)) {
            continue;
        }
        $canonical = [];
        foreach ($rows as $row) {
            if ($table === $permissionTables['permissions'] && in_array($row['id'], $permissionIds)) {
                continue;
            }
            if (in_array($table, [$permissionTables['role_has_permissions'], $permissionTables['model_has_permissions']], true) && in_array($row['permission_id'], $permissionIds)) {
                continue;
            }
            if ($table === 'print_templates' && in_array($row['id'], $templateIds)) {
                continue;
            }
            if ($table === 'document_sequences' && in_array($row['scope'], ['pos_sale', 'pos_return'], true)) {
                continue;
            }
            foreach ($definitions[$table]['removed_columns'] ?? [] as $column) {
                unset($row[$column]);
            }
            ksort($row);
            $canonical[] = json_encode($row, JSON_THROW_ON_ERROR);
        }
        sort($canonical);
        $state[$table] = ['rows' => count($canonical), 'sha256' => hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR))];
    }
    if ($mode === 'before') {
        if (! Schema::hasTable('pos_sales') || ! Schema::hasTable('pos_sale_items') || DB::table('pos_sales')->exists() || DB::table('pos_sale_items')->exists()) {
            throw new RuntimeException('Expected the inspected empty POS tables before migration.');
        }
        $backupName = 'pre-pos-removal-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json.enc';
        $encrypted = Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR));
        $backupPath = storage_path('app/private/'.$backupName);
        if (file_put_contents($backupPath, $encrypted, LOCK_EX) !== strlen($encrypted)) {
            throw new RuntimeException('Snapshot write failed.');
        }
        if (! hash_equals(hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), hash('sha256', Crypt::decryptString(file_get_contents($backupPath))))) {
            throw new RuntimeException('Snapshot verification failed.');
        }
        file_put_contents($manifestPath, json_encode(['backup' => $backupName, 'expected_business_records' => $state], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo 'Protected, verified snapshot: '.$backupName.PHP_EOL;
        echo 'Recorded fingerprints for '.count($state).' non-POS tables.'.PHP_EOL;
    } elseif ($mode === 'after') {
        $before = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        if ($state !== $before['expected_business_records']) {
            throw new RuntimeException('Non-POS records differ from the pre-retirement snapshot. Inspect before continuing.');
        }
        if (Schema::hasTable('pos_sales') || Schema::hasTable('pos_sale_items')) {
            throw new RuntimeException('POS tables still exist.');
        }
        foreach ($definitions as $table => $definition) {
            foreach ($definition['removed_columns'] ?? [] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    throw new RuntimeException('Retired POS column remains: '.$table.'.'.$column);
                }
            }
        }
        if ($permissionIds !== [] || $templateIds !== []) {
            throw new RuntimeException('POS permissions or templates remain.');
        }
        $references = DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IN ('pos_sales','pos_sale_items')")->total;
        if ((int) $references !== 0) {
            throw new RuntimeException('Foreign keys still reference retired POS tables.');
        }
        echo 'PASS: POS tables, shared POS columns, permission definitions and foreign keys are removed.'.PHP_EOL;
        echo 'PASS: non-POS business records, users, passwords and non-POS permission assignments are unchanged.'.PHP_EOL;
        echo 'Recovery snapshot: '.$before['backup'].PHP_EOL;
    } else {
        throw new RuntimeException('Use before or after.');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
