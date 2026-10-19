<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE pos_sales ADD COLUMN payment_method VARCHAR(64) NULL');
            DB::statement("ALTER TABLE pos_sales ADD COLUMN payment_status VARCHAR(64) NOT NULL DEFAULT 'pending'");
            DB::statement('ALTER TABLE pos_sales ADD COLUMN payment_reference VARCHAR(100) NULL');
            DB::statement('CREATE UNIQUE INDEX uq_step10_pos_payment_reference ON pos_sales(payment_method, payment_reference)');
            DB::statement('ALTER TABLE pos_sales ADD COLUMN cheque_date DATE NULL');
            DB::statement('ALTER TABLE pos_sales ADD COLUMN bank_name VARCHAR(150) NULL');
            DB::statement('ALTER TABLE pos_sales ADD COLUMN amount_tendered DECIMAL(19,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE pos_sales ADD COLUMN change_due DECIMAL(19,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE pos_sales ADD COLUMN idempotency_key VARCHAR(100) NULL');
            DB::statement('ALTER TABLE pos_sales ADD COLUMN idempotency_hash VARCHAR(64) NULL');
            DB::statement('ALTER TABLE pos_sales ADD COLUMN payment_verified_by_user_id INTEGER NULL REFERENCES users(id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE pos_sales ADD COLUMN payment_verified_at DATETIME NULL');
            DB::statement('CREATE UNIQUE INDEX uq_step10_pos_sale_idempotency ON pos_sales(idempotency_key)');
            DB::statement('ALTER TABLE stock_returns ADD COLUMN idempotency_key VARCHAR(100) NULL');
            DB::statement('ALTER TABLE stock_returns ADD COLUMN idempotency_hash VARCHAR(64) NULL');
            DB::statement('ALTER TABLE stock_returns ADD COLUMN refund_amount DECIMAL(19,2) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE stock_returns ADD COLUMN refund_payment_reversal_id INTEGER NULL REFERENCES payment_reversals(id) ON DELETE RESTRICT');
            DB::statement('CREATE UNIQUE INDEX uq_step10_pos_return_idempotency ON stock_returns(idempotency_key)');
            DB::statement('ALTER TABLE stock_return_items ADD COLUMN pos_sale_item_id INTEGER NULL REFERENCES pos_sale_items(id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE stock_return_items ADD COLUMN refund_amount DECIMAL(19,2) NOT NULL DEFAULT 0');
        } else {
            Schema::table('pos_sales', function (Blueprint $table): void {
                $table->string('payment_method', 64)->nullable();
                $table->string('payment_status', 64)->default('pending');
                $table->string('payment_reference', 100)->nullable();
                $table->date('cheque_date')->nullable();
                $table->string('bank_name', 150)->nullable();
                $table->decimal('amount_tendered', 19, 2)->default(0);
                $table->decimal('change_due', 19, 2)->default(0);
                $table->string('idempotency_key', 100)->nullable();
                $table->string('idempotency_hash', 64)->nullable();
                $table->foreignId('payment_verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('payment_verified_at')->nullable();
                $table->unique('idempotency_key', 'uq_step10_pos_sale_idempotency');
                $table->unique(['payment_method', 'payment_reference'], 'uq_step10_pos_payment_reference');
            });

            Schema::table('stock_returns', function (Blueprint $table): void {
                $table->string('idempotency_key', 100)->nullable();
                $table->string('idempotency_hash', 64)->nullable();
                $table->decimal('refund_amount', 19, 2)->default(0);
                $table->foreignId('refund_payment_reversal_id')->nullable()
                    ->constrained('payment_reversals')->restrictOnDelete();
                $table->unique('idempotency_key', 'uq_step10_pos_return_idempotency');
            });

            Schema::table('stock_return_items', function (Blueprint $table): void {
                $table->foreignId('pos_sale_item_id')->nullable()
                    ->constrained('pos_sale_items')->restrictOnDelete();
                $table->decimal('refund_amount', 19, 2)->default(0);
            });
        }

        DB::table('document_sequences')->insertOrIgnore([
            'scope' => 'pos_sale',
            'prefix' => 'POS-',
            'padding' => 5,
            'next_value' => 1,
            'description' => 'Point of sale transaction number.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('document_sequences')->insertOrIgnore([
            'scope' => 'pos_return',
            'prefix' => 'RET-POS-',
            'padding' => 5,
            'next_value' => 1,
            'description' => 'Point of sale return number.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX uq_step10_pos_sale_idempotency');
            DB::statement('DROP INDEX uq_step10_pos_payment_reference');
            DB::statement('DROP INDEX uq_step10_pos_return_idempotency');
            DB::statement('ALTER TABLE stock_return_items DROP COLUMN refund_amount');
            DB::statement('ALTER TABLE stock_return_items DROP COLUMN pos_sale_item_id');
            DB::statement('ALTER TABLE stock_returns DROP COLUMN refund_payment_reversal_id');
            DB::statement('ALTER TABLE stock_returns DROP COLUMN refund_amount');
            DB::statement('ALTER TABLE stock_returns DROP COLUMN idempotency_key');
            DB::statement('ALTER TABLE stock_returns DROP COLUMN idempotency_hash');
            DB::statement('ALTER TABLE pos_sales DROP COLUMN payment_verified_at');
            DB::statement('ALTER TABLE pos_sales DROP COLUMN payment_verified_by_user_id');
            DB::statement('ALTER TABLE pos_sales DROP COLUMN idempotency_key');
            DB::statement('ALTER TABLE pos_sales DROP COLUMN idempotency_hash');
            DB::statement('ALTER TABLE pos_sales DROP COLUMN change_due');
            DB::statement('ALTER TABLE pos_sales DROP COLUMN amount_tendered');
            DB::statement('ALTER TABLE pos_sales DROP COLUMN bank_name');
            DB::statement('ALTER TABLE pos_sales DROP COLUMN cheque_date');
            DB::statement('ALTER TABLE pos_sales DROP COLUMN payment_reference');
            DB::statement('ALTER TABLE pos_sales DROP COLUMN payment_status');
            DB::statement('ALTER TABLE pos_sales DROP COLUMN payment_method');
        } else {
            Schema::table('stock_return_items', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('pos_sale_item_id');
                $table->dropColumn('refund_amount');
            });
            Schema::table('stock_returns', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('refund_payment_reversal_id');
                $table->dropUnique('uq_step10_pos_return_idempotency');
                $table->dropColumn(['idempotency_key', 'idempotency_hash', 'refund_amount']);
            });
            Schema::table('pos_sales', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('payment_verified_by_user_id');
                $table->dropUnique('uq_step10_pos_sale_idempotency');
                $table->dropUnique('uq_step10_pos_payment_reference');
                $table->dropColumn([
                    'payment_method',
                    'payment_status',
                    'payment_reference',
                    'cheque_date',
                    'bank_name',
                    'amount_tendered',
                    'change_due',
                    'idempotency_key',
                    'idempotency_hash',
                    'payment_verified_at',
                ]);
            });
        }

        DB::table('document_sequences')->whereIn('scope', ['pos_sale', 'pos_return'])->delete();
    }
};
