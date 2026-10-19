<?php

namespace App\Modules\Suppliers\Models;

use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierPaymentReversal extends BusinessModel
{
    protected $table = 'supplier_payment_reversals';

    protected $fillable = [
        'supplier_payment_id',
        'supplier_id',
        'reversal_type',
        'amount',
        'reversed_at',
        'reversed_by_user_id',
        'reason',
        'idempotency_key',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'reversed_at' => 'datetime',
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

    public function reversedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function supplierAllocationReleases(): HasMany
    {
        return $this->hasMany(SupplierAllocationRelease::class, 'supplier_payment_reversal_id');
    }

    public function supplierAccountEntries(): HasOne
    {
        return $this->hasOne(SupplierAccountEntry::class, 'supplier_payment_reversal_id');
    }
}
