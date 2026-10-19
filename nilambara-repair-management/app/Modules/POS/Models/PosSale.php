<?php

namespace App\Modules\POS\Models;

use App\Enums\DocumentStatus;
use App\Enums\PaymentTerms;
use App\Models\BusinessModel;
use App\Modules\CreditManagement\Models\CustomerAccountEntry;
use App\Modules\Customers\Models\Customer;
use App\Modules\Employees\Models\Employee;
use App\Modules\Inventory\Models\StockReturn;
use App\Modules\Payments\Models\PaymentAllocation;
use App\Modules\Payments\Models\PaymentReversal;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PosSale extends BusinessModel
{
    protected $table = 'pos_sales';

    protected $fillable = [
        'sale_number',
        'sale_at',
        'customer_id',
        'cashier_employee_id',
        'subtotal',
        'discount_amount',
        'total',
        'payment_terms',
        'payment_method',
        'payment_status',
        'payment_reference',
        'cheque_date',
        'bank_name',
        'amount_tendered',
        'change_due',
        'idempotency_key',
        'idempotency_hash',
        'payment_verified_by_user_id',
        'payment_verified_at',
        'status',
        'document_snapshot',
        'finalized_at',
        'cancelled_at',
        'cancellation_reason',
        'notes',
        'customer_scope_id',
    ];

    protected array $immutableFields = ['id', 'sale_number', 'customer_id'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'sale_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_tendered' => 'decimal:2',
            'change_due' => 'decimal:2',
            'payment_terms' => PaymentTerms::class,
            'status' => DocumentStatus::class,
            'document_snapshot' => 'array',
            'finalized_at' => 'datetime',
            'cheque_date' => 'date',
            'payment_verified_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'customer_scope_id' => 'integer',
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

    public function posSaleItems(): HasMany
    {
        return $this->hasMany(PosSaleItem::class, 'pos_sale_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'pos_sale_id');
    }

    public function paymentReversals(): HasMany
    {
        return $this->hasManyThrough(
            PaymentReversal::class,
            PaymentAllocation::class,
            'pos_sale_id',
            'payment_id',
            'id',
            'payment_id',
        );
    }

    public function customerAccountEntries(): HasOne
    {
        return $this->hasOne(CustomerAccountEntry::class, 'pos_sale_id');
    }

    public function stockReturns(): HasMany
    {
        return $this->hasMany(StockReturn::class, 'pos_sale_id');
    }

    public function documentPrintHistories(): HasMany
    {
        return $this->hasMany(DocumentPrintHistory::class, 'pos_sale_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class, 'pos_sale_id');
    }
}
