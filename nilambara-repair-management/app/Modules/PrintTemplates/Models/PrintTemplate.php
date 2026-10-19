<?php

namespace App\Modules\PrintTemplates\Models;

use App\Models\BusinessModel;
use App\Modules\JobCards\Models\JobCard;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrintTemplate extends BusinessModel
{
    protected $table = 'print_templates';

    protected $fillable = [
        'code',
        'version',
        'document_type',
        'name',
        'business_name',
        'business_address',
        'telephone_number',
        'logo_path',
        'document_title',
        'paper_size',
        'orientation',
        'thermal_width_mm',
        'font_size_pt',
        'margin_top_mm',
        'margin_right_mm',
        'margin_bottom_mm',
        'margin_left_mm',
        'table_widths',
        'signature_areas',
        'footer',
        'duplicate_copy_label',
        'is_active',
    ];

    protected array $immutableFields = ['id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'thermal_width_mm' => 'decimal:2',
            'font_size_pt' => 'decimal:2',
            'margin_top_mm' => 'decimal:2',
            'margin_right_mm' => 'decimal:2',
            'margin_bottom_mm' => 'decimal:2',
            'margin_left_mm' => 'decimal:2',
            'table_widths' => 'array',
            'signature_areas' => 'array',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function jobCards(): HasMany
    {
        return $this->hasMany(JobCard::class, 'print_template_id');
    }

    public function documentPrintHistories(): HasMany
    {
        return $this->hasMany(DocumentPrintHistory::class, 'print_template_id');
    }
}
