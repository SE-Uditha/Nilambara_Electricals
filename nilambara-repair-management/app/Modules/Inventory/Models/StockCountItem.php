<?php

namespace App\Modules\Inventory\Models;

use App\Models\BusinessModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockCountItem extends BusinessModel
{
    protected $table = 'stock_count_items';

    protected $fillable = [
        'stock_count_id',
        'spare_part_id',
        'location_id',
        'expected_quantity',
        'counted_quantity',
        'notes',
    ];

    protected array $immutableFields = ['id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:3',
            'counted_quantity' => 'decimal:3',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
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
        return $this->hasOne(StockMovement::class, 'count_item_id');
    }
}
