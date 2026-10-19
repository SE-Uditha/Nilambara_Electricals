<?php

namespace App\Modules\PrintTemplates\Models;

use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\JobCards\Models\JobCard;
use App\Modules\Payments\Models\Payment;
use App\Modules\POS\Models\PosSale;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Quotations\Models\Quotation;
use App\Modules\RepairReceipts\Models\RepairReceipt;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentPrintHistory extends BusinessModel
{
    protected $table = 'document_print_histories';

    protected $fillable = [
        'print_template_id',
        'repair_receipt_id',
        'job_card_id',
        'quotation_id',
        'invoice_id',
        'pos_sale_id',
        'payment_id',
        'customer_id',
        'purchase_id',
        'printed_at',
        'printed_by_user_id',
        'is_duplicate',
        'duplicate_label',
        'document_snapshot',
        'output_reference',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
            'is_duplicate' => 'boolean',
            'document_snapshot' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function printTemplate(): BelongsTo
    {
        return $this->belongsTo(PrintTemplate::class, 'print_template_id');
    }

    public function repairReceipt(): BelongsTo
    {
        return $this->belongsTo(RepairReceipt::class, 'repair_receipt_id');
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class, 'job_card_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function posSale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function printedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by_user_id');
    }
}
