@extends('layouts.app')

@section('title', __('invoices.title').' · '.config('app.name'))

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.sales_finance') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('invoices.title') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('invoices.description') }}</p>
        </div>
        @can('create', App\Modules\Invoices\Models\Invoice::class)
            <a href="{{ route('invoices.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('invoices.new_invoice') }}</a>
        @endcan
    </div>

    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>
    @endif

    <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
        <div class="lg:col-span-2"><label for="q" class="form-label">{{ __('invoices.search') }}</label><input id="q" name="q" value="{{ request('q') }}" class="form-input" placeholder="{{ __('invoices.search_invoice_hint') }}"></div>
        <div><label for="date_from" class="form-label">{{ __('invoices.date_from') }}</label><input id="date_from" name="date_from" type="date" value="{{ request('date_from') }}" class="form-input"></div>
        <div><label for="date_to" class="form-label">{{ __('invoices.date_to') }}</label><input id="date_to" name="date_to" type="date" value="{{ request('date_to') }}" class="form-input"></div>
        <div><label for="status" class="form-label">{{ __('invoices.status') }}</label><select id="status" name="status" class="form-input"><option value="">{{ __('invoices.all_statuses') }}</option>@foreach (App\Enums\DocumentStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ __('invoices.'.$status->value) }}</option>@endforeach</select></div>
        <div><label for="payment_status" class="form-label">{{ __('invoices.payment_status') }}</label><select id="payment_status" name="payment_status" class="form-input"><option value="">{{ __('invoices.all_statuses') }}</option>@foreach (['unpaid', 'partially_paid', 'paid', 'outstanding'] as $status)<option value="{{ $status }}" @selected(request('payment_status') === $status)>{{ __('invoices.'.$status) }}</option>@endforeach</select></div>
        <div><label for="payment_terms" class="form-label">{{ __('invoices.payment_terms_filter') }}</label><select id="payment_terms" name="payment_terms" class="form-input"><option value="">{{ __('invoices.all_statuses') }}</option>@foreach (App\Enums\PaymentTerms::cases() as $terms)<option value="{{ $terms->value }}" @selected(request('payment_terms') === $terms->value)>{{ __('invoices.'.$terms->value) }}</option>@endforeach</select></div>
        <div class="flex items-end gap-2"><button class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('invoices.search') }}</button><a href="{{ route('invoices.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">{{ __('invoices.clear') }}</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    <tr>
                        <th class="px-4 py-3">{{ __('invoices.invoice_number') }}</th><th class="px-4 py-3">{{ __('invoices.invoice_date') }}</th>
                        <th class="px-4 py-3">{{ __('invoices.customer') }}</th><th class="px-4 py-3">{{ __('invoices.job_count') }}</th>
                        <th class="px-4 py-3">{{ __('invoices.invoice_total') }}</th><th class="px-4 py-3">{{ __('invoices.advance_paid') }}</th>
                        <th class="px-4 py-3">{{ __('invoices.total_paid') }}</th><th class="px-4 py-3">{{ __('invoices.outstanding_balance') }}</th>
                        <th class="px-4 py-3">{{ __('invoices.payment_terms') }}</th><th class="px-4 py-3">{{ __('invoices.payment_status') }}</th>
                        <th class="px-4 py-3">{{ __('invoices.cashier_name') }}</th><th class="px-4 py-3">{{ __('invoices.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @forelse ($invoices as $invoice)
                        <tr class="hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-4 py-3 font-mono font-semibold text-teal-800"><a href="{{ route('invoices.show', $invoice) }}" class="hover:underline">{{ $invoice->invoice_number }}</a></td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $invoice->invoice_date->format(config('business.date_format')) }}</td>
                            <td class="px-4 py-3">{{ $invoice->customer->display_name }}<span class="block text-[10px] text-slate-400">{{ $invoice->customer->mobile_number }}</span></td>
                            <td class="px-4 py-3">{{ $invoice->invoiceJobs->count() }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold">{{ config('business.currency') }} {{ $invoice->total }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ config('business.currency') }} {{ $invoice->payment_summary['advance_paid'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ config('business.currency') }} {{ $invoice->payment_summary['total_paid'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ config('business.currency') }} {{ $invoice->payment_summary['outstanding'] }}</td>
                            <td class="px-4 py-3">{{ __('invoices.'.$invoice->payment_terms->value) }}</td>
                            <td class="px-4 py-3">{{ __('invoices.'.$invoice->payment_summary['payment_status']) }}<span class="block text-[10px] text-slate-400">{{ __('invoices.'.$invoice->status->value) }}</span></td>
                            <td class="px-4 py-3">{{ $invoice->cashierEmployee->name }}</td>
                            <td class="whitespace-nowrap px-4 py-3"><div class="flex items-center gap-3"><a class="font-semibold text-teal-700 hover:underline" href="{{ route('invoices.show', $invoice) }}">{{ __('invoices.view') }}</a>@can('print', $invoice)<a class="font-semibold text-slate-600 hover:underline" href="{{ route('invoices.print', $invoice) }}">{{ __('invoices.print') }}</a>@endcan</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="px-4 py-10 text-center text-sm text-slate-500">{{ __('invoices.no_invoices') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-4 py-3">{{ $invoices->links() }}</div>
    </div>
@endsection
