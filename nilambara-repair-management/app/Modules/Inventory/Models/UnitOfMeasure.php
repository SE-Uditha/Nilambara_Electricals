<?php

namespace App\Modules\Inventory\Models;

use App\Models\BusinessModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitOfMeasure extends BusinessModel
{
    protected $table = 'units_of_measure';

    protected $fillable = [
        'code',
        'name',
        'quantity_scale',
    ];

    protected array $immutableFields = ['id'];

    protected function casts(): array
    {
        return [
            'quantity_scale' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class, 'unit_id');
    }
}
