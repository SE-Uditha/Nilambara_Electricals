<?php

namespace App\Modules\CreditManagement\Models;

use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentReversal;
use App\Modules\POS\Models\PosSale;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CustomerAccountEntry extends BusinessModel
{
    protected $table = 'customer_account_entries';

    protected $fillable = [
        'customer_id',
        'entry_type',
        'amount',
        'occurred_at',
        'reference_number',
        'description',
        'recorded_by_user_id',
        'invoice_id',
        'pos_sale_id',
        'payment_id',
        'payment_reversal_id',
        'reverses_entry_id',
        'idempotency_key',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
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

    public function paymentReversal(): BelongsTo
    {
        return $this->belongsTo(PaymentReversal::class, 'payment_reversal_id');
    }

    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(CustomerAccountEntry::class, 'reverses_entry_id');
    }

    public function relatedCustomerAccountEntries(): HasOne
    {
        return $this->hasOne(CustomerAccountEntry::class, 'reverses_entry_id');
    }
}
