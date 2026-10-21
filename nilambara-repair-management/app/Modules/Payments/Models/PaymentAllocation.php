<?php

namespace App\Modules\Payments\Models;

use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentAllocation extends BusinessModel
{
    protected $table = 'payment_allocations';

    protected $fillable = [
        'payment_id',
        'customer_id',
        'target_type',
        'repair_job_id',
        'invoice_id',
        'amount',
        'allocated_at',
        'allocated_by_user_id',
        'idempotency_key',
        'customer_scope_id',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'allocated_at' => 'datetime',
            'customer_scope_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function allocatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by_user_id');
    }

    public function paymentAllocationReleases(): HasMany
    {
        return $this->hasMany(PaymentAllocationRelease::class, 'payment_allocation_id');
    }

    public function releases(): HasMany
    {
        return $this->hasMany(PaymentAllocationRelease::class, 'payment_allocation_id');
    }
}
