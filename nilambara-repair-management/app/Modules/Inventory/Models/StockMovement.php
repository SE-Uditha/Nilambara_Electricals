<?php

namespace App\Modules\Inventory\Models;

use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\POS\Models\PosSaleItem;
use App\Modules\Purchases\Models\GoodsReceivedNoteItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockMovement extends BusinessModel
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'spare_part_id',
        'location_id',
        'movement_type',
        'quantity_delta',
        'unit_cost',
        'occurred_at',
        'reference_number',
        'recorded_by_user_id',
        'reason',
        'idempotency_key',
        'grn_item_id',
        'job_part_usage_id',
        'pos_sale_item_id',
        'return_item_id',
        'adjustment_item_id',
        'count_item_id',
        'reverses_movement_id',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class, 'spare_part_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'location_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function grnItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceivedNoteItem::class, 'grn_item_id');
    }

    public function jobPartUsage(): BelongsTo
    {
        return $this->belongsTo(JobPartUsage::class, 'job_part_usage_id');
    }

    public function posSaleItem(): BelongsTo
    {
        return $this->belongsTo(PosSaleItem::class, 'pos_sale_item_id');
    }

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(StockReturnItem::class, 'return_item_id');
    }

    public function adjustmentItem(): BelongsTo
    {
        return $this->belongsTo(StockAdjustmentItem::class, 'adjustment_item_id');
    }

    public function countItem(): BelongsTo
    {
        return $this->belongsTo(StockCountItem::class, 'count_item_id');
    }

    public function reversesMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'reverses_movement_id');
    }

    public function relatedStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reverses_movement_id');
    }
}
