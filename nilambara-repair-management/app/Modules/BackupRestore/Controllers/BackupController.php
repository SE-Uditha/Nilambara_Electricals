<?php

namespace App\Modules\BackupRestore\Controllers;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\BackupRestore\Models\BackupHistory;
use App\Modules\BackupRestore\Services\BackupConfigurationService;
use App\Modules\BackupRestore\Services\BackupService;
use App\Modules\BackupRestore\Services\GoogleDriveBackupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController
{
    public function index(BackupService $backups): View
    {
        $history = BackupHistory::query()->with('initiatedByUser:id,name')->orderByDesc('backup_at')->paginate(30);
        $expected = now(config('backup.timezone'))->setTimeFromTimeString(config('backup.schedule_time'));
        if (now(config('backup.timezone'))->lt($expected)) {
            $expected = $expected->subDay();
        }

        return view('modules.backup-restore.index', [
            'history' => $history,
            'destinationConfigured' => $backups->isConfigured(),
            'cloudConfigured' => app(GoogleDriveBackupService::class)->configured(),
            'missedBackup' => ! BackupHistory::where('backup_type', 'scheduled')->where('status', 'completed')
                ->whereNull('deleted_at')->where('backup_at', '>=', $expected)->exists(),
            'cleanupFailed' => AuditLog::where('action', 'backup.cleanup.failed')->where('occurred_at', '>=', now()->subDays(7))->exists(),
        ]);
    }

    public function create(Request $request, BackupService $backups): RedirectResponse
    {
        if (! $backups->isConfigured()) {
            return back()->withErrors(['backup' => __('backups.destination_unavailable')]);
        }

        try {
            $backup = $backups->create(false, $request->user());
        } catch (RuntimeException $exception) {
            Log::error('A manual database backup failed.', ['message' => $exception->getMessage()]);

            return back()->withErrors(['backup' => __('backups.backup_failed')]);
        }

        return back()->with('status', __('backups.backup_created', ['id' => $backup->getKey()]));
    }

    public function download(BackupHistory $backup, Request $request, BackupService $backups): BinaryFileResponse
    {
        try {
            $path = $backups->downloadPath($backup);
        } catch (RuntimeException) {
            abort(404);
        }

        AuditLog::query()->create([
            'user_id' => $request->user()->getKey(),
            'action' => 'backup.downloaded',
            'entity_type' => BackupHistory::class,
            'entity_id' => $backup->getKey(),
            'occurred_at' => now(),
            'new_values' => ['backup_history_id' => $backup->getKey()],
            'ip_address' => $request->ip(),
            'request_id' => $request->header('X-Request-Id'),
        ]);

        return response()->download($path, basename($backup->file_reference), [
            'Content-Type' => 'application/sql',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function verify(BackupHistory $backup, BackupService $backups): RedirectResponse
    {
        $valid = $backups->verify($backup);
        $backup->update(['verification_status' => $valid ? 'checksum_verified' : 'failed', 'verified_at' => $valid ? now() : null]);

        return back()->with('status', $valid ? 'File checksum verified. Recoverability requires an isolated restore test.' : 'Verification failed: file missing, unreadable or changed.');
    }

    public function configure(Request $request, BackupConfigurationService $configuration): RedirectResponse
    {
        $values = $request->validate([
            'root' => ['sometimes', 'required', 'string', 'max:1000'],
            'schedule_time' => ['required', 'date_format:H:i'],
            'retention_days' => ['required', 'integer', 'between:1,3650'],
            'maximum_backups' => ['required', 'integer', 'between:1,1000'],
        ]);
        $configuration->update($values, $request->user());

        return back()->with('status', 'Backup policy updated. The host scheduler must run every minute.');
    }
}
