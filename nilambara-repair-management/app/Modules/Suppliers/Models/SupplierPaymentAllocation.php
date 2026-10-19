<?php

namespace App\Modules\Suppliers\Models;

use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\Purchases\Models\Purchase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPaymentAllocation extends BusinessModel
{
    protected $table = 'supplier_payment_allocations';

    protected $fillable = [
        'supplier_payment_id',
        'supplier_id',
        'purchase_id',
        'target_type',
        'amount',
        'allocated_at',
        'allocated_by_user_id',
        'idempotency_key',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'allocated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function allocatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by_user_id');
    }

    public function supplierAllocationReleases(): HasMany
    {
        return $this->hasMany(SupplierAllocationRelease::class, 'supplier_payment_allocation_id');
    }

    public function releases(): HasMany
    {
        return $this->hasMany(SupplierAllocationRelease::class, 'supplier_payment_allocation_id');
    }
}
