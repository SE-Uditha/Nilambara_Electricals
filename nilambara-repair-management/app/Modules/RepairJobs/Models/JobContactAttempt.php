<?php

namespace App\Modules\RepairJobs\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobContactAttempt extends Model
{
    protected $fillable = ['repair_job_id', 'attempt_number', 'contacted_on', 'notes', 'recorded_by_user_id'];

    protected function casts(): array
    {
        return ['contacted_on' => 'date'];
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
