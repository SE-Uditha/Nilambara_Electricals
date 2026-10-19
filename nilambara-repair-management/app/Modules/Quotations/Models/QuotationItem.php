<?php

namespace App\Modules\Quotations\Models;

use App\Models\BusinessModel;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Invoices\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationItem extends BusinessModel
{
    protected $table = 'quotation_items';

    protected $fillable = [
        'quotation_id',
        'line_number',
        'display_order',
        'item_type',
        'spare_part_id',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'line_total',
        'removed_at',
    ];

    protected array $immutableFields = ['id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'display_order' => 'integer',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'removed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class, 'spare_part_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'quotation_item_id');
    }
}
