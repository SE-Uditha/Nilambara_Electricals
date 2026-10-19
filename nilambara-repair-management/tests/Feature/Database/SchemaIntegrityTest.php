<?php

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaIntegrityTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    public function test_foreign_key_enforcement_is_enabled_on_the_test_connection(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->assertSame(1, (int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys);

            return;
        }

        $this->assertSame(1, (int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled);
    }

    public function test_transaction_records_are_not_cascade_deleted(): void
    {
        foreach ([
            'invoices', 'invoice_jobs', 'invoice_items', 'payments',
            'payment_allocations', 'payment_reversals', 'stock_movements',
            'purchases', 'purchase_items', 'goods_received_notes',
            'goods_received_note_items', 'pos_sales', 'pos_sale_items',
            'job_status_histories',
        ] as $table) {
            $foreignKeys = Schema::getForeignKeys($table);

            $this->assertNotEmpty($foreignKeys, "No foreign keys found on {$table}.");

            foreach ($foreignKeys as $foreignKey) {
                $this->assertNotSame('cascade', strtolower($foreignKey['on_delete']), $table);
            }
        }
    }

    public function test_no_schema_column_uses_binary_floating_point_storage(): void
    {
        foreach (Schema::getTables() as $table) {
            foreach (Schema::getColumns($table['name']) as $column) {
                $this->assertNotContains(strtolower($column['type_name']), ['float', 'double', 'real'], $table['name'].'.'.$column['name']);
            }
        }
    }

    public function test_mysql_tables_use_innodb_and_utf8mb4(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('InnoDB and UTF8MB4 metadata require a MySQL-compatible test server.');
        }

        $tables = DB::select(
            'SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_COLLATION AS collation_name
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ?',
            ['BASE TABLE'],
        );

        $this->assertNotEmpty($tables);

        foreach ($tables as $table) {
            $this->assertSame('innodb', strtolower($table->engine), $table->name);
            $this->assertStringStartsWith('utf8mb4_', $table->collation_name, $table->name);
        }
    }

    public function test_mysql_money_fields_are_fixed_precision_decimals(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('SQLite affinity cannot verify MySQL DECIMAL storage precision.');
        }

        foreach ([
            ['customers', 'opening_balance', 2],
            ['suppliers', 'opening_balance', 2],
            ['invoices', 'subtotal', 2],
            ['invoices', 'total', 2],
            ['payments', 'amount', 2],
            ['spare_parts', 'selling_price', 4],
        ] as [$table, $column, $scale]) {
            $metadata = DB::selectOne(
                'SELECT DATA_TYPE AS data_type, NUMERIC_PRECISION AS precision_digits, NUMERIC_SCALE AS scale_digits
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column],
            );

            $this->assertNotNull($metadata, $table.'.'.$column);
            $this->assertSame('decimal', $metadata->data_type, $table.'.'.$column);
            $this->assertSame($scale, (int) $metadata->scale_digits, $table.'.'.$column);
            $this->assertGreaterThanOrEqual(12, (int) $metadata->precision_digits, $table.'.'.$column);
        }
    }

    public function test_mysql_round_trips_amounts_above_binary_floating_point_integer_precision(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('This assertion requires native fixed precision DECIMAL storage.');
        }

        $amount = '9007199254740993.01';
        $customer = $this->customer(['opening_balance' => $amount]);

        $this->assertSame($amount, $customer->fresh()->opening_balance);
    }
}
