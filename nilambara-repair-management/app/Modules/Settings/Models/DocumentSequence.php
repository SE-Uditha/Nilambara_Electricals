<?php

namespace App\Modules\Settings\Models;

use App\Models\BusinessModel;

class DocumentSequence extends BusinessModel
{
    protected $table = 'document_sequences';

    protected $fillable = [
        'scope',
        'prefix',
        'padding',
        'next_value',
        'description',
    ];

    protected array $immutableFields = ['id'];

    protected function casts(): array
    {
        return [
            'padding' => 'integer',
            'next_value' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
