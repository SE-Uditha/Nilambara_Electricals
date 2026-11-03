@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div><p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ config('business.name') }}</p><h1 class="text-2xl font-bold text-slate-900">{{ __('dashboard.title') }}</h1><p class="mt-1 text-xs text-slate-500">{{ $range['date_from'] }} — {{ $range['date_to'] }} · {{ config('business.timezone') }}</p></div>
        <form method="GET" class="flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-3">
            <select name="period" class="form-input dashboard-period"><option value="today" @selected($range['preset'] === 'today')>{{ __('dashboard.today') }}</option><option value="yesterday" @selected($range['preset'] === 'yesterday')>{{ __('dashboard.yesterday') }}</option><option value="week" @selected($range['preset'] === 'week')>{{ __('dashboard.this_week') }}</option><option value="month" @selected($range['preset'] === 'month')>{{ __('dashboard.this_month') }}</option><option value="year" @selected($range['preset'] === 'year')>This Year</option><option value="custom" @selected($range['preset'] === 'custom')>{{ __('dashboard.custom') }}</option></select>
            <input type="date" name="date_from" value="{{ $range['preset'] === 'custom' ? $range['date_from'] : '' }}" class="form-input dashboard-date" aria-label="{{ __('dashboard.date_from') }}">
            <input type="date" name="date_to" value="{{ $range['preset'] === 'custom' ? $range['date_to'] : '' }}" class="form-input dashboard-date" aria-label="{{ __('dashboard.date_to') }}">
            <button class="ui-create-action rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white"><x-icon name="filter" :size="16" />{{ __('dashboard.apply') }}</button>
        </form>
    </div>

    @can('jobs.view')
    <section class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            [__('dashboard.received_jobs'), $jobs['received_jobs'] ?? 0, 'repair-jobs.index', 'blue', 'clipboard'],
            [__('dashboard.active_jobs'), $jobs['active_jobs'] ?? 0, 'repair-jobs.index', 'cyan', 'activity'],
            [__('dashboard.pending_inspection'), $jobs['pending_inspection'] ?? 0, 'repair-jobs.index', 'amber', 'search'],
            [__('dashboard.under_repair'), $jobs['under_repair'] ?? 0, 'repair-jobs.index', 'violet', 'tool'],
            [__('dashboard.waiting_parts'), $jobs['waiting_for_parts'] ?? 0, 'repair-jobs.index', 'amber', 'box'],
            [__('dashboard.completed'), $jobs['completed_jobs'] ?? 0, 'repair-jobs.index', 'green', 'check-circle'],
            [__('dashboard.ready_collection'), $jobs['ready_for_collection'] ?? 0, 'repair-jobs.index', 'teal', 'bag'],
            [__('dashboard.delivered'), $jobs['delivered_jobs'] ?? 0, 'repair-jobs.index', 'green', 'truck'],
            ['Cancelled Jobs', $jobs['cancelled_jobs'] ?? 0, 'repair-jobs.index', 'red', 'close'],
            ['Unable to Repair', $jobs['unable_to_repair'] ?? 0, 'repair-jobs.index', 'slate', 'tool'],
        ] as [$label, $value, $routeName, $tone, $icon])
            <x-dashboard-stat :label="$label" :value="$value" :tone="$tone" :icon="$icon" :href="route($routeName)" />
        @endforeach
    </section>
    @endcan

    <section class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @can('invoices.view')
            <x-dashboard-stat :label="__('dashboard.repair_sales')" :value="config('business.currency').' '.$sales['repair']" icon="invoice" tone="blue" :soft="true" />
        @endcan
        @can('reports.financial')
            @foreach ([[__('dashboard.total_sales'), $sales['total'], 'green', 'chart'], [__('dashboard.cash_sales'), $sales['cash_terms'], 'teal', 'wallet'], [__('dashboard.credit_sales'), $sales['credit_terms'], 'violet', 'credit']] as [$label, $value, $tone, $icon])
                <x-dashboard-stat :label="$label" :value="config('business.currency').' '.$value" :tone="$tone" :icon="$icon" :soft="true" />
            @endforeach
        @endcan
        @can('payments.view')
            @foreach ([[__('dashboard.payments_collected'), $payments['collected'], 'green', 'wallet'], [__('dashboard.advances'), $payments['advances'], 'cyan', 'receipt'], ['Credit collections', $payments['credit_collections'], 'violet', 'credit']] as [$label, $value, $tone, $icon])
                <x-dashboard-stat :label="$label" :value="config('business.currency').' '.$value" :tone="$tone" :icon="$icon" :soft="true" />
            @endforeach
        @endcan
        @can('credit.view')
            <x-dashboard-stat :label="__('dashboard.outstanding_credit')" :value="config('business.currency').' '.$payments['customer_credit']" icon="credit" tone="red" :soft="true" />
        @endcan
    </section>

    @can('inventory.view')
    <section class="mb-6 grid gap-3 sm:grid-cols-3">
        @foreach ([[__('dashboard.spare_parts'), $inventory['parts'], 'blue', 'box'], [__('dashboard.low_stock'), $inventory['low_stock'], 'amber', 'box'], [__('dashboard.out_of_stock'), $inventory['out_of_stock'], 'red', 'box'], ['Current stock (default locations)', $inventory['current_stock'], 'teal', 'box'], ['Stock valuation', $inventory['valuation'], 'violet', 'chart']] as [$label, $value, $tone, $icon])
            <x-dashboard-stat :label="$label" :value="$value" :tone="$tone" :icon="$icon" :soft="true" />
        @endforeach
    </section>
    @endcan

    <div class="grid gap-5 xl:grid-cols-2">
        @can('jobs.view')
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold">{{ __('dashboard.recent_jobs') }}</h2>            @can('reports.view')<a class="text-xs text-teal-700 hover:underline" href="{{ route('reports.index') }}">{{ __('dashboard.view_report') }}</a>@endcan</div>
            <div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="px-4 py-3">{{ __('dashboard.job_number') }}</th><th class="px-4 py-3">{{ __('dashboard.customer') }}</th><th class="px-4 py-3">{{ __('dashboard.item') }}</th><th class="px-4 py-3">{{ __('dashboard.status') }}</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse ($recent_jobs as $job)<tr><td class="px-4 py-3 font-semibold">{{ $job->job_number }}</td><td class="px-4 py-3">{{ $job->customer_name }}</td><td class="px-4 py-3">{{ $job->item_name }}</td><td class="px-4 py-3"><x-status-badge :status="$job->status" /></td></tr>@empty<tr><td colspan="4" class="px-4 py-10 text-center text-slate-500">{{ __('dashboard.no_jobs') }}</td></tr>@endforelse</tbody></table></div>
        </section>
        @endcan
        @can('inventory.view')
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold">{{ __('dashboard.low_stock_alerts') }}</h2></div>
            <div class="divide-y divide-slate-100">@forelse ($low_stock_items as $part)<div class="flex items-center justify-between gap-3 px-5 py-3"><div><p class="text-xs font-semibold text-slate-800">{{ $part->name }}</p><p class="mt-1 text-[10px] text-slate-500">{{ $part->sku }} · {{ $part->category_name ?: __('dashboard.uncategorized') }}</p></div><p class="text-xs font-bold text-amber-700">{{ $part->available_quantity }}</p></div>@empty<p class="px-5 py-10 text-center text-xs text-slate-500">{{ __('dashboard.no_low_stock') }}</p>@endforelse</div>
        </section>
        @endcan
        @can('invoices.view')
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold">{{ __('dashboard.recent_invoices') }}</h2></div><div class="divide-y divide-slate-100">@forelse ($recent_invoices as $invoice)<div class="flex items-center justify-between px-5 py-3"><div><p class="text-xs font-semibold">{{ $invoice->invoice_number }} · {{ $invoice->customer_name }}</p><p class="mt-1 text-[10px] text-slate-500">{{ $invoice->invoice_date }}</p></div><p class="text-xs font-semibold">{{ config('business.currency') }} {{ $invoice->total }}</p></div>@empty<p class="px-5 py-10 text-center text-xs text-slate-500">{{ __('dashboard.no_invoices') }}</p>@endforelse</div></section>
        @endcan
    </div>
@can('jobs.view')<section class="mt-5 rounded-xl border bg-white p-4"><h2 class="font-semibold">Ready for Collection</h2>@forelse ($ready_jobs as $job)<p class="mt-2 text-sm">{{ $job->job_number }} · {{ $job->item_name }}</p>@empty<p class="mt-2 text-sm text-slate-500">No ready jobs in the selected received-date cohort.</p>@endforelse</section>@endcan
@endsection
