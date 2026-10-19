<?php

namespace App\Modules\Payments\Models;

use App\Enums\PaymentMethod;
use App\Models\BusinessModel;
use App\Modules\CreditManagement\Models\CustomerAccountEntry;
use App\Modules\Customers\Models\Customer;
use App\Modules\Employees\Models\Employee;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends BusinessModel
{
    protected $table = 'payments';

    protected $fillable = [
        'payment_number',
        'customer_id',
        'payment_type',
        'method',
        'amount',
        'paid_at',
        'received_by_employee_id',
        'bank_reference',
        'cheque_number',
        'cheque_date',
        'bank_name',
        'notes',
        'idempotency_key',
        'customer_scope_id',
    ];

    protected array $immutableFields = ['id', 'payment_number'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'cheque_date' => 'date',
            'customer_scope_id' => 'integer',
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

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    public function paymentReversals(): HasMany
    {
        return $this->hasMany(PaymentReversal::class, 'payment_id');
    }

    public function paymentAllocationReleases(): HasMany
    {
        return $this->hasMany(PaymentAllocationRelease::class, 'payment_id');
    }

    public function customerAccountEntries(): HasOne
    {
        return $this->hasOne(CustomerAccountEntry::class, 'payment_id');
    }

    public function documentPrintHistories(): HasMany
    {
        return $this->hasMany(DocumentPrintHistory::class, 'payment_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(PaymentReversal::class, 'payment_id');
    }
}
