<?php

namespace App\Modules\JobTracking\Models;

use App\Enums\JobStatus;
use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobStatusHistory extends BusinessModel
{
    protected $table = 'job_status_histories';

    protected $fillable = [
        'repair_job_id',
        'previous_status',
        'new_status',
        'changed_by_user_id',
        'changed_at',
        'notes',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'previous_status' => JobStatus::class,
            'new_status' => JobStatus::class,
            'changed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
