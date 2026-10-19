<?php

namespace App\Modules\Inventory\Models;

use App\Enums\DocumentStatus;
use App\Models\BusinessModel;
use App\Models\User;
use App\Modules\Payments\Models\PaymentReversal;
use App\Modules\POS\Models\PosSale;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockReturn extends BusinessModel
{
    protected $table = 'stock_returns';

    protected $fillable = [
        'return_number',
        'return_type',
        'repair_job_id',
        'pos_sale_id',
        'purchase_id',
        'returned_at',
        'recorded_by_user_id',
        'status',
        'reason',
        'idempotency_key',
        'idempotency_hash',
        'refund_amount',
        'refund_payment_reversal_id',
    ];

    protected array $immutableFields = ['id', 'return_number'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
            'refund_amount' => 'decimal:2',
            'status' => DocumentStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function repairJob(): BelongsTo
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function posSale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function refundPaymentReversal(): BelongsTo
    {
        return $this->belongsTo(PaymentReversal::class, 'refund_payment_reversal_id');
    }

    public function stockReturnItems(): HasMany
    {
        return $this->hasMany(StockReturnItem::class, 'stock_return_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReturnItem::class, 'stock_return_id');
    }
}
