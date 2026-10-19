<?php

namespace App\Modules\RepairReceipts\Models;

use App\Models\BusinessModel;
use App\Modules\Customers\Models\Customer;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepairReceipt extends BusinessModel
{
    protected $table = 'repair_receipts';

    protected $fillable = [
        'repair_job_id',
        'customer_id',
        'receipt_number',
        'issued_at',
        'document_snapshot',
        'special_notes',
    ];

    protected array $immutableFields = ['id', 'repair_job_id', 'customer_id', 'receipt_number', 'document_snapshot'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'document_snapshot' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function documentPrintHistories(): HasMany
    {
        return $this->hasMany(DocumentPrintHistory::class, 'repair_receipt_id');
    }
}
