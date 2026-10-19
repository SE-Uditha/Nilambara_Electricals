<?php

namespace App\Modules\RepairJobs\Models;

use App\Enums\ApprovalStatus;
use App\Enums\JobStatus;
use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Employees\Models\Employee;
use App\Modules\Inventory\Models\JobPartUsage;
use App\Modules\Inventory\Models\StockReturn;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceJob;
use App\Modules\JobCards\Models\JobCard;
use App\Modules\JobTracking\Models\JobStatusHistory;
use App\Modules\Payments\Models\PaymentAllocation;
use App\Modules\Quotations\Models\Quotation;
use App\Modules\RepairReceipts\Models\RepairReceipt;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RepairJob extends BusinessModel
{
    protected $table = 'repair_jobs';

    protected $fillable = [
        'job_number',
        'automatic_number',
        'customer_id',
        'item_name',
        'brand',
        'model',
        'item_description',
        'reported_fault',
        'accessories_received',
        'physical_condition',
        'visible_damage',
        'job_type',
        'received_at',
        'received_by_employee_id',
        'technician_id',
        'repair_description',
        'labour_charge',
        'labour_cost',
        'parts_charge',
        'parts_cost',
        'total_job_amount',
        'completed_at',
        'delivered_at',
        'approval_status',
        'special_notes',
        'status',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
    ];

    protected array $immutableFields = ['id', 'job_number', 'automatic_number', 'customer_id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'labour_charge' => 'decimal:2',
            'labour_cost' => 'decimal:2',
            'parts_charge' => 'decimal:2',
            'parts_cost' => 'decimal:2',
            'total_job_amount' => 'decimal:2',
            'completed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'approval_status' => ApprovalStatus::class,
            'status' => JobStatus::class,
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function receivedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'received_by_employee_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'technician_id');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function repairReceipts(): HasOne
    {
        return $this->hasOne(RepairReceipt::class, 'repair_job_id');
    }

    public function jobStatusHistories(): HasMany
    {
        return $this->hasMany(JobStatusHistory::class, 'repair_job_id');
    }

    public function jobInspections(): HasMany
    {
        return $this->hasMany(JobInspection::class, 'repair_job_id');
    }

    public function jobCards(): HasOne
    {
        return $this->hasOne(JobCard::class, 'repair_job_id');
    }

    public function jobApprovals(): HasMany
    {
        return $this->hasMany(JobApproval::class, 'repair_job_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'repair_job_id');
    }

    public function invoiceJobs(): HasOne
    {
        return $this->hasOne(InvoiceJob::class, 'repair_job_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'repair_job_id');
    }

    public function jobPartUsages(): HasMany
    {
        return $this->hasMany(JobPartUsage::class, 'repair_job_id');
    }

    public function stockReturns(): HasMany
    {
        return $this->hasMany(StockReturn::class, 'repair_job_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(RepairReceipt::class, 'repair_job_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(JobStatusHistory::class, 'repair_job_id');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(JobInspection::class, 'repair_job_id');
    }

    public function partsUsages(): HasMany
    {
        return $this->hasMany(JobPartUsage::class, 'repair_job_id');
    }

    public function jobCard(): HasOne
    {
        return $this->hasOne(JobCard::class, 'repair_job_id');
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_jobs', 'repair_job_id', 'invoice_id')->withPivot(['id', 'customer_id', 'job_amount'])->withTimestamps();
    }
}
