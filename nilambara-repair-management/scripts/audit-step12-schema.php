<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Read-only metadata/count audit. No business row values or credentials are exported.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
});
$db = DB::connection();
if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'nilambara_repair_management') {
    throw new RuntimeException('Read-only audit requires the named local application MySQL database.');
}
$quote = static fn (string $name): string => '`'.str_replace('`', '``', $name).'`';
$tables = $db->select('SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_COLLATION AS collation_name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ?', ['BASE TABLE']);
$result = ['database' => $db->getDatabaseName(), 'captured_at' => date(DATE_ATOM), 'read_only' => true, 'tables' => [], 'foreign_keys' => []];
foreach ($tables as $table) {
    $name = $table->name;
    $indexes = $db->select('SHOW INDEX FROM '.$quote($name));
    $result['tables'][] = [
        'name' => $name, 'engine' => $table->engine, 'collation' => $table->collation_name,
        'rows' => (int) $db->selectOne('SELECT COUNT(*) AS n FROM '.$quote($name))->n,
        'indexes' => array_values(array_unique(array_map(static fn ($index) => $index->Key_name, $indexes))),
    ];
}
$keys = $db->select('SELECT k.CONSTRAINT_NAME AS name, k.TABLE_NAME AS child_table, k.COLUMN_NAME AS child_column, k.REFERENCED_TABLE_NAME AS parent_table, k.REFERENCED_COLUMN_NAME AS parent_column, r.DELETE_RULE AS delete_rule FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL');
foreach ($keys as $key) {
    $orphans = $db->selectOne('SELECT COUNT(*) AS n FROM '.$quote($key->child_table).' c LEFT JOIN '.$quote($key->parent_table).' p ON c.'.$quote($key->child_column).'=p.'.$quote($key->parent_column).' WHERE c.'.$quote($key->child_column).' IS NOT NULL AND p.'.$quote($key->parent_column).' IS NULL');
    $result['foreign_keys'][] = array_merge((array) $key, ['orphans' => (int) $orphans->n]);
}
$constraints = [];
foreach ($keys as $key) {
    $constraints[$key->child_table.'.'.$key->name][] = $key;
}
$result['foreign_key_constraints'] = [];
foreach ($constraints as $name => $columns) {
    $first = $columns[0];
    $join = implode(' AND ', array_map(static fn ($key) => 'c.'.$quote($key->child_column).'=p.'.$quote($key->parent_column), $columns));
    // SQL permits a composite FK with any null component. Check only complete tuples.
    $notNull = implode(' AND ', array_map(static fn ($key) => 'c.'.$quote($key->child_column).' IS NOT NULL', $columns));
    $orphans = $db->selectOne('SELECT COUNT(*) AS n FROM '.$quote($first->child_table).' c LEFT JOIN '.$quote($first->parent_table).' p ON '.$join.' WHERE '.$notNull.' AND p.'.$quote($first->parent_column).' IS NULL');
    $result['foreign_key_constraints'][] = ['name' => $name, 'columns' => count($columns), 'orphans' => (int) $orphans->n];
}
$result['floating_point_columns'] = $db->select("SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND DATA_TYPE IN ('float','double','real')");
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
