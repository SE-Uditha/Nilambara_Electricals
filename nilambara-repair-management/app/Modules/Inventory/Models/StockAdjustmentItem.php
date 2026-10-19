<?php

namespace App\Modules\Inventory\Models;

use App\Models\BusinessModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockAdjustmentItem extends BusinessModel
{
    protected $table = 'stock_adjustment_items';

    protected $fillable = [
        'stock_adjustment_id',
        'spare_part_id',
        'location_id',
        'quantity_delta',
        'notes',
    ];

    protected array $immutableFields = ['id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class, 'spare_part_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    public function stockMovements(): HasOne
    {
        return $this->hasOne(StockMovement::class, 'adjustment_item_id');
    }
}
