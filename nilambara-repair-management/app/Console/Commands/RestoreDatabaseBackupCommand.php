<?php

namespace App\Console\Commands;

use App\Modules\BackupRestore\Models\BackupHistory;
use App\Modules\BackupRestore\Models\BackupRestore;
use App\Modules\BackupRestore\Services\BackupService;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RestoreDatabaseBackupCommand extends Command
{
    protected $signature = 'backup:restore {backupId : Verified backup history ID}';

    protected $description = 'Restore a verified database backup through a confirmed maintenance-mode CLI workflow';

    public function handle(BackupService $backups): int
    {
        $backup = BackupHistory::query()->find($this->argument('backupId'));
        if ($backup === null || ! $backups->verify($backup)) {
            $this->error('The selected backup does not exist or failed verification.');

            return self::FAILURE;
        }

        $database = (string) config('database.connections.'.config('database.default').'.database');
        if (! $this->confirm('This will replace all current data in database '.$database.'. Continue?', false)
            || ! hash_equals($database, (string) $this->ask('Type the exact database name to confirm'))) {
            $this->warn('Restore cancelled; no database changes were made.');

            return self::FAILURE;
        }

        $username = Str::lower(trim((string) $this->ask('Username of the administrator authorizing this restore')));
        $actor = User::query()->where('username', $username)->first();
        if ($actor === null || ! $actor->hasActiveAccount() || ! $actor->hasRole('administrator')) {
            $this->error('An active administrator account is required to authorize a restore.');

            return self::FAILURE;
        }

        $restore = null;
        try {
            $preRestoreBackup = $backups->create(false, $actor, false);
        } catch (\Throwable $exception) {
            $this->error('Restore stopped because the pre-restore safety backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (Artisan::call('down') !== self::SUCCESS) {
            $this->error('Maintenance mode could not be enabled; restore was not started.');

            return self::FAILURE;
        }

        $restore = BackupRestore::query()->create([
            'backup_history_id' => $backup->getKey(),
            'initiated_by_user_id' => $actor->getKey(),
            'confirmed_at' => now(),
            'started_at' => now(),
            'status' => 'running',
        ]);
        try {
            $backups->restoreDump($backup);
        } catch (\Throwable $exception) {
            Log::critical('Database restore failed; maintenance mode remains enabled.', ['backup_history_id' => $backup->getKey()]);
            try {
                BackupRestore::query()->create([
                    'backup_history_id' => $backup->getKey(),
                    'initiated_by_user_id' => $actor->getKey(),
                    'confirmed_at' => $restore->confirmed_at,
                    'started_at' => $restore->started_at,
                    'completed_at' => now(),
                    'status' => 'failed',
                    'error_details' => mb_substr($exception->getMessage(), 0, 2000),
                ]);
            } catch (\Throwable $recordingException) {
                Log::critical('The failed database restore could not be recorded in the restored database.', ['exception' => $recordingException::class]);
            }
            $this->error('Restore failed. Maintenance mode remains enabled because database consistency is unknown.');

            return self::FAILURE;
        }

        try {
            $restoredAt = now();
            $restoredBackup = BackupHistory::query()->where('file_reference', $backup->file_reference)->first();
            if ($restoredBackup === null) {
                $restoredBackup = BackupHistory::query()->create([
                    'backup_at' => $backup->backup_at,
                    'file_reference' => $backup->file_reference,
                    'backup_type' => $backup->backup_type,
                    'status' => 'completed',
                    'storage_location' => $backup->storage_location,
                    'checksum_sha256' => $backup->checksum_sha256,
                    'verified_at' => $restoredAt,
                    'initiated_by_user_id' => $actor->getKey(),
                ]);
            } else {
                $restoredBackup->update([
                    'status' => 'completed',
                    'checksum_sha256' => $backup->checksum_sha256,
                    'verified_at' => $restoredAt,
                ]);
            }
            BackupHistory::query()->create([
                'backup_at' => $preRestoreBackup->backup_at,
                'file_reference' => $preRestoreBackup->file_reference,
                'backup_type' => 'manual',
                'status' => 'completed',
                'storage_location' => $preRestoreBackup->storage_location,
                'checksum_sha256' => $preRestoreBackup->checksum_sha256,
                'verified_at' => $preRestoreBackup->verified_at,
                'initiated_by_user_id' => $actor->getKey(),
            ]);
            $restore = BackupRestore::query()->create([
                'backup_history_id' => $restoredBackup->getKey(),
                'initiated_by_user_id' => $actor->getKey(),
                'confirmed_at' => $restore->confirmed_at,
                'started_at' => $restore->started_at,
                'completed_at' => $restoredAt,
                'status' => 'completed',
            ]);
            AuditLog::query()->create([
                'user_id' => $actor->getKey(),
                'action' => 'backup.restore.completed',
                'entity_type' => BackupRestore::class,
                'entity_id' => $restore->getKey(),
                'occurred_at' => now(),
                'new_values' => ['backup_history_id' => $backup->getKey()],
            ]);
        } catch (\Throwable $exception) {
            Log::critical('The database import completed, but post-restore history could not be recorded; maintenance mode remains enabled.', [
                'backup_history_id' => $backup->getKey(),
                'exception' => $exception::class,
            ]);
            $this->error('Database import completed, but restore history could not be finalized. Maintenance mode remains enabled.');

            return self::FAILURE;
        }
        if (Artisan::call('up') !== self::SUCCESS) {
            $this->error('The database restore completed, but maintenance mode could not be disabled.');

            return self::FAILURE;
        }
        $this->info('Restore completed and maintenance mode was disabled.');

        return self::SUCCESS;
    }
}
