<?php

namespace Database\Seeders;

use App\Modules\PrintTemplates\Models\PrintTemplate;
use Illuminate\Database\Seeder;

class Step11TemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['repair_receipt' => ['Repair Receipt', 'A5'], 'job_card' => ['Repair Job Card', 'A5'],
            'quotation' => ['Quotation', 'A4'], 'invoice' => ['Repair Invoice', 'A5'],
            'customer_statement' => ['Customer Statement', 'A4'], 'payment_receipt' => ['Payment Receipt', 'A5'],
            'purchase' => ['Purchase Document', 'A4']] as $type => [$name,$paper]) {
            if (PrintTemplate::where('document_type', $type)->exists()) {
                continue;
            }
            PrintTemplate::create(['code' => 'step11-'.$type, 'version' => 1, 'document_type' => $type, 'name' => $name,
                'document_title' => $name, 'paper_size' => $paper, 'orientation' => 'portrait', 'font_size_pt' => 9,
                'thermal_width_mm' => null,
                'margin_top_mm' => 5, 'margin_right_mm' => 5, 'margin_bottom_mm' => 5, 'margin_left_mm' => 5,
                'duplicate_copy_label' => 'Duplicate Copy', 'is_active' => true]);
        }
    }
}
