<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('print_templates')
            ->where('document_type', 'job_card')
            ->where('is_active', true)
            ->update([
                'paper_size' => 'A5',
                'orientation' => 'portrait',
                'updated_at' => now(),
            ]);

        DB::table('job_cards')
            ->where('paper_size', 'A6')
            ->update([
                'paper_size' => 'A5',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('print_templates')
            ->where('document_type', 'job_card')
            ->where('is_active', true)
            ->update([
                'paper_size' => 'A6',
                'orientation' => 'portrait',
                'updated_at' => now(),
            ]);

        DB::table('job_cards')
            ->where('paper_size', 'A5')
            ->update([
                'paper_size' => 'A6',
                'updated_at' => now(),
            ]);
    }
};
