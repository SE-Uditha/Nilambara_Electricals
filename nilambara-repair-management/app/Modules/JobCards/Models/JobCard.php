<?php

namespace App\Modules\JobCards\Models;

use App\Models\BusinessModel;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\PrintTemplates\Models\PrintTemplate;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobCard extends BusinessModel
{
    protected $table = 'job_cards';

    protected $fillable = [
        'repair_job_id',
        'print_template_id',
        'paper_size',
        'notes',
    ];

    protected array $immutableFields = ['id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function printTemplate(): BelongsTo
    {
        return $this->belongsTo(PrintTemplate::class, 'print_template_id');
    }

    public function documentPrintHistories(): HasMany
    {
        return $this->hasMany(DocumentPrintHistory::class, 'job_card_id');
    }
}
