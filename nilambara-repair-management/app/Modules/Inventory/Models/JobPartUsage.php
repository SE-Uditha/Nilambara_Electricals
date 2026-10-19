<?php

namespace App\Modules\Inventory\Models;

use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JobPartUsage extends BusinessModel
{
    protected $table = 'job_part_usages';

    protected $fillable = [
        'repair_job_id',
        'spare_part_id',
        'location_id',
        'quantity',
        'unit_cost',
        'unit_price',
        'status',
        'requested_by_user_id',
        'approved_at',
        'approved_by_user_id',
        'idempotency_key',
        'notes',
    ];

    protected array $immutableFields = ['id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'approved_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class, 'spare_part_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function stockMovements(): HasOne
    {
        return $this->hasOne(StockMovement::class, 'job_part_usage_id');
    }
}
