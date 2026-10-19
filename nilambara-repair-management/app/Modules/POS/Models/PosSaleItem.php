<?php

namespace App\Modules\POS\Models;

use App\Models\BusinessModel;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReturnItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PosSaleItem extends BusinessModel
{
    protected $table = 'pos_sale_items';

    protected $fillable = [
        'pos_sale_id',
        'line_number',
        'spare_part_id',
        'location_id',
        'description',
        'quantity',
        'unit_price',
        'unit_cost',
        'discount_amount',
        'line_total',
    ];

    protected array $immutableFields = ['id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function posSale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
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
        return $this->hasOne(StockMovement::class, 'pos_sale_item_id');
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(StockReturnItem::class, 'pos_sale_item_id');
    }
}
