@extends('layouts.app')

@section('title', __('ui.job_status_tracking'))

@section('content')
    <div class="mb-7">
        <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.workshop') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.job_status_tracking') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('ui.job_status_search_description') }}</p>
    </div>
    <form method="GET" action="{{ route('repair-jobs.status-search') }}" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-[minmax(0,1fr)_220px_auto_auto]">
        <input name="q" value="{{ request('q') }}" class="form-input" placeholder="{{ __('ui.job_search_placeholder') }}" aria-label="{{ __('ui.search_jobs') }}">
        <select name="status" class="form-input" aria-label="{{ __('ui.job_status') }}">
            <option value="">{{ __('ui.all_statuses') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->value }}</option>
            @endforeach
        </select>
        <button class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.search') }}</button>
        @if (request()->hasAny(['q', 'status']))<a href="{{ route('repair-jobs.status-search') }}" class="rounded-xl px-3 py-2 text-center text-xs font-semibold text-slate-500 hover:bg-slate-50">{{ __('ui.clear') }}</a>@endif
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3">{{ __('ui.manual_job_number') }}</th>
                        <th class="px-4 py-3">{{ __('ui.received_date') }}</th>
                        <th class="px-4 py-3">{{ __('ui.customer') }}</th>
                        <th class="px-4 py-3">{{ __('ui.item_name') }}</th>
                        <th class="px-4 py-3">{{ __('ui.repair_description') }}</th>
                        <th class="px-4 py-3">{{ __('ui.job_status') }}</th>
                        <th class="px-4 py-3">{{ __('ui.special_notes') }}</th>
                        <th class="px-4 py-3">{{ __('ui.total_amount') }}</th>
                        <th class="px-4 py-3">{{ __('ui.advance_paid') }}</th>
                        <th class="px-4 py-3">{{ __('ui.outstanding_balance') }}</th>
                        <th class="px-4 py-3">{{ __('ui.repair_completed_date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($jobs as $job)
                        <tr class="text-slate-700">
                            <td class="px-4 py-4 font-mono font-semibold"><a href="{{ route('repair-jobs.show', $job) }}" class="text-teal-800 hover:underline">{{ $job->job_number }}</a><span class="mt-1 block text-[10px] text-slate-400">{{ $job->automatic_number }}</span></td>
                            <td class="px-4 py-4">{{ $job->received_at->timezone(config('business.timezone'))->format(config('business.date_format')) }}</td>
                            <td class="px-4 py-4">{{ $job->customer->display_name }}<span class="mt-1 block text-[10px] text-slate-400">{{ $job->customer->mobile_number }}</span></td>
                            <td class="px-4 py-4">{{ $job->item_name }}</td>
                            <td class="max-w-48 truncate px-4 py-4" title="{{ $job->repair_description }}">{{ $job->repair_description ?: '—' }}</td>
                            <td class="px-4 py-4">
                                @can('updateStatus', $job)
                                    <form method="POST" action="{{ route('repair-jobs.update-status', $job) }}" class="flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="return_to" value="status-search">
                                        <select name="status" class="form-input min-w-44 py-1 text-xs" aria-label="{{ __('ui.job_status') }}">
                                            @foreach ($statuses as $status)
                                                @if (($status !== \App\Enums\JobStatus::Cancelled || auth()->user()->can('jobs.cancel')) && ($status !== \App\Enums\JobStatus::RepairCompleted || auth()->user()->can('jobs.complete')))
                                                    <option value="{{ $status->value }}" @selected($job->status === $status)>{{ $status->value }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <button class="rounded-lg bg-teal-700 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-teal-800">{{ __('ui.save_status') }}</button>
                                    </form>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-700">{{ $job->status->value }}</span>
                                @endcan
                            </td>
                            <td class="max-w-40 truncate px-4 py-4" title="{{ $job->special_notes }}">{{ $job->special_notes ?: '—' }}</td>
                            <td class="px-4 py-4">{{ (float) $job->total_job_amount > 0 ? config('business.currency').' '.$job->total_job_amount : __('ui.amount_unavailable') }}</td>
                            <td class="px-4 py-4">{{ config('business.currency') }} {{ number_format((float) ($job->advance_paid ?? 0), 2) }}</td>
                            <td class="px-4 py-4">{{ __('ui.amount_unavailable') }}</td>
                            <td class="px-4 py-4">{{ $job->completed_at?->timezone(config('business.timezone'))->format(config('business.date_format')) ?: '—' }}</td>
                        </tr>
                        @if (in_array($job->status, [\App\Enums\JobStatus::CustomerContactFailed, \App\Enums\JobStatus::CustomerContactFailedAfterReady], true))
                            <tr class="bg-amber-50/50">
                                <td colspan="11" class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-slate-700">
                                        <span class="font-semibold text-amber-800">{{ __('ui.times_contacted', ['count' => $job->contactAttempts->count()]) }}</span>
                                        @if ($job->contactAttempts->isNotEmpty())
                                            <span>{{ __('ui.last_contacted') }}: {{ $job->contactAttempts->last()->contacted_on->format(config('business.date_format')) }}</span>
                                        @endif
                                        @can('updateStatus', $job)
                                            <form method="POST" action="{{ route('repair-jobs.contact-attempts.store', $job) }}" class="flex flex-wrap items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="return_to" value="status-search">
                                                <input type="date" name="contacted_on" required max="{{ now()->timezone(config('business.timezone'))->toDateString() }}" value="{{ now()->timezone(config('business.timezone'))->toDateString() }}" class="form-input w-40 py-1 text-xs" aria-label="{{ __('ui.date_contacted') }}">
                                                <input type="text" name="notes" maxlength="1000" placeholder="{{ __('ui.contact_notes') }}" class="form-input w-56 py-1 text-xs" aria-label="{{ __('ui.contact_notes') }}">
                                                <button class="rounded-lg bg-teal-700 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-teal-800">{{ __('ui.add_contact_attempt') }}</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="11" class="px-5 py-14 text-center"><p class="font-semibold text-slate-700">{{ __('ui.no_matching_jobs') }}</p><p class="mt-1 text-xs text-slate-400">{{ __('ui.no_matching_jobs_description') }}</p></td></tr>
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
