<?php

namespace App\Modules\Suppliers\Models;

use App\Enums\PaymentMethod;
use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierPayment extends BusinessModel
{
    protected $table = 'supplier_payments';

    protected $fillable = [
        'payment_number',
        'supplier_id',
        'method',
        'amount',
        'paid_at',
        'paid_by_user_id',
        'bank_reference',
        'cheque_number',
        'cheque_date',
        'notes',
        'idempotency_key',
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
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function supplierPaymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'supplier_payment_id');
    }

    public function supplierPaymentReversals(): HasMany
    {
        return $this->hasMany(SupplierPaymentReversal::class, 'supplier_payment_id');
    }

    public function supplierAllocationReleases(): HasMany
    {
        return $this->hasMany(SupplierAllocationRelease::class, 'supplier_payment_id');
    }

    public function supplierAccountEntries(): HasOne
    {
        return $this->hasOne(SupplierAccountEntry::class, 'supplier_payment_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'supplier_payment_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(SupplierPaymentReversal::class, 'supplier_payment_id');
    }
}
