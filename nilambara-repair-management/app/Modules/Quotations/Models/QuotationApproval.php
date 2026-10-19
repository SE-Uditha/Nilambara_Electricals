<?php

namespace App\Modules\Quotations\Models;

use App\Enums\ApprovalStatus;
use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationApproval extends BusinessModel
{
    protected $table = 'quotation_approvals';

    protected $fillable = [
        'quotation_id',
        'decision',
        'decided_at',
        'customer_contact_name',
        'approval_method',
        'recorded_by_user_id',
        'notes',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function casts(): array
    {
        return [
            'decision' => ApprovalStatus::class,
            'decided_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
