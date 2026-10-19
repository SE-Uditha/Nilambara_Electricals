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
            DB::statement('ALTER TABLE invoices ADD COLUMN payment_method VARCHAR(64) NULL');
        } else {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->string('payment_method', 64)->nullable()->after('payment_terms');
            });
        }

        Schema::create('invoice_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('event_type', 50);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->dateTime('event_at');
            $table->timestamps();
            $table->index(['invoice_id', 'event_at'], 'ix_invoice_history_event');
            $table->index('actor_user_id', 'ix_invoice_history_actor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_history');

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE invoices DROP COLUMN payment_method');
        } else {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropColumn('payment_method');
            });
        }
    }
};
