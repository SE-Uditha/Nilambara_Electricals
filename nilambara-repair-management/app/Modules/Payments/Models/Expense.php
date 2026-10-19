<?php

namespace App\Modules\Payments\Models;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Models\BusinessModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends BusinessModel
{
    protected $table = 'expenses';

    protected $fillable = [
        'expense_number',
        'expense_category_id',
        'incurred_at',
        'amount',
        'method',
        'description',
        'payee',
        'external_reference',
        'recorded_by_user_id',
        'status',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected array $immutableFields = ['id', 'expense_number'];

    protected bool $preserveRecord = true;

    protected function casts(): array
    {
        return [
            'incurred_at' => 'datetime',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'status' => DocumentStatus::class,
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
