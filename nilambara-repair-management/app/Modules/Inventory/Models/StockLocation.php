<?php

namespace App\Modules\Inventory\Models;

use App\Models\BusinessModel;
use App\Modules\POS\Models\PosSaleItem;
use App\Modules\Purchases\Models\GoodsReceivedNoteItem;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLocation extends BusinessModel
{
    protected $table = 'stock_locations';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected array $immutableFields = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class, 'default_location_id');
    }

    public function goodsReceivedNoteItems(): HasMany
    {
        return $this->hasMany(GoodsReceivedNoteItem::class, 'location_id');
    }

    public function posSaleItems(): HasMany
    {
        return $this->hasMany(PosSaleItem::class, 'location_id');
    }

    public function jobPartUsages(): HasMany
    {
        return $this->hasMany(JobPartUsage::class, 'location_id');
    }

    public function stockAdjustmentItems(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class, 'location_id');
    }

    public function stockCountItems(): HasMany
    {
        return $this->hasMany(StockCountItem::class, 'location_id');
    }

    public function stockReturnItems(): HasMany
    {
        return $this->hasMany(StockReturnItem::class, 'location_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'location_id');
    }
}
