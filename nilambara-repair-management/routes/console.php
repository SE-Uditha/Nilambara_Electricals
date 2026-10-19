<?php

use App\Modules\BackupRestore\Services\BackupService;
use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:create --scheduled')
    ->dailyAt((string) config('backup.schedule_time'))
    ->timezone((string) config('backup.timezone'))
    ->when(fn (): bool => app(BackupService::class)->isConfigured());
