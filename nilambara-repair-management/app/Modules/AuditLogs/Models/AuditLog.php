<?php

namespace App\Modules\AuditLogs\Models;

use App\Models\BusinessModel;
use App\Models\User;
use App\Support\SensitiveData;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends BusinessModel
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'occurred_at',
        'old_values',
        'new_values',
        'ip_address',
        'request_id',
    ];

    protected array $immutableFields = ['id'];

    protected bool $appendOnly = true;

    protected function oldValues(): Attribute
    {
        return $this->auditValuesAttribute();
    }

    protected function newValues(): Attribute
    {
        return $this->auditValuesAttribute();
    }

    private function auditValuesAttribute(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): mixed => SensitiveData::redact(SensitiveData::decodeJsonValue($value)),
            set: static function (mixed $value): ?string {
                $sanitized = SensitiveData::redact(SensitiveData::decodeJsonValue($value));

                return $sanitized === null ? null : json_encode($sanitized, JSON_THROW_ON_ERROR);
            },
        );
    }

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'occurred_at' => 'datetime',
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
