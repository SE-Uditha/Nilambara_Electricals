@extends('layouts.app')

@section('title', __('ui.quotations').' · '.config('app.name'))

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.repair_management') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.quotations') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('ui.quotations_description') }}</p>
        </div>
        @can('create', App\Modules\Quotations\Models\Quotation::class)
            <a href="{{ route('quotations.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.new_quotation') }}</a>
        @endcan
    </div>

    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>
    @endif

    <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2"><label for="q" class="form-label">{{ __('ui.search') }}</label><input id="q" name="q" value="{{ request('q') }}" class="form-input" placeholder="{{ __('ui.quotation_search_hint') }}"></div>
        <div><label for="date_from" class="form-label">{{ __('ui.date_from') }}</label><input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}" class="form-input"></div>
        <div><label for="date_to" class="form-label">{{ __('ui.date_to') }}</label><input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}" class="form-input"></div>
        <div><label for="approval_status" class="form-label">{{ __('ui.customer_approval_status') }}</label><select id="approval_status" name="approval_status" class="form-input"><option value="">{{ __('ui.all_statuses') }}</option>@foreach ($approvalStatuses as $status)<option value="{{ $status->value }}" @selected(request('approval_status') === $status->value)>{{ __('ui.approval_'.$status->value) }}</option>@endforeach</select></div>
        <div><label for="preparation_status" class="form-label">{{ __('ui.preparation_status') }}</label><select id="preparation_status" name="preparation_status" class="form-input"><option value="">{{ __('ui.all_statuses') }}</option>@foreach ($preparationStatuses as $status)<option value="{{ $status->value }}" @selected(request('preparation_status') === $status->value)>{{ __('ui.preparation_'.$status->value) }}</option>@endforeach</select></div>
        <div class="flex items-end gap-2"><button class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.search') }}</button><a href="{{ route('quotations.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">{{ __('ui.clear') }}</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    <tr><th class="px-4 py-3">{{ __('ui.quotation_number') }}</th><th class="px-4 py-3">{{ __('ui.quotation_date') }}</th><th class="px-4 py-3">{{ __('ui.job_number') }}</th><th class="px-4 py-3">{{ __('ui.customer') }}</th><th class="px-4 py-3">{{ __('ui.item_name') }}</th><th class="px-4 py-3">{{ __('ui.quotation_total') }}</th><th class="px-4 py-3">{{ __('ui.valid_until') }}</th><th class="px-4 py-3">{{ __('ui.preparation_status') }}</th><th class="px-4 py-3">{{ __('ui.customer_approval_status') }}</th><th class="px-4 py-3">{{ __('ui.prepared_by') }}</th><th class="px-4 py-3">{{ __('ui.actions') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse ($quotations as $quotation)
                        <tr class="hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-4 py-3 font-mono font-semibold text-teal-800"><a href="{{ route('quotations.show', $quotation) }}" class="hover:underline">{{ $quotation->quotation_number }}</a>@if($quotation->revision > 1)<span class="ml-1 text-slate-400">v{{ $quotation->revision }}</span>@endif</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $quotation->quotation_date->format(config('business.date_format')) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $quotation->repairJob->automatic_number }} / {{ $quotation->repairJob->job_number }}</td>
                            <td class="px-4 py-3">{{ $quotation->customer->display_name }}</td>
                            <td class="px-4 py-3">{{ $quotation->repairJob->item_name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold">LKR {{ \Brick\Math\BigDecimal::of($quotation->total)->toScale(2) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $quotation->valid_until?->format(config('business.date_format')) ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ __('ui.preparation_'.$quotation->preparation_status->value) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ __('ui.approval_'.$quotation->approval_status->value) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $quotation->createdByUser?->name ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3"><div class="flex items-center gap-3"><a class="font-semibold text-teal-700 hover:underline" href="{{ route('quotations.show', $quotation) }}">{{ __('ui.view') }}</a>@can('print', $quotation)<a class="font-semibold text-slate-600 hover:underline" href="{{ route('quotations.print', $quotation) }}">{{ __('ui.print') }}</a>@endcan</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-4 py-10 text-center text-sm text-slate-500">{{ __('ui.no_quotations_found') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-4 py-3">{{ $quotations->links() }}</div>
    </div>
@endsection
