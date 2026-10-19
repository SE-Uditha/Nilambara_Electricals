<?php

namespace App\Modules\RepairJobs\Models;

use App\Models\BusinessModel;
use App\Modules\Employees\Models\Employee;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobInspection extends BusinessModel
{
    protected $table = 'job_inspections';

    protected $fillable = [
        'repair_job_id',
        'inspected_at',
        'inspected_by_employee_id',
        'existing_fault',
        'inspection_result',
        'technical_information',
        'work_performed',
        'repaired_by_employee_id',
        'repair_completed_at',
        'testing_details',
        'special_notes',
    ];

    protected array $immutableFields = ['id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'inspected_at' => 'datetime',
            'repair_completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function inspectedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'inspected_by_employee_id');
    }

    public function repairedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'repaired_by_employee_id');
    }
}
