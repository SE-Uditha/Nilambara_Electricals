<?php

namespace App\Modules\Employees\Models;

use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Modules\Purchases\Models\GoodsReceivedNote;
use App\Modules\RepairJobs\Models\JobInspection;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends BusinessModel
{
    protected $table = 'employees';

    protected $fillable = [
        'employee_code',
        'name',
        'address',
        'mobile_number',
        'job_title',
        'department',
        'date_joined',
        'is_active',
        'user_id',
    ];

    protected array $immutableFields = ['id', 'employee_code'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'date_joined' => 'date',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function receivedByEmployeeRepairJobs(): HasMany
    {
        return $this->hasMany(RepairJob::class, 'received_by_employee_id');
    }

    public function technicianRepairJobs(): HasMany
    {
        return $this->hasMany(RepairJob::class, 'technician_id');
    }

    public function inspectedByEmployeeJobInspections(): HasMany
    {
        return $this->hasMany(JobInspection::class, 'inspected_by_employee_id');
    }

    public function repairedByEmployeeJobInspections(): HasMany
    {
        return $this->hasMany(JobInspection::class, 'repaired_by_employee_id');
    }

    public function goodsReceivedNotes(): HasMany
    {
        return $this->hasMany(GoodsReceivedNote::class, 'received_by_employee_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'cashier_employee_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'received_by_employee_id');
    }
}
