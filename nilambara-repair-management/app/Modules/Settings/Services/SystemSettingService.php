<?php

namespace App\Modules\Settings\Services;

use App\Models\User;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Settings\Models\SystemSetting;
use App\Support\SensitiveData;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class SystemSettingService
{
    private const BUSINESS_KEYS = [
        'name', 'address', 'contact_number', 'currency', 'timezone', 'date_format',
        'time_format', 'logo',
    ];

    public function apply(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        foreach (SystemSetting::query()->where('group', 'business')->whereIn('key', self::BUSINESS_KEYS)->get() as $setting) {
            Config::set('business.'.$setting->key, $setting->value);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values, User $actor): void
    {
        foreach (array_keys($values) as $key) {
            if (! in_array($key, self::BUSINESS_KEYS, true)) {
                throw new InvalidArgumentException('The requested business setting cannot be changed.');
            }
            SensitiveData::assertSafeStructure($values[$key]);
        }

        DB::transaction(function () use ($values, $actor): void {
            foreach ($values as $key => $value) {
                $setting = SystemSetting::query()->firstOrNew(['group' => 'business', 'key' => $key]);
                $previous = $setting->exists ? $setting->value : null;
                $setting->fill([
                    'value' => $value,
                    'value_type' => is_bool($value) ? 'boolean' : 'string',
                    'description' => 'Business profile setting.',
                    'updated_by_user_id' => $actor->getKey(),
                ])->save();

                AuditLog::query()->create([
                    'user_id' => $actor->getKey(),
                    'action' => 'settings.business.updated',
                    'entity_type' => SystemSetting::class,
                    'entity_id' => $setting->getKey(),
                    'occurred_at' => now(),
                    'old_values' => ['key' => $key, 'value' => $previous],
                    'new_values' => ['key' => $key, 'value' => $value],
                    'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                    'request_id' => app()->runningInConsole() ? null : request()->header('X-Request-Id'),
                ]);
            }
        });

    }
}
