<?php

namespace App\Modules\Purchases\Models;

use App\Enums\DocumentStatus;
use App\Enums\PaymentTerms;
use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\Inventory\Models\StockReturn;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Models\SupplierAccountEntry;
use App\Modules\Suppliers\Models\SupplierPaymentAllocation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Purchase extends BusinessModel
{
    protected $table = 'purchases';

    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'supplier_invoice_number',
        'purchase_date',
        'subtotal',
        'discount_amount',
        'other_costs',
        'total',
        'payment_terms',
        'due_date',
        'status',
        'created_by_user_id',
        'finalized_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
    ];

    protected array $immutableFields = ['id', 'purchase_number', 'supplier_id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'other_costs' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_terms' => PaymentTerms::class,
            'due_date' => 'date',
            'status' => DocumentStatus::class,
            'finalized_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_id');
    }

    public function goodsReceivedNotes(): HasMany
    {
        return $this->hasMany(GoodsReceivedNote::class, 'purchase_id');
    }

    public function goodsReceivedNoteItems(): HasMany
    {
        return $this->hasMany(GoodsReceivedNoteItem::class, 'purchase_id');
    }

    public function supplierPaymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class, 'purchase_id');
    }

    public function supplierAccountEntries(): HasOne
    {
        return $this->hasOne(SupplierAccountEntry::class, 'purchase_id');
    }

    public function stockReturns(): HasMany
    {
        return $this->hasMany(StockReturn::class, 'purchase_id');
    }

    public function documentPrintHistories(): HasMany
    {
        return $this->hasMany(DocumentPrintHistory::class, 'purchase_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'purchase_id');
    }
}
