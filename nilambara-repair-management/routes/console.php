<?php

use App\Modules\BackupRestore\Services\BackupConfigurationService;
use Illuminate\Support\Facades\Schedule;

app(BackupConfigurationService::class)->apply();

Schedule::command('backup:create --scheduled')
    ->dailyAt((string) config('backup.schedule_time'))
    ->timezone((string) config('backup.timezone'))
    ->withoutOverlapping(180)
    ->appendOutputTo(storage_path('logs/backup-scheduler.log'));

Schedule::command('jobs:sync-drive')
    ->hourly()
    ->timezone((string) config('backup.timezone'))
    ->withoutOverlapping(60)
    ->when(fn (): bool => (bool) config('job_data.enabled'))
    ->appendOutputTo(storage_path('logs/job-data-drive-sync.log'));
