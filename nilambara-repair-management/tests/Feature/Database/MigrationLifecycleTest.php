<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationLifecycleTest extends TestCase
{
    public function test_the_complete_schema_can_migrate_rollback_and_migrate_again_on_an_isolated_database(): void
    {
        $connection = 'migration_lifecycle';

        config([
            'database.connections.'.$connection => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        $schema = Schema::connection($connection);
        $expectedTables = [
            'users',
            'password_reset_tokens',
            'sessions',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'roles',
            'permissions',
            'role_user',
            'permission_role',
            'permission_user',
            'employees',
            'customers',
            'suppliers',
            'spare_part_categories',
            'spare_part_brands',
            'units_of_measure',
            'stock_locations',
            'spare_parts',
            'document_sequences',
            'print_templates',
            'system_settings',
            'backup_histories',
            'backup_restores',
            'audit_logs',
            'authorized_approval_requests',
            'repair_jobs',
            'repair_job_charges',
            'repair_receipts',
            'job_status_histories',
            'job_inspections',
            'job_cards',
            'job_approvals',
            'quotations',
            'quotation_items',
            'quotation_approvals',
            'quotation_history',
            'purchases',
            'purchase_items',
            'goods_received_notes',
            'goods_received_note_items',
            'invoices',
            'invoice_jobs',
            'invoice_items',
            'payments',
            'payment_allocations',
            'payment_reversals',
            'payment_allocation_releases',
            'supplier_payments',
            'supplier_payment_allocations',
            'supplier_payment_reversals',
            'supplier_allocation_releases',
            'customer_account_entries',
            'supplier_account_entries',
            'job_part_usages',
            'stock_adjustments',
            'stock_adjustment_items',
            'stock_counts',
            'stock_count_items',
            'stock_returns',
            'stock_return_items',
            'stock_movements',
            'expense_categories',
            'expenses',
            'document_print_histories',
        ];

        try {
            $this->assertSame('sqlite', DB::connection($connection)->getDriverName());
            $this->assertSame(':memory:', DB::connection($connection)->getDatabaseName());
            $this->assertSame([], $schema->getTables());

            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => $connection,
                '--force' => true,
            ]), Artisan::output());

            foreach ($expectedTables as $table) {
                $this->assertTrue($schema->hasTable($table), "Missing table: {$table}");
            }
            $this->assertTrue($schema->hasColumn('quotations', 'preparation_status'));
            $this->assertTrue($schema->hasColumn('quotations', 'issued_at'));
            $this->assertTrue($schema->hasColumn('quotation_items', 'display_order'));
            $this->assertTrue($schema->hasColumn('quotation_approvals', 'approval_method'));

            $this->assertSame(0, Artisan::call('migrate:rollback', [
                '--database' => $connection,
                '--force' => true,
            ]), Artisan::output());

            foreach ($expectedTables as $table) {
                $this->assertFalse($schema->hasTable($table), "Rollback retained table: {$table}");
            }

            $this->assertSame(0, Artisan::call('migrate', [
                '--database' => $connection,
                '--force' => true,
            ]), Artisan::output());

            foreach ($expectedTables as $table) {
                $this->assertTrue($schema->hasTable($table), "Remigration missed table: {$table}");
            }
        } finally {
            DB::purge($connection);
        }
    }
}
