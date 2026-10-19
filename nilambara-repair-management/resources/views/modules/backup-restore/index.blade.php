@extends('layouts.app')

@section('title', __('backups.title'))

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('backups.title') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('backups.description') }}</p>
    </div>
    @if (session('status'))<p class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>@endif
    @error('backup')<p class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>@enderror
    <div class="mb-6 rounded-xl border {{ $destinationConfigured ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-4 text-sm">
        <p class="font-semibold">{{ $destinationConfigured ? __('backups.destination_ready') : __('backups.destination_unavailable') }}</p>
        <p class="mt-1 text-xs">{{ __('backups.policy') }}</p>
    </div>
    @can('backups.create')
        <form method="POST" action="{{ route('backups.create') }}" class="mb-6">
            @csrf
            <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white" @disabled(! $destinationConfigured)>{{ __('backups.create_now') }}</button>
        </form>
    @endcan
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600"><tr><th class="px-4 py-3">{{ __('backups.date') }}</th><th class="px-4 py-3">{{ __('backups.type') }}</th><th class="px-4 py-3">{{ __('backups.status') }}</th><th class="px-4 py-3">{{ __('backups.verified') }}</th><th class="px-4 py-3">{{ __('backups.checksum') }}</th><th class="px-4 py-3">{{ __('backups.actions') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($history as $backup)
                    <tr><td class="whitespace-nowrap px-4 py-3">{{ $backup->backup_at->timezone(config('backup.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }}</td><td class="px-4 py-3">{{ __('backups.'.$backup->backup_type) }}</td><td class="px-4 py-3">{{ __('backups.'.$backup->status) }}</td><td class="px-4 py-3">{{ $backup->verified_at?->timezone(config('backup.timezone'))->format(config('business.date_format').' '.config('business.time_format')) ?: '—' }}</td><td class="px-4 py-3 font-mono">{{ $backup->checksum_sha256 ? substr($backup->checksum_sha256, 0, 12).'…' : '—' }}</td><td class="px-4 py-3">@if ($backup->status === 'completed' && $backup->verified_at && $destinationConfigured)<a class="font-semibold text-teal-700 hover:underline" href="{{ route('backups.download', $backup) }}">{{ __('backups.download') }}</a>@else—@endif</td></tr>
                @empty
                    <tr><td class="px-4 py-10 text-center text-slate-500" colspan="6">{{ __('backups.no_backups') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $history->links() }}</div>
@endsection
