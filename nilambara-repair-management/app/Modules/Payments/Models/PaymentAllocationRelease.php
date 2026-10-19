<?php

namespace App\Modules\Payments\Models;

use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocationRelease extends BusinessModel
{
    protected $table = 'payment_allocation_releases';

    protected $fillable = [
        'payment_allocation_id',
        'payment_id',
        'payment_reversal_id',
        'amount',
        'reason_type',
        'released_at',
        'released_by_user_id',
        'notes',
        'idempotency_key',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'released_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function paymentAllocation(): BelongsTo
    {
        return $this->belongsTo(PaymentAllocation::class, 'payment_allocation_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function paymentReversal(): BelongsTo
    {
        return $this->belongsTo(PaymentReversal::class, 'payment_reversal_id');
    }

    public function releasedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }
}
