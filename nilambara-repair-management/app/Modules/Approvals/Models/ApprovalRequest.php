<?php

namespace App\Modules\Approvals\Models;

use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ApprovalRequest extends BusinessModel
{
    protected $table = 'authorized_approval_requests';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected bool $preserveRecord = true;

    protected array $immutableFields = ['id', 'source_key', 'kind', 'requested_by_user_id', 'purchase_id', 'payment_id', 'spare_part_id', 'amount', 'reason', 'payload', 'payload_hash'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'amount' => 'decimal:2', 'decided_at' => 'datetime'];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::updating(function (self $request): void {
            if ($request->getRawOriginal('status') !== 'pending' && $request->isDirty(['status', 'decided_by_user_id', 'decided_at', 'decision_comment'])) {
                throw new LogicException('Approval decisions cannot be changed.');
            }
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
