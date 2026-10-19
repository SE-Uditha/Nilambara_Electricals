<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_inspections', function (Blueprint $table): void {
            $table->text('parts_used')->nullable();
            $table->decimal('parts_quantity', 12, 3)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_inspections', function (Blueprint $table): void {
            $table->dropColumn(['parts_used', 'parts_quantity']);
        });
    }
};
