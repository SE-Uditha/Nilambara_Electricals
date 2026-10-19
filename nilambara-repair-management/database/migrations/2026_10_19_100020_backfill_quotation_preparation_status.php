<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('quotations')
            ->where('status', 'finalized')
            ->where('preparation_status', 'draft')
            ->update([
                'preparation_status' => 'issued',
                'issued_at' => DB::raw('COALESCE(issued_at, updated_at, created_at, CURRENT_TIMESTAMP)'),
            ]);

        DB::table('quotations')
            ->where('status', 'cancelled')
            ->where('preparation_status', 'draft')
            ->update([
                'preparation_status' => 'cancelled',
            ]);
    }

    public function down(): void
    {
        // The normalization is retained when only this data migration is rolled back.
    }
};
