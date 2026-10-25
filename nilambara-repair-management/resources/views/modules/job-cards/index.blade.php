@extends('layouts.app')

@section('title', __('ui.job_cards'))

@section('content')
    <div class="mb-7">
        <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.workshop') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.job_cards') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('ui.job_cards_description') }}</p>
    </div>
    <form method="GET" action="{{ route('job-cards.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div class="min-w-[240px] flex-1">
            <label for="q" class="form-label">{{ __('ui.search') }}</label>
            <input id="q" type="search" name="q" value="{{ $search }}" maxlength="120" class="form-input" placeholder="{{ __('ui.job_reference') }}, {{ __('ui.manual_job_number') }}, {{ __('ui.customer') }}, {{ __('ui.item_name') }}, {{ __('ui.technician') }}">
        </div>
        <div>
            <label for="status" class="form-label">{{ __('ui.job_status') }}</label>
            <select id="status" name="status" class="form-input">
                <option value="">{{ __('ui.all_statuses') }}</option>
                @foreach (\App\Enums\JobStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case->value)>{{ $case->value }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.filter') }}</button>
        @if ($search !== '' || $status !== '')<a href="{{ route('job-cards.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">{{ __('ui.clear') }}</a>@endif
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    <tr><th class="px-5 py-3">{{ __('ui.job_reference') }}</th><th class="px-5 py-3">{{ __('ui.manual_job_number') }}</th><th class="px-5 py-3">{{ __('ui.customer') }}</th><th class="px-5 py-3">{{ __('ui.item_name') }}</th><th class="px-5 py-3">{{ __('ui.technician') }}</th><th class="px-5 py-3">{{ __('ui.job_status') }}</th><th class="px-5 py-3">{{ __('ui.actions') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($jobs as $job)
                        <tr class="text-slate-700">
                            <td class="px-5 py-4 font-mono font-semibold text-teal-800">{{ $job->automatic_number }}</td>
                            <td class="px-5 py-4 font-mono">{{ $job->job_number }}</td>
                            <td class="px-5 py-4">{{ $job->customer->display_name }}</td>
                            <td class="px-5 py-4">{{ $job->item_name }}</td>
                            <td class="px-5 py-4">{{ $job->technician?->name ?: '—' }}</td>
                            <td class="px-5 py-4">{{ $job->status->value }}</td>
                            <td class="px-5 py-4">
                                <div class="flex gap-3">
                                    @can('job-cards.print')<a href="{{ route('job-cards.print', $job) }}" class="font-semibold text-teal-700 hover:text-teal-900">{{ __('ui.preview_print') }}</a>@endcan
                                    @can('jobs.view')<a href="{{ route('repair-jobs.show', $job) }}" class="font-semibold text-slate-600 hover:text-slate-900">{{ __('ui.view_job') }}</a>@endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-14 text-center"><p class="font-semibold text-slate-700">{{ __('ui.no_job_cards') }}</p><p class="mt-1 text-xs text-slate-400">{{ __('ui.no_job_cards_description') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
            <p class="text-xs text-slate-500">{{ __('ui.showing_records', ['count' => $jobs->count(), 'total' => $jobs->total()]) }}</p>
            @if ($jobs->hasPages()){{ $jobs->links() }}@endif
        </div>
    </div>
@endsection
