<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repair_jobs', function (Blueprint $table): void {
            $table->text('private_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('repair_jobs', function (Blueprint $table): void {
            $table->dropColumn('private_notes');
        });
    }
};
