<?php

namespace App\Modules\Suppliers\Models;

use App\Models\BusinessModel;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Purchases\Models\GoodsReceivedNote;
use App\Modules\Purchases\Models\Purchase;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends BusinessModel
{
    protected $table = 'suppliers';

    protected $fillable = [
        'supplier_code',
        'name',
        'contact_person',
        'address',
        'mobile_number',
        'telephone_number',
        'email',
        'opening_balance',
        'credit_terms',
        'credit_days',
        'notes',
        'is_active',
    ];

    protected array $immutableFields = ['id', 'supplier_code', 'opening_balance'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'credit_days' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class, 'supplier_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class, 'supplier_id');
    }

    public function goodsReceivedNotes(): HasMany
    {
        return $this->hasMany(GoodsReceivedNote::class, 'supplier_id');
    }

    public function supplierPayments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class, 'supplier_id');
    }

    public function supplierPaymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'supplier_id');
    }

    public function supplierPaymentReversals(): HasMany
    {
        return $this->hasMany(SupplierPaymentReversal::class, 'supplier_id');
    }

    public function supplierAccountEntries(): HasMany
    {
        return $this->hasMany(SupplierAccountEntry::class, 'supplier_id');
    }

    public function accountEntries(): HasMany
    {
        return $this->hasMany(SupplierAccountEntry::class, 'supplier_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class, 'supplier_id');
    }
}
