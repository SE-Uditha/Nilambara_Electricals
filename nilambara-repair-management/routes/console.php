<?php

use App\Modules\BackupRestore\Services\BackupConfigurationService;
use Illuminate\Support\Facades\Schedule;

app(BackupConfigurationService::class)->apply();

Schedule::command('backup:create --scheduled')
    ->dailyAt((string) config('backup.schedule_time'))
    ->timezone((string) config('backup.timezone'))
    ->withoutOverlapping(180)
    ->appendOutputTo(storage_path('logs/backup-scheduler.log'));
