@extends('layouts.app')

@section('title', __('audit.title'))

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('audit.title') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('audit.description') }}</p>
    </div>
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <label class="text-xs text-slate-600">{{ __('audit.date_from') }}<input class="form-input mt-1" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
        <label class="text-xs text-slate-600">{{ __('audit.date_to') }}<input class="form-input mt-1" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
        <label class="text-xs text-slate-600">{{ __('audit.action') }}<input class="form-input mt-1" name="action" value="{{ $filters['action'] ?? '' }}" maxlength="100"></label>
        <label class="text-xs text-slate-600">{{ __('audit.entity') }}<input class="form-input mt-1" name="entity_type" value="{{ $filters['entity_type'] ?? '' }}" maxlength="150"></label>
        <label class="text-xs text-slate-600">{{ __('audit.user_id') }}<input class="form-input mt-1" type="number" min="1" name="user_id" value="{{ $filters['user_id'] ?? '' }}"></label>
        <button class="rounded-lg bg-teal-700 px-4 py-2 text-xs font-semibold text-white">{{ __('audit.filter') }}</button>
    </form>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600"><tr><th class="px-4 py-3">{{ __('audit.timestamp') }}</th><th class="px-4 py-3">{{ __('audit.user') }}</th><th class="px-4 py-3">{{ __('audit.action') }}</th><th class="px-4 py-3">{{ __('audit.entity') }}</th><th class="px-4 py-3">{{ __('audit.changes') }}</th><th class="px-4 py-3">{{ __('audit.ip') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr class="align-top"><td class="whitespace-nowrap px-4 py-3">{{ $log->occurred_at->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }}</td><td class="px-4 py-3">{{ $log->user?->name ?? __('audit.system') }}</td><td class="px-4 py-3">{{ $log->action }}</td><td class="px-4 py-3">{{ $log->entity_type }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</td><td class="max-w-xl px-4 py-3"><details><summary class="cursor-pointer text-teal-700">{{ __('audit.view_changes') }}</summary><pre class="mt-2 whitespace-pre-wrap break-all">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details></td><td class="px-4 py-3">{{ $log->ip_address }}</td></tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">{{ __('audit.no_logs') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
@endsection
