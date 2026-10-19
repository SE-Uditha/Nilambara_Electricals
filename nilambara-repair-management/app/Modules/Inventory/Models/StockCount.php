<?php

namespace App\Modules\Inventory\Models;

use App\Enums\DocumentStatus;
use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends BusinessModel
{
    protected $table = 'stock_counts';

    protected $fillable = [
        'count_number',
        'counted_at',
        'counted_by_user_id',
        'status',
        'notes',
    ];

    protected array $immutableFields = ['id', 'count_number'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'counted_at' => 'datetime',
            'status' => DocumentStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function countedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by_user_id');
    }

    public function stockCountItems(): HasMany
    {
        return $this->hasMany(StockCountItem::class, 'stock_count_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class, 'stock_count_id');
    }
}
