<?php

namespace App\Modules\Inventory\Models;

use App\Models\BusinessModel;
use App\Modules\POS\Models\PosSaleItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockReturnItem extends BusinessModel
{
    protected $table = 'stock_return_items';

    protected $fillable = [
        'stock_return_id',
        'spare_part_id',
        'location_id',
        'quantity',
        'unit_cost',
        'condition',
        'notes',
        'pos_sale_item_id',
        'refund_amount',
    ];

    protected array $immutableFields = ['id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'refund_amount' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function stockReturn(): BelongsTo
    {
        return $this->belongsTo(StockReturn::class, 'stock_return_id');
    }

    public function posSaleItem(): BelongsTo
    {
        return $this->belongsTo(PosSaleItem::class, 'pos_sale_item_id');
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
        return $this->hasOne(StockMovement::class, 'return_item_id');
    }
}
