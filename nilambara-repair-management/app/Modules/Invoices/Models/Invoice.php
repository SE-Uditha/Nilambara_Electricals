<?php

namespace App\Modules\Invoices\Models;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTerms;
use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\CreditManagement\Models\CustomerAccountEntry;
use App\Modules\Customers\Models\Customer;
use App\Modules\Employees\Models\Employee;
use App\Modules\Payments\Models\PaymentAllocation;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class Invoice extends BusinessModel
{
    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'customer_id',
        'subtotal',
        'discount_amount',
        'total',
        'payment_terms',
        'payment_method',
        'due_date',
        'status',
        'cashier_employee_id',
        'collected_by',
        'vehicle_number',
        'delivery_at',
        'delivery_information',
        'special_notes',
        'document_snapshot',
        'finalized_at',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
    ];

    protected array $immutableFields = ['id', 'invoice_number', 'customer_id'];

    protected bool $preserveRecord = true;

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            if ($invoice->getRawOriginal('status') !== DocumentStatus::Finalized->value) {
                return;
            }

            $allowed = ['status', 'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason', 'updated_at'];
            foreach (array_keys($invoice->getDirty()) as $field) {
                if (! in_array($field, $allowed, true)) {
                    throw new LogicException('Issued invoice field ['.$field.'] is immutable.');
                }
            }
            if ($invoice->isDirty('status') && $invoice->status !== DocumentStatus::Cancelled) {
                throw new LogicException('Issued invoices can only transition to cancelled.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_terms' => PaymentTerms::class,
            'payment_method' => PaymentMethod::class,
            'due_date' => 'date',
            'status' => DocumentStatus::class,
            'delivery_at' => 'datetime',
            'document_snapshot' => 'array',
            'finalized_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function cashierEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'cashier_employee_id');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function invoiceJobs(): HasMany
    {
        return $this->hasMany(InvoiceJob::class, 'invoice_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'invoice_id');
    }

    public function customerAccountEntries(): HasOne
    {
        return $this->hasOne(CustomerAccountEntry::class, 'invoice_id');
    }

    public function documentPrintHistories(): HasMany
    {
        return $this->hasMany(DocumentPrintHistory::class, 'invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function jobLinks(): HasMany
    {
        return $this->hasMany(InvoiceJob::class, 'invoice_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'invoice_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(InvoiceHistory::class, 'invoice_id')->orderBy('event_at')->orderBy('id');
    }

    public function repairJobs(): BelongsToMany
    {
        return $this->belongsToMany(RepairJob::class, 'invoice_jobs', 'invoice_id', 'repair_job_id')->withPivot(['id', 'customer_id', 'job_amount'])->withTimestamps();
    }
}
