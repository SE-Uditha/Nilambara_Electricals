@extends('layouts.app')

@section('title', $invoice->invoice_number.' · '.config('app.name'))

@section('content')
    @php($isDraft = $invoice->status === App\Enums\DocumentStatus::Draft)
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('invoices.invoice_details') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $invoice->invoice_number }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $invoice->invoice_date->format(config('business.date_format')) }} · {{ __('invoices.'.$invoice->status->value) }} · {{ __('invoices.'.$balance['payment_status']) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('print', $invoice)<a href="{{ route('invoices.print', $invoice) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('invoices.print') }}</a><a href="{{ route('invoices.duplicate', $invoice) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('invoices.duplicate_print') }}</a>@endcan
            @can('export', $invoice)<a href="{{ route('invoices.pdf', $invoice) }}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('invoices.export_pdf') }}</a>@endcan
            @can('updateDraft', $invoice)<a href="{{ route('invoices.edit', $invoice) }}" class="rounded-xl border border-teal-700 px-3 py-2 text-xs font-semibold text-teal-800 hover:bg-teal-50">{{ __('invoices.edit_draft') }}</a>@endcan
        </div>
    </div>

    @if (session('status'))<div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>@endif
    @if ($errors->any())<div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-bold text-slate-900">{{ __('invoices.customer_details') }}</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="form-label">{{ __('invoices.customer') }}</dt><dd class="font-semibold text-slate-900">{{ data_get($document, 'customer.name', $invoice->customer->display_name) }}</dd></div>
                    <div><dt class="form-label">{{ __('invoices.customer_id') }}</dt><dd>{{ $invoice->customer_id }}</dd></div>
                    <div><dt class="form-label">{{ __('invoices.due_date') }}</dt><dd>{{ $invoice->due_date?->format(config('business.date_format')) ?: '—' }}</dd></div>
                    <div><dt class="form-label">{{ __('invoices.address') }}</dt><dd>{{ data_get($document, 'customer.address', $invoice->customer->address) ?: '—' }}</dd></div>
                    <div><dt class="form-label">{{ __('invoices.mobile') }}</dt><dd>{{ data_get($document, 'customer.mobile_number', $invoice->customer->mobile_number) ?: '—' }}</dd></div>
                </dl>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-bold text-slate-900">{{ __('invoices.job_billing') }}</h2>
                <div class="mt-4 space-y-4">
                    @foreach ($invoice->invoiceJobs as $link)
                        <article class="overflow-hidden rounded-xl border border-slate-200">
                            <div class="flex flex-wrap justify-between gap-3 bg-slate-50 px-4 py-3">
                                <div>
                                    <p class="font-semibold text-slate-900">
                                        @can('jobs.view')<a class="hover:text-teal-800 hover:underline" href="{{ route('repair-jobs.show', $link->repair_job_id) }}">{{ data_get($link->job_snapshot, 'job_number') }}</a>@else{{ data_get($link->job_snapshot, 'job_number') }}@endcan
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500">{{ data_get($link->job_snapshot, 'item_name') }} · {{ data_get($link->job_snapshot, 'repair_description') }}</p>
                                </div>
                                <p class="font-bold text-slate-900">{{ config('business.currency') }} {{ $link->job_amount }}</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-left text-xs">
                                    <thead class="text-[10px] uppercase text-slate-500"><tr><th class="px-4 py-2">{{ __('invoices.description_label') }}</th><th class="px-4 py-2">{{ __('invoices.item') }}</th><th class="px-4 py-2 text-right">{{ __('invoices.job_total') }}</th></tr></thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach ($link->invoiceItems as $item)
                                            <tr><td class="px-4 py-2">{{ $item->description }}</td><td class="px-4 py-2">{{ __('invoices.'.$item->item_type) }}</td><td class="px-4 py-2 text-right">{{ config('business.currency') }} {{ $item->line_total }}</td></tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if (data_get($link->job_snapshot, 'quotation_number'))
                                <p class="px-4 py-2 text-[11px] text-teal-800">
                                    {{ __('invoices.approved_quote') }}:
                                    @if (auth()->user()->can('quotations.view'))
                                        <a class="font-semibold hover:underline" href="{{ route('quotations.show', data_get($link->job_snapshot, 'quotation_id')) }}">{{ data_get($link->job_snapshot, 'quotation_number') }}</a>
                                    @else
                                        {{ data_get($link->job_snapshot, 'quotation_number') }}
                                    @endif
                                    · {{ __('invoices.approved_quote_estimate_notice') }}
                                </p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-bold text-slate-900">{{ __('invoices.collection_details') }}</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="form-label">{{ __('invoices.collected_by') }}</dt><dd>{{ $invoice->collected_by ?: '—' }}</dd></div>
                    <div><dt class="form-label">{{ __('invoices.vehicle_number') }}</dt><dd>{{ $invoice->vehicle_number ?: '—' }}</dd></div>
                    <div><dt class="form-label">{{ __('invoices.delivery_date') }}</dt><dd>{{ $invoice->delivery_at?->format(config('business.date_format').' '.config('business.time_format')) ?: '—' }}</dd></div>
                    <div><dt class="form-label">{{ __('invoices.delivery_information') }}</dt><dd>{{ $invoice->delivery_information ?: '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="form-label">{{ __('invoices.special_notes') }}</dt><dd class="whitespace-pre-line">{{ $invoice->special_notes ?: '—' }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-slate-500">{{ __('invoices.no_delivery_status_change') }}</p>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-bold text-slate-900">{{ __('invoices.print_history') }}</h2>
                <ol class="mt-4 space-y-3">
                    @forelse ($invoice->documentPrintHistories as $print)
                        <li class="border-l-2 border-slate-200 pl-3">
                            <p class="text-xs font-semibold text-slate-900">{{ $print->is_duplicate ? $print->duplicate_label : __('invoices.original_copy') }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">{{ $print->printedByUser->name }} · {{ $print->printed_at->format(config('business.date_format').' '.config('business.time_format')) }}</p>
                        </li>
                    @empty
                        <li class="text-xs text-slate-500">{{ __('invoices.no_print_history') }}</li>
                    @endforelse
                </ol>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-bold text-slate-900">{{ __('invoices.invoice_summary') }}</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt>{{ __('invoices.subtotal') }}</dt><dd>{{ config('business.currency') }} {{ $invoice->subtotal }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>{{ __('invoices.discount') }}</dt><dd>{{ config('business.currency') }} {{ $invoice->discount_amount }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-slate-100 pt-3 font-bold"><dt>{{ __('invoices.invoice_total') }}</dt><dd>{{ config('business.currency') }} {{ $invoice->total }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>{{ __('invoices.advance_paid') }}</dt><dd>{{ config('business.currency') }} {{ $balance['advance_paid'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>{{ __('invoices.previous_payments') }}</dt><dd>{{ config('business.currency') }} {{ $balance['previous_payments'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>{{ __('invoices.current_payment') }}</dt><dd>{{ config('business.currency') }} {{ $balance['current_payment'] }}</dd></div>
                    <div class="flex justify-between gap-3 border-t border-slate-100 pt-3 font-bold text-teal-900"><dt>{{ __('invoices.outstanding_balance') }}</dt><dd>{{ config('business.currency') }} {{ $balance['outstanding'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>{{ __('invoices.payment_terms') }}</dt><dd>{{ __('invoices.'.$invoice->payment_terms->value) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>{{ __('invoices.payment_method') }}</dt><dd>{{ $invoice->payment_method ? __('invoices.'.$invoice->payment_method->value) : '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>{{ __('invoices.payment_status') }}</dt><dd>{{ __('invoices.'.$balance['payment_status']) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt>{{ __('invoices.cashier_name') }}</dt><dd>{{ $invoice->cashierEmployee->name }}</dd></div>
                </dl>
                <p class="mt-4 rounded-lg bg-amber-50 p-3 text-xs leading-5 text-amber-900">{{ __('invoices.pending_step_8') }}</p>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-bold text-slate-900">{{ __('invoices.invoice_history') }}</h2>
                <ol class="mt-4 space-y-3">
                    @forelse ($invoice->history as $event)
                        <li class="border-l-2 border-teal-200 pl-3">
                            <p class="text-xs font-semibold text-slate-900">{{ str($event->event_type)->replace('_', ' ')->headline() }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">{{ $event->actor->name }} · {{ $event->event_at->format(config('business.date_format').' '.config('business.time_format')) }}</p>
                        </li>
                    @empty
                        <li class="text-xs text-slate-500">{{ __('invoices.no_history') }}</li>
                    @endforelse
                </ol>
            </section>
        </aside>
    </div>

    <div class="mt-5 flex flex-wrap justify-end gap-2">
        @can('issue', $invoice)
            <form method="POST" action="{{ route('invoices.issue', $invoice) }}" data-confirm="{{ __('invoices.issue_confirmation') }}">@csrf<button class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('invoices.issue_invoice') }}</button></form>
        @endcan
        @can('cancel', $invoice)
            <form method="POST" action="{{ route('invoices.cancel', $invoice) }}" class="flex flex-wrap items-end gap-2" onsubmit="return window.confirm(@js(__('invoices.cancel_confirmation')));">
                @csrf
                <div><label for="reason" class="form-label">{{ __('invoices.cancellation_reason') }}</label><input id="reason" name="reason" required minlength="10" maxlength="1000" class="form-input" placeholder="{{ __('invoices.cancellation_reason') }}"></div>
                <button class="rounded-xl border border-red-300 px-4 py-2.5 text-xs font-semibold text-red-700 hover:bg-red-50">{{ __('invoices.cancel_invoice') }}</button>
            </form>
        @endcan
    </div>
@endsection
