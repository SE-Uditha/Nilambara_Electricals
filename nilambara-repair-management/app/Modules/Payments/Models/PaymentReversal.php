<?php

namespace App\Modules\Payments\Models;

use App\Enums\PaymentMethod;
use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\CreditManagement\Models\CustomerAccountEntry;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PaymentReversal extends BusinessModel
{
    protected $table = 'payment_reversals';

    protected $fillable = [
        'payment_id',
        'customer_id',
        'reversal_type',
        'amount',
        'method',
        'reversed_at',
        'reversed_by_user_id',
        'reason',
        'external_reference',
        'idempotency_key',
        'customer_scope_id',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'reversed_at' => 'datetime',
            'customer_scope_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function reversedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function paymentAllocationReleases(): HasMany
    {
        return $this->hasMany(PaymentAllocationRelease::class, 'payment_reversal_id');
    }

    public function customerAccountEntries(): HasOne
    {
        return $this->hasOne(CustomerAccountEntry::class, 'payment_reversal_id');
    }
}
