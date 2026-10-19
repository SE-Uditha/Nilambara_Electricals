@extends('layouts.app')

@section('title', __('ui.repair_receipts'))

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.workshop') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.repair_receipts') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('ui.repair_receipts_description') }}</p>
        </div>
        @can('receipts.create')
            <a href="{{ route('repair-receipts.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.new_repair_receipt') }}</a>
        @endcan
    </div>

    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('repair-receipts.index') }}" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-3 xl:grid-cols-6">
        <input name="q" value="{{ request('q') }}" class="form-input xl:col-span-2" placeholder="{{ __('ui.receipt_search_placeholder') }}" aria-label="{{ __('ui.search_receipts') }}">
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-input" aria-label="{{ __('ui.date_from') }}">
        <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-input" aria-label="{{ __('ui.date_to') }}">
        <select name="job_type" class="form-input" aria-label="{{ __('ui.job_type') }}">
            <option value="">{{ __('ui.all_job_types') }}</option>
            @foreach (['normal', 'special', 'urgent'] as $type)
                <option value="{{ $type }}" @selected(request('job_type') === $type)>{{ __('ui.job_type_'.$type) }}</option>
            @endforeach
        </select>
        <select name="status" class="form-input" aria-label="{{ __('ui.job_status') }}">
            <option value="">{{ __('ui.all_statuses') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->value }}</option>
            @endforeach
        </select>
        <select name="received_by" class="form-input" aria-label="{{ __('ui.received_by') }}">
            <option value="">{{ __('ui.all_employees') }}</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}" @selected((string) request('received_by') === (string) $employee->id)>{{ $employee->name }}</option>
            @endforeach
        </select>
        <button class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.filter') }}</button>
        @if (request()->hasAny(['q', 'date_from', 'date_to', 'job_type', 'status', 'received_by']))
            <a href="{{ route('repair-receipts.index') }}" class="rounded-xl px-3 py-2 text-center text-xs font-semibold text-slate-500 hover:bg-slate-50">{{ __('ui.clear') }}</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1200px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3">{{ __('ui.job_reference') }}</th>
                        <th class="px-4 py-3">{{ __('ui.manual_job_number') }}</th>
                        <th class="px-4 py-3">{{ __('ui.received_date') }}</th>
                        <th class="px-4 py-3">{{ __('ui.customer') }}</th>
                        <th class="px-4 py-3">{{ __('ui.mobile_number') }}</th>
                        <th class="px-4 py-3">{{ __('ui.item_name') }}</th>
                        <th class="px-4 py-3">{{ __('ui.brand_model') }}</th>
                        <th class="px-4 py-3">{{ __('ui.job_type') }}</th>
                        <th class="px-4 py-3">{{ __('ui.job_status') }}</th>
                        <th class="px-4 py-3">{{ __('ui.received_by') }}</th>
                        <th class="px-4 py-3">{{ __('ui.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($receipts as $receipt)
                        @php($job = $receipt->repairJob)
                        <tr class="text-slate-700">
                            <td class="px-4 py-4 font-mono font-semibold text-teal-800">{{ $job->automatic_number }}</td>
                            <td class="px-4 py-4 font-mono">{{ $job->job_number }}</td>
                            <td class="px-4 py-4">{{ $job->received_at->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }}</td>
                            <td class="px-4 py-4">{{ $job->customer->display_name }}</td>
                            <td class="px-4 py-4">{{ $job->customer->mobile_number }}</td>
                            <td class="px-4 py-4">{{ $job->item_name }}</td>
                            <td class="px-4 py-4">{{ trim(($job->brand ?? '').' '.($job->model ?? '')) ?: '—' }}</td>
                            <td class="px-4 py-4">{{ __('ui.job_type_'.$job->job_type) }}</td>
                            <td class="px-4 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-700">{{ $job->status->value }}</span></td>
                            <td class="px-4 py-4">{{ $job->receivedByEmployee->name }}</td>
                            <td class="px-4 py-4">
                                <div class="flex gap-3">
                                    <a href="{{ route('repair-receipts.show', $receipt) }}" class="font-semibold text-teal-700 hover:text-teal-900">{{ __('ui.view') }}</a>
                                    @can('receipts.print')
                                        <a href="{{ route('repair-receipts.print', $receipt) }}" class="font-semibold text-slate-600 hover:text-slate-900">{{ __('ui.print') }}</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-5 py-14 text-center"><p class="font-semibold text-slate-700">{{ __('ui.no_repair_receipts') }}</p><p class="mt-1 text-xs text-slate-400">{{ __('ui.no_repair_receipts_description') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
            <p class="text-xs text-slate-500">{{ __('ui.showing_records', ['count' => $receipts->count(), 'total' => $receipts->total()]) }}</p>
            @if ($receipts->hasPages()){{ $receipts->links() }}@endif
        </div>
    </div>
@endsection
