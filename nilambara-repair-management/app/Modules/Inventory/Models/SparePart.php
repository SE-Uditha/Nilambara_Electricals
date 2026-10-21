<?php

namespace App\Modules\Inventory\Models;

use App\Models\BusinessModel;
use App\Modules\Invoices\Models\InvoiceItem;
use App\Modules\Purchases\Models\GoodsReceivedNoteItem;
use App\Modules\Purchases\Models\PurchaseItem;
use App\Modules\Quotations\Models\QuotationItem;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparePart extends BusinessModel
{
    protected $table = 'spare_parts';

    protected $fillable = [
        'sku',
        'barcode',
        'name',
        'description',
        'brand_id',
        'category_id',
        'model_compatibility',
        'unit_id',
        'selling_price',
        'minimum_stock_level',
        'reorder_level',
        'supplier_id',
        'default_location_id',
        'rack',
        'is_active',
    ];

    protected array $immutableFields = ['id', 'sku'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:4',
            'minimum_stock_level' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(SparePartBrand::class, 'brand_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SparePartCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'default_location_id');
    }

    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class, 'spare_part_id');
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'spare_part_id');
    }

    public function goodsReceivedNoteItems(): HasMany
    {
        return $this->hasMany(GoodsReceivedNoteItem::class, 'spare_part_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'spare_part_id');
    }

    public function jobPartUsages(): HasMany
    {
        return $this->hasMany(JobPartUsage::class, 'spare_part_id');
    }

    public function stockAdjustmentItems(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class, 'spare_part_id');
    }

    public function stockCountItems(): HasMany
    {
        return $this->hasMany(StockCountItem::class, 'spare_part_id');
    }

    public function stockReturnItems(): HasMany
    {
        return $this->hasMany(StockReturnItem::class, 'spare_part_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'spare_part_id');
    }
}
