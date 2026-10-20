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
            'module' => ['nullable', 'string', 'max:64'],
            'record' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name,username', 'user.employee:id,user_id,name')
            ->when($filters['module'] ?? null, fn ($query, $module) => $query->where('action', 'like', $module.'.%'))
            ->when($filters['record'] ?? null, fn ($query, $id) => $query->where('entity_id', $id))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where(fn ($q) => $q->where('action', 'like', '%'.$term.'%')->orWhere('new_values', 'like', '%'.$term.'%')->orWhereHas('user', fn ($u) => $u->where('username', 'like', '%'.$term.'%'))))
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
