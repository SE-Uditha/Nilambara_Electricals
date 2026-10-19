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
            DB::statement('ALTER TABLE quotations ADD COLUMN parts_total DECIMAL(19, 2) NOT NULL DEFAULT 0');
            DB::statement("ALTER TABLE quotations ADD COLUMN preparation_status VARCHAR(32) NOT NULL DEFAULT 'draft'");
            DB::statement('ALTER TABLE quotations ADD COLUMN issued_at DATETIME NULL');
            DB::statement('ALTER TABLE quotations ADD COLUMN updated_by_user_id INTEGER NULL REFERENCES users(id) ON DELETE RESTRICT');
            DB::statement('CREATE INDEX ix_quotations_prep_approval ON quotations (preparation_status, approval_status)');
            DB::statement('ALTER TABLE quotation_approvals ADD COLUMN approval_method VARCHAR(32) NULL');
            DB::statement('ALTER TABLE quotation_items ADD COLUMN display_order INTEGER NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE quotation_items ADD COLUMN removed_at DATETIME NULL');
        } else {
            Schema::table('quotations', function (Blueprint $table): void {
                $table->decimal('parts_total', 19, 2)->default(0)->after('labour_charge');
                $table->string('preparation_status', 32)->default('draft')->after('status');
                $table->dateTime('issued_at')->nullable()->after('preparation_status');
                $table->unsignedBigInteger('updated_by_user_id')->nullable()->after('created_by_user_id');
                $table->foreign('updated_by_user_id')->references('id')->on('users')->restrictOnDelete();
                $table->index(['preparation_status', 'approval_status'], 'ix_quotations_prep_approval');
            });

            Schema::table('quotation_approvals', function (Blueprint $table): void {
                $table->string('approval_method', 32)->nullable()->after('customer_contact_name');
            });

            Schema::table('quotation_items', function (Blueprint $table): void {
                $table->unsignedInteger('display_order')->default(0)->after('line_number');
                $table->dateTime('removed_at')->nullable()->after('line_total');
            });
        }

        DB::table('quotations')
            ->where('status', 'finalized')
            ->update([
                'preparation_status' => 'issued',
                'issued_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)'),
            ]);
        DB::table('quotations')->where('status', 'cancelled')->update([
            'preparation_status' => 'cancelled',
        ]);

        Schema::create('quotation_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 50);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->dateTime('event_at');
            $table->timestamps();
            $table->index(['quotation_id', 'event_at'], 'ix_quotation_history_event');
            $table->index('actor_user_id', 'ix_quotation_history_actor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_history');

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX ix_quotations_prep_approval');
            DB::statement('ALTER TABLE quotation_approvals DROP COLUMN approval_method');
            DB::statement('ALTER TABLE quotation_items DROP COLUMN removed_at');
            DB::statement('ALTER TABLE quotation_items DROP COLUMN display_order');
            DB::statement('ALTER TABLE quotations DROP COLUMN updated_by_user_id');
            DB::statement('ALTER TABLE quotations DROP COLUMN issued_at');
            DB::statement('ALTER TABLE quotations DROP COLUMN preparation_status');
            DB::statement('ALTER TABLE quotations DROP COLUMN parts_total');
        } else {
            Schema::table('quotation_approvals', function (Blueprint $table): void {
                $table->dropColumn('approval_method');
            });

            Schema::table('quotation_items', function (Blueprint $table): void {
                $table->dropColumn(['display_order', 'removed_at']);
            });

            Schema::table('quotations', function (Blueprint $table): void {
                $table->dropForeign(['updated_by_user_id']);
                $table->dropIndex('ix_quotations_prep_approval');
                $table->dropColumn(['parts_total', 'preparation_status', 'issued_at', 'updated_by_user_id']);
            });
        }
    }
};
