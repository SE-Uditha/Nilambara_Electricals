<?php

namespace App\Modules\Payments\Models;

use App\Models\BusinessModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends BusinessModel
{
    protected $table = 'expense_categories';

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected array $immutableFields = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'expense_category_id');
    }
}
