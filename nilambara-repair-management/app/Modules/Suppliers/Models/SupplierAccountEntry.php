<?php

namespace App\Modules\Suppliers\Models;

use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\Purchases\Models\Purchase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierAccountEntry extends BusinessModel
{
    protected $table = 'supplier_account_entries';

    protected $fillable = [
        'supplier_id',
        'entry_type',
        'amount',
        'occurred_at',
        'reference_number',
        'description',
        'recorded_by_user_id',
        'purchase_id',
        'supplier_payment_id',
        'supplier_payment_reversal_id',
        'reverses_entry_id',
        'idempotency_key',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id');
    }

    public function supplierPaymentReversal(): BelongsTo
    {
        return $this->belongsTo(SupplierPaymentReversal::class, 'supplier_payment_reversal_id');
    }

    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(SupplierAccountEntry::class, 'reverses_entry_id');
    }

    public function relatedSupplierAccountEntries(): HasOne
    {
        return $this->hasOne(SupplierAccountEntry::class, 'reverses_entry_id');
    }
}
