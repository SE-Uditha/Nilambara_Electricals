<?php

namespace App\Modules\RepairJobs\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairJobCharge extends Model
{
    protected $fillable = ['repair_job_id', 'line_number', 'description', 'quantity', 'unit_price', 'line_total', 'removed_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:4', 'line_total' => 'decimal:2', 'removed_at' => 'datetime'];
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class);
    }
}
