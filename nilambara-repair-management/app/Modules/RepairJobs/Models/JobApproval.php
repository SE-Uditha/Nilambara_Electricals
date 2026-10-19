<?php

namespace App\Modules\RepairJobs\Models;

use App\Enums\ApprovalStatus;
use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApproval extends BusinessModel
{
    protected $table = 'job_approvals';

    protected $fillable = [
        'repair_job_id',
        'decision',
        'decided_at',
        'approved_by_name',
        'recorded_by_user_id',
        'notes',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'decision' => ApprovalStatus::class,
            'decided_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
