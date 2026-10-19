<?php

namespace App\Modules\Customers\Models;

use App\Enums\CustomerType;
use App\Models\BusinessModel;
use App\Modules\CreditManagement\Models\CustomerAccountEntry;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceJob;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentAllocation;
use App\Modules\Payments\Models\PaymentReversal;
use App\Modules\POS\Models\PosSale;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\Quotations\Models\Quotation;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\RepairReceipts\Models\RepairReceipt;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends BusinessModel
{
    protected $table = 'customers';

    protected $fillable = [
        'customer_code',
        'customer_type',
        'customer_name',
        'company_name',
        'contact_person',
        'address',
        'mobile_number',
        'alternate_phone',
        'email',
        'opening_balance',
        'special_notes',
        'registration_date',
        'is_active',
    ];

    protected array $immutableFields = ['id', 'customer_code', 'opening_balance'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'customer_type' => CustomerType::class,
            'opening_balance' => 'decimal:2',
            'registration_date' => 'date',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function repairJobs(): HasMany
    {
        return $this->hasMany(RepairJob::class, 'customer_id');
    }

    public function repairReceipts(): HasMany
    {
        return $this->hasMany(RepairReceipt::class, 'customer_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'customer_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function invoiceJobs(): HasMany
    {
        return $this->hasMany(InvoiceJob::class, 'customer_id');
    }

    public function posSales(): HasMany
    {
        return $this->hasMany(PosSale::class, 'customer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'customer_id');
    }

    public function paymentReversals(): HasMany
    {
        return $this->hasMany(PaymentReversal::class, 'customer_id');
    }

    public function customerAccountEntries(): HasMany
    {
        return $this->hasMany(CustomerAccountEntry::class, 'customer_id');
    }

    public function documentPrintHistories(): HasMany
    {
        return $this->hasMany(DocumentPrintHistory::class, 'customer_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(RepairReceipt::class, 'customer_id');
    }

    public function accountEntries(): HasMany
    {
        return $this->hasMany(CustomerAccountEntry::class, 'customer_id');
    }
}
