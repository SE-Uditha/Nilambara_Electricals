<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_jobs', function (Blueprint $table): void {
            $table->index('received_at');
            $table->index('completed_at');
            $table->index('delivered_at');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['status', 'due_date']);
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->index(['status', 'due_date']);
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['occurred_at']);
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropIndex(['status', 'due_date']);
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['status', 'due_date']);
        });

        Schema::table('repair_jobs', function (Blueprint $table): void {
            $table->dropIndex(['delivered_at']);
            $table->dropIndex(['completed_at']);
            $table->dropIndex(['received_at']);
        });
    }
};
