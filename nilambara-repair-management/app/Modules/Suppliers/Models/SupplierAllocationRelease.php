<?php

namespace App\Modules\Suppliers\Models;

use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAllocationRelease extends BusinessModel
{
    protected $table = 'supplier_allocation_releases';

    protected $fillable = [
        'supplier_payment_allocation_id',
        'supplier_payment_id',
        'supplier_payment_reversal_id',
        'amount',
        'released_at',
        'released_by_user_id',
        'reason',
        'idempotency_key',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'released_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function supplierPaymentAllocation(): BelongsTo
    {
        return $this->belongsTo(SupplierPaymentAllocation::class, 'supplier_payment_allocation_id');
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function supplierPaymentReversal(): BelongsTo
    {
        return $this->belongsTo(SupplierPaymentReversal::class, 'supplier_payment_reversal_id');
    }

    public function releasedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }
}
