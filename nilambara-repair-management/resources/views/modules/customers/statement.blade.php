@extends('layouts.app')

@section('title', __('ui.customer_statement'))

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('customers.show', $customer) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← {{ $customer->customer_code }} · {{ $customer->display_name }}</a>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.customer_statement') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('ui.customer_statement_description') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('customers.statement.print', ['customer' => $customer] + $filters) }}" target="_blank" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700">{{ __('payments.print_statement') }}</a>
            <a href="{{ route('customers.statement.pdf', ['customer' => $customer] + $filters) }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white">{{ __('payments.download_pdf') }}</a>
        </div>
    </div>

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <div><label for="date_from" class="form-label">{{ __('payments.date_from') }}</label><input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="form-input"></div>
        <div><label for="date_to" class="form-label">{{ __('payments.date_to') }}</label><input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="form-input"></div>
        <button class="rounded-xl border border-teal-700 px-4 py-2.5 text-xs font-semibold text-teal-800 hover:bg-teal-50">{{ __('ui.filter') }}</button>
        <a href="{{ route('customers.statement', $customer) }}" class="px-3 py-2.5 text-xs font-semibold text-slate-500">{{ __('ui.clear') }}</a>
    </form>

    <section class="mb-5 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-xs text-slate-600">
        <p class="font-semibold text-slate-900">{{ $customer->display_name }} · {{ $customer->customer_code }}</p>
        <p class="mt-1">{{ $customer->address }} · {{ $customer->mobile_number }}@if ($customer->email) · {{ $customer->email }}@endif</p>
    </section>

    @include('modules.customers.statement-summary')
    @include('modules.customers.statement-table', ['entries' => $entries])
@endsection
