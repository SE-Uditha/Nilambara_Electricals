@extends('layouts.app')

@section('title', __('backups.title'))

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('backups.title') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('backups.description') }}</p>
    </div>
    @if (session('status'))<p class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>@endif
    @if ($missedBackup)<p role="alert" class="mb-4 rounded-lg bg-amber-50 p-3 text-sm">No recent completed scheduled backup was recorded. Check scheduler execution and backup failures.</p>@endif
    @if ($cleanupFailed)<p role="alert" class="mb-4 rounded-lg bg-amber-50 p-3 text-sm">A retention cleanup failure was recorded in the last seven days. Review the audit history.</p>@endif
    <p class="mb-4 text-sm">Google Drive: {{ $cloudConfigured ? 'Configured' : 'Not configured — account and folder details can be added later.' }}</p>
    @can('backups.configure')
    <form method="POST" action="{{ route('backups.configure') }}" class="mb-6 grid gap-3 rounded-xl border bg-white p-4 sm:grid-cols-2">
        @csrf
        <div class="sm:col-span-2">
            <label class="mb-2 block font-semibold" for="backup-destination">Custom backup folder</label>
            <div class="flex flex-wrap items-center gap-2"><input class="form-input flex-1" name="root" value="{{ old('root') }}" placeholder="Example: D:\NilambaraBackups" maxlength="1000" @disabled(!old('root')) id="backup-destination"><button type="button" id="browse-backup-folder" class="rounded-lg border px-4 py-2 text-teal-700">Browse folders</button></div>
            <label class="mt-2 block text-sm"><input type="checkbox" id="change-backup-destination" @checked(old('root'))> Change backup location</label>
            <p class="mt-2 text-xs text-slate-500">Choose a folder on the PC running this system, or enter its full path. A new subfolder is created on the first backup. Existing backups stay in their original locations.</p>
            @error('root')<p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <label>Daily time (Asia/Colombo)<input class="form-input" type="time" name="schedule_time" value="{{ config('backup.schedule_time') }}" required></label>
        <label>Retention days<input class="form-input" type="number" name="retention_days" min="1" max="3650" value="{{ config('backup.retention_days') }}" required></label>
        <label>Maximum scheduled versions<input class="form-input" type="number" name="maximum_backups" min="1" max="1000" value="{{ config('backup.maximum_backups') }}" required></label>
        <button class="rounded bg-teal-700 px-4 py-2 text-white">Save backup policy</button>
    </form>
    @include('modules.backup-restore.folder-picker')
    <p class="mb-4 text-xs">Restore uses the protected <code>php artisan backup:restore ID</code> command with administrator password, exact database confirmation and a safety backup. Selecting a history row never starts a restore. Manual backups are protected from automatic retention cleanup.</p>
    @endcan
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
    <section class="mt-6 space-y-3">@foreach ($history as $backup)<div class="flex flex-wrap items-center gap-3 rounded border bg-white p-3 text-xs">
        <span>Backup #{{ $backup->id }} · {{ $backup->file_size ?? '—' }} bytes · {{ $backup->verification_status }} · Google Drive: {{ $backup->cloud_status }} · {{ $backup->is_protected ? 'Protected manual copy' : 'Scheduled retention' }}</span>
        @if (! $backup->deleted_at)<form method="POST" action="{{ route('backups.verify', $backup) }}">@csrf<button class="text-teal-700 underline">Verify file</button></form>@else<span>File removed by retention; history preserved.</span>@endif
    </div>@endforeach</section>
@endsection
