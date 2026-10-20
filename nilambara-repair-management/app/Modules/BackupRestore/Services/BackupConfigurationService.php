<?php

namespace App\Modules\BackupRestore\Services;

use App\Models\User;
use App\Modules\Settings\Models\SystemSetting;
use App\Support\BusinessAudit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackupConfigurationService
{
    private const KEYS = ['root', 'schedule_time', 'retention_days', 'maximum_backups'];

    public function apply(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }
        foreach (SystemSetting::where('group', 'backup')->whereIn('key', self::KEYS)->get() as $setting) {
            config(['backup.'.$setting->key => $setting->key === 'root' ? Crypt::decryptString($setting->value) : $setting->value]);
        }
    }

    public function update(array $values, User $actor): void
    {
        abort_unless($actor->hasRole('administrator') && $actor->can('backups.configure'), 403);
        DB::transaction(function () use ($values, $actor): void {
            foreach (array_intersect_key($values, array_flip(self::KEYS)) as $key => $value) {
                if ($key === 'root') {
                    abort_unless(app(BackupService::class)->isSafeDestination($value), 422, 'Destination must be a private absolute path outside web roots.');
                }
                $setting = SystemSetting::updateOrCreate(['group' => 'backup', 'key' => $key], [
                    'value' => $key === 'root' ? Crypt::encryptString($value) : $value,
                    'value_type' => 'string', 'description' => 'Backup policy configuration.', 'updated_by_user_id' => $actor->id,
                ]);
                BusinessAudit::record($actor, 'backup.configuration.updated', SystemSetting::class, $setting->id,
                    newValues: ['key' => $key, 'value' => $key === 'root' ? '[PRIVATE DESTINATION]' : $value]);
            }
        });
        $this->apply();
    }
}
