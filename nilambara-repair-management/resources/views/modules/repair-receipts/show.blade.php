@extends('layouts.app')

@section('title', __('ui.repair_receipt'))

@section('content')
    @php($snapshot = $receipt->document_snapshot ?? [])
    @php($job = $receipt->repairJob)
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.repair_receipts') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.repair_receipt') }} · {{ $receipt->receipt_number }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('ui.receipt_detail_description') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('repair-receipts.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">{{ __('ui.back_to_receipts') }}</a>
            @can('jobs.view')
                <a href="{{ route('repair-jobs.show', $job) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.view_repair_job') }}</a>
            @endcan
            @can('receipts.print')
                <a href="{{ route('repair-receipts.print', $receipt) }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.print_receipt') }}</a>
            @endcan
            @can('receipts.reprint')
                <a href="{{ route('repair-receipts.duplicate', $receipt) }}" class="rounded-xl border border-teal-200 bg-teal-50 px-4 py-2.5 text-xs font-semibold text-teal-800 hover:bg-teal-100">{{ __('ui.reprint_duplicate') }}</a>
            @endcan
        </div>
    </div>
    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <h2 class="mb-5 text-sm font-bold text-slate-900">{{ __('ui.customer_information') }}</h2>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="form-label">{{ __('ui.customer_name') }}</dt><dd class="font-medium text-slate-800">{{ data_get($snapshot, 'customer.name', '—') }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.customer_id') }}</dt><dd class="font-mono text-slate-700">{{ data_get($snapshot, 'customer.customer_code', '—') }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.contact_person') }}</dt><dd class="text-slate-700">{{ data_get($snapshot, 'customer.contact_person') ?: '—' }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.mobile_number') }}</dt><dd class="text-slate-700">{{ data_get($snapshot, 'customer.mobile_number', '—') }}</dd></div>
                    <div class="sm:col-span-2"><dt class="form-label">{{ __('ui.address') }}</dt><dd class="whitespace-pre-line text-slate-700">{{ data_get($snapshot, 'customer.address', '—') }}</dd></div>
                </dl>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <h2 class="mb-5 text-sm font-bold text-slate-900">{{ __('ui.appliance_information') }}</h2>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="form-label">{{ __('ui.item_name') }}</dt><dd class="font-medium text-slate-800">{{ data_get($snapshot, 'item.name', '—') }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.brand_model') }}</dt><dd class="text-slate-700">{{ trim((string) data_get($snapshot, 'item.brand').' '.(string) data_get($snapshot, 'item.model')) ?: '—' }}</dd></div>
                    @foreach ([
                        'description' => __('ui.item_description'),
                        'reported_fault' => __('ui.reported_fault'),
                        'accessories_received' => __('ui.accessories_received'),
                        'physical_condition' => __('ui.physical_condition'),
                        'visible_damage' => __('ui.visible_damage'),
                    ] as $key => $label)
                        <div class="sm:col-span-2"><dt class="form-label">{{ $label }}</dt><dd class="whitespace-pre-line text-slate-700">{{ data_get($snapshot, 'item.'.$key) ?: '—' }}</dd></div>
                    @endforeach
                </dl>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <h2 class="mb-5 text-sm font-bold text-slate-900">{{ __('ui.receipt_notes') }}</h2>
                <p class="whitespace-pre-line text-sm text-slate-700">{{ data_get($snapshot, 'special_notes') ?: '—' }}</p>
            </section>
        </div>
        <aside class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-bold text-slate-900">{{ __('ui.job_information') }}</h2>
                <dl class="space-y-3 text-sm">
                    <div><dt class="form-label">{{ __('ui.job_reference') }}</dt><dd class="font-mono font-semibold text-teal-800">{{ data_get($snapshot, 'job_reference', '—') }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.manual_job_number') }}</dt><dd class="font-mono text-slate-800">{{ data_get($snapshot, 'manual_job_number', '—') }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.received_date_time') }}</dt><dd class="text-slate-700">{{ $receipt->issued_at->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.current_job_status') }}</dt><dd><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-700">{{ $job->status->value }}</span></dd></div>
                    <div><dt class="form-label">{{ __('ui.received_by') }}</dt><dd class="text-slate-700">{{ data_get($snapshot, 'received_by', '—') }}</dd></div>
                </dl>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-bold text-slate-900">{{ __('ui.payment_information') }}</h2>
                <dl class="space-y-3 text-sm">
                    <div><dt class="form-label">{{ __('ui.advance_payment') }}</dt><dd class="font-semibold text-slate-800">{{ config('business.currency') }} {{ data_get($snapshot, 'advance_payment', '0.00') }}</dd></div>
                    @if (data_get($snapshot, 'advance_payment_number'))<div><dt class="form-label">{{ __('payments.number') }}</dt><dd class="font-mono text-teal-800">{{ data_get($snapshot, 'advance_payment_number') }}</dd></div>@endif
                    <div><dt class="form-label">{{ __('ui.labor_cost') }}</dt><dd class="text-slate-700">{{ config('business.currency') }} {{ data_get($snapshot, 'labor_cost', '0.00') }}</dd></div>
                </dl>
                @can('payments.view')
                    @if ($advancePayments->isNotEmpty())
                        <div class="mt-4 border-t border-slate-100 pt-3">
                            <h3 class="mb-2 text-xs font-semibold text-slate-800">{{ __('payments.advance_history') }}</h3>
                            @foreach ($advancePayments as $allocation)
                                <a href="{{ route('payments.show', $allocation->payment) }}" class="flex justify-between gap-3 py-1.5 text-xs text-teal-800 hover:underline">
                                    <span>{{ $allocation->payment->payment_number }} · {{ $allocation->payment->paid_at->format(config('business.date_format')) }}</span>
                                    <span>{{ config('business.currency') }} {{ $allocation->amount }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                @endcan
            </section>
        </aside>
    </div>
@endsection
