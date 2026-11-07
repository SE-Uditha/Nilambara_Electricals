@extends('layouts.app')

@section('title', __('payments.invoice_history').' · '.$invoice->invoice_number)

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div><a href="{{ route('credit-invoices.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← {{ __('payments.credit_invoices') }}</a><h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">{{ $invoice->invoice_number }}</h1><p class="mt-1 text-sm text-slate-500">{{ $invoice->customer->display_name }} · {{ __('payments.invoice_history') }}</p></div>
        <div class="flex flex-wrap gap-2">@can('invoices.view')<a href="{{ route('invoices.show', $invoice) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700">{{ __('payments.view_invoice') }}</a>@endcan
        @can('customers.statement')<a href="{{ route('customers.statement', $invoice->customer) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700">{{ __('payments.customer_statement') }}</a>@endcan
        @if ($balance['outstanding'] !== '0.00' && auth()->user()->can('credit.collect') && auth()->user()->can('payments.receive'))<a href="{{ route('credit-invoices.collect', $invoice) }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white">{{ __('payments.collect') }}</a>@endif</div>
    </div>

    <section class="mb-5 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2 xl:grid-cols-4">
        <div><p class="form-label">{{ __('payments.customer') }}</p><p class="font-semibold">{{ $invoice->customer->display_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $invoice->customer->mobile_number }}</p></div>
        <div><p class="form-label">{{ __('payments.invoice_date') }}</p><p>{{ $invoice->invoice_date->format(config('business.date_format')) }}</p></div>
        <div><p class="form-label">{{ __('payments.credit_amount') }}</p><p class="font-semibold">{{ config('business.currency') }} {{ $invoice->total }}</p></div>
        <div><p class="form-label">{{ __('payments.expected_payment_date') }}</p><p class="font-semibold">{{ $invoice->due_date?->format(config('business.date_format')) ?: '—' }}
            @if ($invoice->due_date && $balance['outstanding'] !== '0.00' && $invoice->due_date->toDateString() < now(config('business.timezone'))->toDateString())<span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-800">{{ __('payments.passed_due_days', ['days' => (int) $invoice->due_date->diffInDays(now(config('business.timezone'))->toDateString())]) }}</span>@endif</p></div>
        <div><p class="form-label">{{ __('payments.payment_status') }}</p><p>{{ __('payments.status_'.$balance['payment_status']) }}</p></div>
        <div><p class="form-label">{{ __('payments.advance_applied') }}</p><p>{{ config('business.currency') }} {{ $balance['advance_paid'] }}</p></div>
        <div><p class="form-label">{{ __('payments.amount_paid') }}</p><p>{{ config('business.currency') }} {{ $balance['total_paid'] }}</p></div>
        <div><p class="form-label">{{ __('payments.outstanding') }}</p><p class="font-bold text-teal-900">{{ config('business.currency') }} {{ $balance['outstanding'] }}</p></div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-bold text-slate-900">{{ __('payments.payment_history') }}</h2></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[850px] text-left text-xs">
            <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase"><tr><th class="px-5 py-3">{{ __('payments.date') }}</th><th class="px-5 py-3">{{ __('payments.number') }}</th><th class="px-5 py-3">{{ __('payments.type') }}</th><th class="px-5 py-3">{{ __('payments.method') }}</th><th class="px-5 py-3">{{ __('payments.received_by') }}</th><th class="px-5 py-3 text-right">{{ __('payments.amount') }}</th><th class="px-5 py-3 text-right">{{ __('payments.released') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($allocations as $allocation)
                    @php($released = $allocation->releases->reduce(fn ($sum, $release) => $sum->plus((string) $release->amount), \Brick\Math\BigDecimal::zero()->toScale(2)))
                    <tr>
                        <td class="px-5 py-3">{{ $allocation->allocated_at->format(config('business.date_format')) }}</td>
                        <td class="px-5 py-3 font-mono">@can('payments.view')<a href="{{ route('payments.show', $allocation->payment) }}" class="text-teal-800 hover:underline">{{ $allocation->payment->payment_number }}</a>@else{{ $allocation->payment->payment_number }}@endcan</td>
                        <td class="px-5 py-3">{{ $allocation->target_type === 'job_advance' ? __('payments.job_advance') : __('payments.invoice_payment') }}</td>
                        <td class="px-5 py-3">{{ __('payments.method_'.$allocation->payment->method->value) }}</td>
                        <td class="px-5 py-3">{{ $allocation->payment->receivedByEmployee?->name ?: '—' }}</td>
                        <td class="px-5 py-3 text-right">{{ config('business.currency') }} {{ $allocation->amount }}</td>
                        <td class="px-5 py-3 text-right">{{ config('business.currency') }} {{ $released }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500">{{ __('payments.no_payment_history') }}</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
@endsection
