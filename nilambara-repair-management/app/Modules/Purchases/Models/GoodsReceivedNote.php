<?php

namespace App\Modules\Purchases\Models;

use App\Enums\DocumentStatus;
use App\Models\BusinessModel;
use App\Modules\Employees\Models\Employee;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceivedNote extends BusinessModel
{
    protected $table = 'goods_received_notes';

    protected $fillable = [
        'grn_number',
        'purchase_id',
        'supplier_id',
        'received_at',
        'received_by_employee_id',
        'status',
        'finalized_at',
        'notes',
    ];

    protected array $immutableFields = ['id', 'grn_number', 'purchase_id', 'supplier_id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'status' => DocumentStatus::class,
            'finalized_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function receivedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'received_by_employee_id');
    }

    public function goodsReceivedNoteItems(): HasMany
    {
        return $this->hasMany(GoodsReceivedNoteItem::class, 'goods_received_note_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceivedNoteItem::class, 'goods_received_note_id');
    }
}
