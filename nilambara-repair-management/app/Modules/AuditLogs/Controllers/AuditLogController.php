<?php

namespace App\Modules\AuditLogs\Controllers;

use App\Modules\AuditLogs\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'action' => ['nullable', 'string', 'max:100'],
            'entity_type' => ['nullable', 'string', 'max:150'],
            'user_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name,username')
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('occurred_at', '<=', $date))
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', 'like', '%'.$action.'%'))
            ->when($filters['entity_type'] ?? null, fn ($query, string $type) => $query->where('entity_type', 'like', '%'.$type.'%'))
            ->when($filters['user_id'] ?? null, fn ($query, int $userId) => $query->where('user_id', $userId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('modules.audit-logs.index', compact('filters', 'logs'));
    }
}
