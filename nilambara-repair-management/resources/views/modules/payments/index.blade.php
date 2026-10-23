@extends('layouts.app')

@section('title', __('payments.title'))

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.sales_finance') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('payments.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('payments.list_description') }}</p>
        </div>
        @can('payments.receive')
            <a href="{{ route('payments.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('payments.record_payment') }}</a>
        @endcan
    </div>

    @if (session('status'))<div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>@endif

    @include('modules.repair-jobs.billing-queue')

    <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-3 xl:grid-cols-6">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('payments.search_hint') }}" class="form-input md:col-span-2">
        <input name="date_from" type="date" value="{{ request('date_from') }}" class="form-input" aria-label="{{ __('payments.date_from') }}">
        <input name="date_to" type="date" value="{{ request('date_to') }}" class="form-input" aria-label="{{ __('payments.date_to') }}">
        <select name="method" class="form-input"><option value="">{{ __('payments.all_methods') }}</option>@foreach (\App\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}" @selected(request('method') === $method->value)>{{ __('payments.method_'.$method->value) }}</option>@endforeach</select>
        <select name="payment_type" class="form-input"><option value="">{{ __('payments.all_types') }}</option>@foreach (['advance', 'invoice', 'credit_collection'] as $type)<option value="{{ $type }}" @selected(request('payment_type') === $type)>{{ __('payments.type_'.$type) }}</option>@endforeach</select>
        <select name="status" class="form-input"><option value="">{{ __('payments.all_statuses') }}</option>@foreach (['unallocated', 'partially_allocated', 'allocated', 'partially_reversed', 'reversed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ __('payments.status_'.$status) }}</option>@endforeach</select>
        <button class="rounded-xl border border-teal-700 px-4 py-2 text-xs font-semibold text-teal-800 hover:bg-teal-50">{{ __('ui.search') }}</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[920px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    <tr><th class="px-4 py-3">{{ __('payments.number') }}</th><th class="px-4 py-3">{{ __('payments.date') }}</th><th class="px-4 py-3">{{ __('payments.customer') }}</th><th class="px-4 py-3">{{ __('payments.related_document') }}</th><th class="px-4 py-3">{{ __('payments.type') }}</th><th class="px-4 py-3">{{ __('payments.method') }}</th><th class="px-4 py-3 text-right">{{ __('payments.amount') }}</th><th class="px-4 py-3">{{ __('payments.received_by') }}</th><th class="px-4 py-3">{{ __('payments.status') }}</th><th class="px-4 py-3">{{ __('payments.actions') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $payment)
                        @php($allocation = $payment->paymentAllocations->first())
                        <tr class="text-slate-700">
                            <td class="px-4 py-3 font-mono font-semibold"><a href="{{ route('payments.show', $payment) }}" class="text-teal-800 hover:underline">{{ $payment->payment_number }}</a></td>
                            <td class="px-4 py-3">{{ $payment->paid_at->timezone(config('business.timezone'))->format(config('business.date_format')) }}</td>
                            <td class="px-4 py-3">{{ $payment->customer?->display_name ?: '-' }}<span class="mt-1 block text-[10px] text-slate-400">{{ $payment->customer?->mobile_number }}</span></td>
                            <td class="px-4 py-3">
                                @if ($allocation?->invoice)<a class="text-teal-800 hover:underline" href="{{ route('invoices.show', $allocation->invoice) }}">{{ $allocation->invoice->invoice_number }}</a>
                                @elseif ($allocation?->repairJob)<a class="text-teal-800 hover:underline" href="{{ route('repair-jobs.show', $allocation->repairJob) }}">{{ $allocation->repairJob->automatic_number ?: $allocation->repairJob->job_number }}</a>
                                @else{{ __('payments.unallocated') }}@endif
                            </td>
                            <td class="px-4 py-3">{{ __('payments.type_'.$payment->payment_type) }}</td>
                            <td class="px-4 py-3">{{ __('payments.method_'.$payment->method->value) }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ config('business.currency') }} {{ $payment->amount }}</td>
                            <td class="px-4 py-3">{{ $payment->receivedByEmployee?->name ?: '—' }}</td>
                            <td class="px-4 py-3">{{ __('payments.status_'.$payment->balance_summary['status']) }}</td>
                            <td class="px-4 py-3"><a href="{{ route('payments.show', $payment) }}" class="font-semibold text-teal-800 hover:underline">{{ __('payments.view') }}</a>@can('print', $payment)<a href="{{ route('payments.print', $payment) }}" class="ml-3 text-slate-600 hover:underline">{{ __('payments.print') }}</a>@endcan</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-14 text-center text-sm text-slate-500">{{ __('payments.no_payments') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages())<div class="border-t border-slate-100 px-4 py-4">{{ $payments->links() }}</div>@endif
    </div>
@endsection
