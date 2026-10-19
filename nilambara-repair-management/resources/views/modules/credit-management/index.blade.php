@extends('layouts.app')

@section('title', __('payments.credit_invoices'))

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div><p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.sales_finance') }}</p><h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('payments.credit_invoices') }}</h1><p class="mt-1 text-sm text-slate-500">{{ __('payments.credit_list_description') }}</p></div>
        @can('payments.view')<a href="{{ route('payments.index', ['payment_type' => 'credit_collection']) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700">{{ __('payments.credit_collections') }}</a>@endcan
    </div>

    <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-2 xl:grid-cols-6">
        <input name="q" value="{{ request('q') }}" placeholder="{{ __('payments.search_hint') }}" class="form-input md:col-span-2">
        <select name="customer_id" class="form-input"><option value="">{{ __('payments.all_customers') }}</option>@foreach ($customers as $customer)<option value="{{ $customer->getKey() }}" @selected((string) request('customer_id') === (string) $customer->getKey())>{{ $customer->customer_code }} · {{ $customer->display_name }}</option>@endforeach</select>
        <input name="date_from" type="date" value="{{ request('date_from') }}" class="form-input" aria-label="{{ __('payments.date_from') }}">
        <input name="date_to" type="date" value="{{ request('date_to') }}" class="form-input" aria-label="{{ __('payments.date_to') }}">
        <select name="payment_status" class="form-input"><option value="outstanding" @selected(request('payment_status', 'outstanding') === 'outstanding')>{{ __('payments.outstanding_only') }}</option><option value="partially_paid" @selected(request('payment_status') === 'partially_paid')>{{ __('payments.status_partially_paid') }}</option><option value="paid" @selected(request('payment_status') === 'paid')>{{ __('payments.status_paid') }}</option><option value="all" @selected(request('payment_status') === 'all')>{{ __('payments.all_statuses') }}</option></select>
        <button class="rounded-xl border border-teal-700 px-4 py-2 text-xs font-semibold text-teal-800 hover:bg-teal-50">{{ __('ui.search') }}</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto"><table class="w-full min-w-[980px] text-left text-xs">
            <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase"><tr><th class="px-4 py-3">{{ __('payments.invoice') }}</th><th class="px-4 py-3">{{ __('payments.date') }}</th><th class="px-4 py-3">{{ __('payments.customer') }}</th><th class="px-4 py-3 text-right">{{ __('payments.credit_amount') }}</th><th class="px-4 py-3 text-right">{{ __('payments.amount_paid') }}</th><th class="px-4 py-3 text-right">{{ __('payments.outstanding') }}</th><th class="px-4 py-3">{{ __('payments.status') }}</th><th class="px-4 py-3">{{ __('payments.actions') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($invoices as $invoice)
                    <tr class="text-slate-700">
                        <td class="px-4 py-3 font-mono font-semibold"><a href="{{ route('credit-invoices.show', $invoice) }}" class="text-teal-800 hover:underline">{{ $invoice->invoice_number }}</a></td>
                        <td class="px-4 py-3">{{ $invoice->invoice_date->format(config('business.date_format')) }}</td>
                        <td class="px-4 py-3">{{ $invoice->customer->display_name }}<span class="mt-1 block text-[10px] text-slate-400">{{ $invoice->customer->mobile_number }}</span></td>
                        <td class="px-4 py-3 text-right">{{ config('business.currency') }} {{ $invoice->total }}</td>
                        <td class="px-4 py-3 text-right">{{ config('business.currency') }} {{ $invoice->payment_summary['total_paid'] }}</td>
                        <td class="px-4 py-3 text-right font-semibold">{{ config('business.currency') }} {{ $invoice->payment_summary['outstanding'] }}</td>
                        <td class="px-4 py-3">{{ __('payments.status_'.$invoice->payment_summary['payment_status']) }}</td>
                        <td class="whitespace-nowrap px-4 py-3"><a href="{{ route('credit-invoices.show', $invoice) }}" class="font-semibold text-teal-800 hover:underline">{{ __('payments.history') }}</a>@if ($invoice->payment_summary['outstanding'] !== '0.00' && auth()->user()->can('credit.collect') && auth()->user()->can('payments.receive'))<a href="{{ route('credit-invoices.collect', $invoice) }}" class="ml-3 text-slate-600 hover:underline">{{ __('payments.collect') }}</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-14 text-center text-sm text-slate-500">{{ __('payments.no_credit_invoices') }}</td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if ($invoices->hasPages())<div class="border-t border-slate-100 px-4 py-4">{{ $invoices->links() }}</div>@endif
    </div>
@endsection
