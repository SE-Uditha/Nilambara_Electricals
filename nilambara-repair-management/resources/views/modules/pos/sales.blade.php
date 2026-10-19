@extends('layouts.app')

@section('title', __('pos.sales_history'))

@section('content')
    <div class="mb-6 flex items-end justify-between gap-4"><div><p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.sales_finance') }}</p><h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('pos.sales_history') }}</h1></div><a href="{{ route('pos.index') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white">{{ __('pos.new_sale') }}</a></div>
    <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-3 xl:grid-cols-7">
        <input name="q" value="{{ $filters['q'] ?? '' }}" class="form-input" placeholder="{{ __('pos.search_sale') }}">
        <input name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="form-input" aria-label="{{ __('payments.date_from') }}">
        <input name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="form-input" aria-label="{{ __('payments.date_to') }}">
        <select name="customer_id" class="form-input"><option value="">{{ __('pos.all_customers') }}</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected(($filters['customer_id'] ?? '') == $customer->id)>{{ $customer->customer_code }} · {{ $customer->display_name }}</option>@endforeach</select>
        <select name="cashier_id" class="form-input"><option value="">{{ __('pos.all_cashiers') }}</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected(($filters['cashier_id'] ?? '') == $employee->id)>{{ $employee->name }}</option>@endforeach</select>
        <select name="payment_method" class="form-input"><option value="">{{ __('pos.all_methods') }}</option>@foreach ($paymentMethods as $method)<option value="{{ $method->value }}" @selected(($filters['payment_method'] ?? '') === $method->value)>{{ __('payments.method_'.$method->value) }}</option>@endforeach</select>
        <button class="rounded-xl border border-teal-700 px-4 py-2 text-xs font-semibold text-teal-800">{{ __('ui.search') }}</button>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left text-xs">
        <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase"><tr><th class="px-4 py-3">{{ __('pos.sale_number') }}</th><th class="px-4 py-3">{{ __('payments.date') }}</th><th class="px-4 py-3">{{ __('pos.customer') }}</th><th class="px-4 py-3">{{ __('pos.items') }}</th><th class="px-4 py-3 text-right">{{ __('pos.total') }}</th><th class="px-4 py-3">{{ __('pos.payment') }}</th><th class="px-4 py-3">{{ __('pos.cashier') }}</th><th class="px-4 py-3">{{ __('pos.status') }}</th></tr></thead>
        <tbody class="divide-y divide-slate-100">@forelse ($sales as $sale)<tr>
            <td class="px-4 py-3 font-mono font-semibold"><a class="text-teal-800 hover:underline" href="{{ route('pos.sales.show', $sale) }}">{{ $sale->sale_number }}</a></td>
            <td class="px-4 py-3">{{ $sale->sale_at->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }}</td>
            <td class="px-4 py-3">{{ $sale->customer?->display_name ?: __('pos.walk_in') }}</td><td class="px-4 py-3">{{ $sale->items_count }}</td>
            <td class="px-4 py-3 text-right font-semibold">{{ config('business.currency') }} {{ $sale->total }}</td><td class="px-4 py-3">{{ $sale->payment_method ? __('payments.method_'.$sale->payment_method) : '—' }}</td>
            <td class="px-4 py-3">{{ $sale->cashierEmployee?->name }}</td><td class="px-4 py-3">{{ __('pos.payment_status_'.$sale->payment_status) }} · {{ __('pos.sale_status_'.$sale->status->value) }}</td>
        </tr>@empty<tr><td colspan="8" class="px-4 py-14 text-center text-sm text-slate-500">{{ __('pos.no_sales') }}</td></tr>@endforelse</tbody></table></div>
        @if ($sales->hasPages())<div class="border-t border-slate-100 px-4 py-4">{{ $sales->links() }}</div>@endif
    </div>
@endsection
