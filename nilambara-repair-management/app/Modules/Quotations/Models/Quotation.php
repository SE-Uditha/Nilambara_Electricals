<?php

namespace App\Modules\Quotations\Models;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quotation extends BusinessModel
{
    protected $table = 'quotations';

    protected $fillable = [
        'quotation_number',
        'revision',
        'revises_quotation_id',
        'quotation_date',
        'repair_job_id',
        'customer_id',
        'proposed_repair_description',
        'document_snapshot',
        'labour_charge',
        'subtotal',
        'discount_amount',
        'total',
        'valid_until',
        'terms_and_conditions',
        'approval_status',
        'status',
        'created_by_user_id',
    ];

    protected array $immutableFields = ['id', 'quotation_number', 'repair_job_id', 'customer_id', 'revises_quotation_id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'quotation_date' => 'date',
            'document_snapshot' => 'array',
            'labour_charge' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'valid_until' => 'date',
            'approval_status' => ApprovalStatus::class,
            'status' => DocumentStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function revisesQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'revises_quotation_id');
    }

    public function relatedQuotations(): HasOne
    {
        return $this->hasOne(Quotation::class, 'revises_quotation_id');
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function quotationItems(): HasMany
    {
        return $this->hasMany(QuotationItem::class, 'quotation_id');
    }

    public function quotationApprovals(): HasMany
    {
        return $this->hasMany(QuotationApproval::class, 'quotation_id');
    }

    public function documentPrintHistories(): HasMany
    {
        return $this->hasMany(DocumentPrintHistory::class, 'quotation_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class, 'quotation_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(QuotationApproval::class, 'quotation_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(Quotation::class, 'revises_quotation_id');
    }
}
