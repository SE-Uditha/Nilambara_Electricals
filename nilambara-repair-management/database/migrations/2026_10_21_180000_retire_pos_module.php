<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        // The inspected installation has no POS transactions. Refuse to discard
        // populated POS data on another installation; archive/reconcile it first.
        foreach (['pos_sales', 'pos_sale_items'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('POS retirement requires archived/reconciled POS transactions before migration.');
            }
        }
        $definitions = $this->definitions();
        foreach ($definitions as $table => $definition) {
            foreach ($definition['removed_columns'] ?? [] as $column) {
                if ((str_starts_with($column, 'pos_') || in_array($column, ['refund_payment_reversal_id', 'consumed_at'], true)) && DB::table($table)->whereNotNull($column)->exists()) {
                    throw new RuntimeException('POS references remain in '.$table.'. Archive/reconcile them before retirement.');
                }
            }
        }
        foreach (['stock_returns', 'stock_return_items'] as $table) {
            if (DB::table($table)->where('refund_amount', '<>', 0)->exists()) {
                throw new RuntimeException('Nonzero return refund amounts must be reconciled before POS retirement.');
            }
        }
        foreach (['payments' => ['payment_type', 'pos'], 'payment_allocations' => ['target_type', 'pos_sale'], 'customer_account_entries' => ['entry_type', 'pos_sale'], 'stock_returns' => ['return_type', 'pos_return'], 'stock_movements' => ['movement_type', 'pos_sale']] as $table => [$column, $value]) {
            if (DB::table($table)->where($column, $value)->exists()) {
                throw new RuntimeException('POS accounting/stock records remain in '.$table.'.');
            }
        }
        if (DB::table('authorized_approval_requests')->whereIn('kind', ['pos_payment', 'pos_return', 'pos_discount'])->exists()) {
            throw new RuntimeException('Archive POS approval history before retirement.');
        }
        $posTemplates = DB::table('print_templates')->where('document_type', 'pos_receipt')->pluck('id');
        if (DB::table('document_print_histories')->whereIn('print_template_id', $posTemplates)->exists()) {
            throw new RuntimeException('Archive POS print history before retirement.');
        }

        $this->changeSchema($definitions, false);
        $permissionTables = config('permission.table_names');
        $ids = DB::table($permissionTables['permissions'])->where('name', 'like', 'pos.%')->pluck('id');
        DB::transaction(function () use ($permissionTables, $ids, $posTemplates): void {
            DB::table($permissionTables['role_has_permissions'])->whereIn('permission_id', $ids)->delete();
            DB::table($permissionTables['model_has_permissions'])->whereIn('permission_id', $ids)->delete();
            DB::table($permissionTables['permissions'])->whereIn('id', $ids)->delete();
            DB::table('print_templates')->whereIn('id', $posTemplates)->delete();
            DB::table('document_sequences')->whereIn('scope', ['pos_sale', 'pos_return'])->delete();
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Schema-only rollback; retired grants/templates are recovered from the
        // protected pre-retirement snapshot when a full data recovery is needed.
        $this->changeSchema($this->definitions(), true);
    }

    private function definitions(): array
    {
        $driver = DB::getDriverName() === 'sqlite' ? 'sqlite' : 'mysql';

        return json_decode(file_get_contents(database_path('schema/pos-retirement/'.$driver.'.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function changeSchema(array $definitions, bool $restore): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::disableForeignKeyConstraints();
            try {
                DB::transaction(function () use ($definitions, $restore): void {
                    if ($restore) {
                        foreach (['pos_sales', 'pos_sale_items'] as $table) {
                            DB::statement($definitions[$table]['sql']);
                            foreach ($definitions[$table]['indexes'] as $sql) {
                                DB::statement($sql);
                            }
                        }
                    }
                    foreach ($definitions as $table => $definition) {
                        if (! isset($definition['retired_sql'])) {
                            continue;
                        }
                        $sql = $definition[$restore ? 'sql' : 'retired_sql'];
                        $temporary = '__pos_retirement_'.$table;
                        $sql = preg_replace('/^(CREATE TABLE\s+)[`"]?'.preg_quote($table, '/').'[`"]?/i', '$1`'.$temporary.'`', $sql, 1);
                        DB::statement($sql);
                        $writable = collect(DB::select('PRAGMA table_xinfo("'.$temporary.'")'))->where('hidden', 0)->pluck('name')->all();
                        $columns = array_intersect(Schema::getColumnListing($table), $writable);
                        $quoted = implode(',', array_map(fn ($column) => '"'.$column.'"', $columns));
                        DB::statement('INSERT INTO "'.$temporary.'" ('.$quoted.') SELECT '.$quoted.' FROM "'.$table.'"');
                        Schema::drop($table);
                        DB::statement('ALTER TABLE "'.$temporary.'" RENAME TO "'.$table.'"');
                        foreach ($definition[$restore ? 'indexes' : 'retired_indexes'] as $index) {
                            DB::statement($index);
                        }
                    }
                    if (! $restore) {
                        Schema::drop('pos_sale_items');
                        Schema::drop('pos_sales');
                    }
                    if (DB::select('PRAGMA foreign_key_check') !== []) {
                        throw new RuntimeException('Foreign-key validation failed during POS schema retirement.');
                    }
                });
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            return;
        }
        if ($restore) {
            foreach (['pos_sales', 'pos_sale_items'] as $table) {
                DB::statement($definitions[$table]['sql']);
            }
        }
        $maria = str_contains(strtolower(DB::selectOne('SELECT VERSION() AS version')->version), 'mariadb');
        foreach ($definitions as $table => $definition) {
            if (! isset($definition['changes'])) {
                continue;
            }
            $drop = [];
            $add = [];
            foreach ($definition['changes'] as $change) {
                if ($restore) {
                    if (isset($change['new'])) {
                        $drop[] = 'DROP '.($maria ? 'CONSTRAINT' : 'CHECK').' `'.$change['name'].'`';
                    }
                    $add[] = 'ADD '.$change['old'];
                } else {
                    $drop[] = match ($change['type']) {
                        'column' => 'DROP COLUMN `'.$change['name'].'`',
                        'foreign' => 'DROP FOREIGN KEY `'.$change['name'].'`',
                        'index' => 'DROP INDEX `'.$change['name'].'`',
                        'check' => 'DROP '.($maria ? 'CONSTRAINT' : 'CHECK').' `'.$change['name'].'`',
                    };
                    if (isset($change['new'])) {
                        $add[] = 'ADD '.$change['new'];
                    }
                }
            }
            if ($drop !== [] || $add !== []) {
                DB::statement('ALTER TABLE `'.$table.'` '.implode(', ', [...$drop, ...$add]));
            }
        }
        if (! $restore) {
            Schema::drop('pos_sale_items');
            Schema::drop('pos_sales');
        }
    }
};
