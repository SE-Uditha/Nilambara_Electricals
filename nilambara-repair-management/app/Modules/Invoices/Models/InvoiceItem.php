<?php

namespace App\Modules\Invoices\Models;

use App\Models\BusinessModel;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Quotations\Models\QuotationItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends BusinessModel
{
    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'invoice_job_id',
        'line_number',
        'quotation_item_id',
        'item_type',
        'spare_part_id',
        'description',
        'quantity',
        'unit_price',
        'unit_cost',
        'discount_amount',
        'line_total',
    ];

    protected array $immutableFields = [
        'id',
        'invoice_id',
        'invoice_job_id',
        'line_number',
        'quotation_item_id',
        'item_type',
        'spare_part_id',
        'description',
        'quantity',
        'unit_price',
        'unit_cost',
        'discount_amount',
        'line_total',
    ];

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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function invoiceJob(): BelongsTo
    {
        return $this->belongsTo(InvoiceJob::class, 'invoice_job_id');
    }

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class, 'quotation_item_id');
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class, 'spare_part_id');
    }
}
