<?php

namespace App\Modules\Inventory\Models;

use App\Enums\DocumentStatus;
use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends BusinessModel
{
    protected $table = 'stock_adjustments';

    protected $fillable = [
        'adjustment_number',
        'adjusted_at',
        'adjustment_type',
        'reason',
        'created_by_user_id',
        'status',
        'approved_at',
        'approved_by_user_id',
    ];

    protected array $immutableFields = ['id', 'adjustment_number'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'adjusted_at' => 'datetime',
            'status' => DocumentStatus::class,
            'approved_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function stockAdjustmentItems(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class, 'stock_adjustment_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class, 'stock_adjustment_id');
    }
}
