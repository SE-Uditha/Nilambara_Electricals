<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorized_approval_requests', function (Blueprint $table): void {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->string('source_key', 100)->unique();
            $table->string('kind', 32);
            $table->string('status', 16)->default('pending');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->restrictOnDelete();
            $table->foreignId('pos_sale_id')->nullable()->constrained('pos_sales')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->foreignId('spare_part_id')->nullable()->constrained('spare_parts')->restrictOnDelete();
            $table->foreignId('pos_sale_item_id')->nullable()->constrained('pos_sale_items')->restrictOnDelete();
            $table->decimal('amount', 19, 2)->nullable();
            $table->text('reason');
            $table->json('payload');
            $table->string('payload_hash', 64);
            $table->text('decision_comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'kind', 'created_at'], 'authorized_approvals_queue');
            $table->index(['requested_by_user_id', 'status'], 'authorized_approvals_requester');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorized_approval_requests');
    }
};
