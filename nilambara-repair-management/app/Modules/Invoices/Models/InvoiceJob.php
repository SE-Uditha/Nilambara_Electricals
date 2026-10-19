<?php

namespace App\Modules\Invoices\Models;

use App\Models\BusinessModel;
use App\Modules\Customers\Models\Customer;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceJob extends BusinessModel
{
    protected $table = 'invoice_jobs';

    protected $fillable = [
        'invoice_id',
        'repair_job_id',
        'customer_id',
        'job_amount',
        'job_snapshot',
    ];

    protected array $immutableFields = ['id', 'invoice_id', 'repair_job_id', 'customer_id', 'job_amount', 'job_snapshot'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'job_amount' => 'decimal:2',
            'job_snapshot' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_job_id');
    }
}
