<?php

namespace App\Modules\BackupRestore\Controllers;

use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\BackupRestore\Models\BackupHistory;
use App\Modules\BackupRestore\Services\BackupService;
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

        return view('modules.backup-restore.index', [
            'history' => $history,
            'destinationConfigured' => $backups->isConfigured(),
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
}
