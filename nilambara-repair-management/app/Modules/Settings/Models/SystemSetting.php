<?php

namespace App\Modules\Settings\Models;

use App\Models\BusinessModel;
use App\Models\User;
use App\Support\SensitiveData;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class SystemSetting extends BusinessModel
{
    protected $table = 'system_settings';

    protected $fillable = [
        'group',
        'key',
        'value',
        'value_type',
        'description',
        'updated_by_user_id',
    ];

    protected array $immutableFields = ['id'];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(static function (SystemSetting $setting): void {
            $setting->assertSafeForStorage();
        });
    }

    protected function group(): Attribute
    {
        return $this->settingNameAttribute();
    }

    protected function key(): Attribute
    {
        return $this->settingNameAttribute();
    }

    private function settingNameAttribute(): Attribute
    {
        return Attribute::make(set: static function (mixed $value): mixed {
            if (is_string($value) && SensitiveData::isSensitiveKey($value)) {
                throw new InvalidArgumentException('Credentials cannot be named as system settings.');
            }

            return $value;
        });
    }

    protected function value(): Attribute
    {
        return Attribute::make(set: static function (mixed $value): string {
            $decoded = SensitiveData::decodeJsonValue($value);

            if (is_string($value) && ! is_array($decoded)) {
                $decoded = $value;
            }

            SensitiveData::assertSafeStructure($decoded);

            return json_encode($decoded, JSON_THROW_ON_ERROR);
        });
    }

    /**
     * Saving rechecks raw/hydrated attributes. Unknown plaintext secrets under
     * ordinary names cannot be identified; query-builder/raw SQL bypasses this.
     */
    public function assertSafeForStorage(): void
    {
        foreach (['group', 'key'] as $attribute) {
            $name = $this->getAttribute($attribute);

            if (is_string($name) && SensitiveData::isSensitiveKey($name)) {
                throw new InvalidArgumentException('Credentials cannot be named as system settings.');
            }
        }

        SensitiveData::assertSafeStructure($this->value);
    }

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
