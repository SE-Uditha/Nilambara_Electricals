<?php

namespace App\Modules\Purchases\Models;

use App\Models\BusinessModel;
use App\Modules\Inventory\Models\SparePart;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseItem extends BusinessModel
{
    protected $table = 'purchase_items';

    protected $fillable = [
        'purchase_id',
        'line_number',
        'spare_part_id',
        'description',
        'quantity',
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
            'unit_cost' => 'decimal:4',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class, 'spare_part_id');
    }

    public function goodsReceivedNoteItems(): HasMany
    {
        return $this->hasMany(GoodsReceivedNoteItem::class, 'purchase_item_id');
    }
}
