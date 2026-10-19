<?php

namespace App\Console\Commands;

use App\Modules\BackupRestore\Services\BackupService;
use Illuminate\Console\Command;

class CreateDatabaseBackupCommand extends Command
{
    protected $signature = 'backup:create {--scheduled : Record this as an automatic scheduled backup}';

    protected $description = 'Create and verify a private database backup';

    public function handle(BackupService $backups): int
    {
        try {
            $backup = $backups->create((bool) $this->option('scheduled'), null);
        } catch (\Throwable $exception) {
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Verified private backup created. Reference: '.$backup->getKey());

        return self::SUCCESS;
    }
}
