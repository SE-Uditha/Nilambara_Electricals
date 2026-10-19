<?php

namespace App\Modules\Inventory\Models;

use App\Models\BusinessModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SparePartCategory extends BusinessModel
{
    protected $table = 'spare_part_categories';

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

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class, 'category_id');
    }
}
