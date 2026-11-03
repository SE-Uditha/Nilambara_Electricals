@extends('layouts.app')
@section('title', __('dashboard.title'))
@section('content')
@php
    $stages = [
        [__('dashboard.pending_inspection'), (int) ($jobs['pending_inspection'] ?? 0), '#f59e0b'],
        [__('dashboard.under_repair'), (int) ($jobs['under_repair'] ?? 0), '#6366f1'],
        [__('dashboard.waiting_parts'), (int) ($jobs['waiting_for_parts'] ?? 0), '#eab308'],
        [__('dashboard.completed'), (int) ($jobs['completed_jobs'] ?? 0), '#16a34a'],
        [__('dashboard.ready_collection'), (int) ($jobs['ready_for_collection'] ?? 0), '#0d9488'],
        [__('dashboard.delivered'), (int) ($jobs['delivered_jobs'] ?? 0), '#2563eb'],
        [__('dashboard.cancelled'), (int) ($jobs['cancelled_jobs'] ?? 0), '#ef4444'],
        [__('dashboard.unable'), (int) ($jobs['unable_to_repair'] ?? 0), '#64748b'],
    ];
    $received = (int) ($jobs['received_jobs'] ?? 0);
    $other = max(0, $received - array_sum(array_column($stages, 1)));
    if ($other > 0) $stages[] = [__('dashboard.other_stages'), $other, '#94a3b8'];
    $largestStage = max(1, ...array_column($stages, 1));
    $cashSales = (float) $sales['cash_terms'];
    $creditSales = (float) $sales['credit_terms'];
    $mixTotal = $cashSales + $creditSales;
    $cashPercent = $mixTotal > 0 ? round($cashSales / $mixTotal * 100, 1) : 0;
    $creditPercent = $mixTotal > 0 ? round(100 - $cashPercent, 1) : 0;
    $stockTotal = (int) $inventory['parts'];
    $lowStock = (int) $inventory['low_stock'];
    $outStock = (int) $inventory['out_of_stock'];
    $healthyStock = max(0, $stockTotal - $lowStock - $outStock);
@endphp
<div class="dashboard-page">
    <header class="dashboard-heading">
        <div><p class="dashboard-eyebrow">{{ config('business.name') }}</p><h1>{{ __('dashboard.title') }}</h1><p class="dashboard-subtitle">{{ __('dashboard.overview_help') }}</p></div>
        <span class="dashboard-date-label"><x-icon name="calendar" :size="16" />{{ $range['date_from'] }} &ndash; {{ $range['date_to'] }}</span>
    </header>

    <form method="GET" class="dashboard-filters">
        <div><label class="form-label" for="dashboard-period">{{ __('dashboard.period') }}</label><select id="dashboard-period" name="period" class="form-input">@foreach (['today' => 'today', 'yesterday' => 'yesterday', 'week' => 'this_week', 'month' => 'this_month', 'year' => 'this_year', 'custom' => 'custom'] as $value => $key)<option value="{{ $value }}" @selected($range['preset'] === $value)>{{ __('dashboard.'.$key) }}</option>@endforeach</select></div>
        <div><label class="form-label" for="dashboard-from">{{ __('dashboard.date_from') }}</label><input id="dashboard-from" type="date" name="date_from" value="{{ $range['preset'] === 'custom' ? $range['date_from'] : '' }}" class="form-input"></div>
        <div><label class="form-label" for="dashboard-to">{{ __('dashboard.date_to') }}</label><input id="dashboard-to" type="date" name="date_to" value="{{ $range['preset'] === 'custom' ? $range['date_to'] : '' }}" class="form-input"></div>
        <button class="dashboard-apply"><x-icon name="filter" :size="17" />{{ __('dashboard.apply') }}</button>
    </form>

    @can('jobs.view')
        <section class="dashboard-kpis" aria-label="{{ __('dashboard.repair_overview') }}">
            @foreach ([[__('dashboard.received_jobs'), $received, 'blue', 'clipboard'], [__('dashboard.active_jobs'), $jobs['active_jobs'] ?? 0, 'violet', 'activity'], [__('dashboard.completed'), $jobs['completed_jobs'] ?? 0, 'green', 'check-circle'], [__('dashboard.ready_collection'), $jobs['ready_for_collection'] ?? 0, 'teal', 'bag']] as [$label, $value, $tone, $icon])
                <x-dashboard-stat :label="$label" :value="$value" :tone="$tone" :icon="$icon" :href="route('repair-jobs.index')" :soft="true" />
            @endforeach
        </section>
    @endcan

    <div class="dashboard-columns">
        @can('jobs.view')
        <section class="dashboard-panel">
            <div class="dashboard-panel-heading"><div><h2><x-icon name="chart" :size="19" />{{ __('dashboard.repair_progress') }}</h2><p>{{ __('dashboard.cohort_help') }}</p></div><span class="dashboard-count">{{ $received }} {{ __('dashboard.jobs_label') }}</span></div>
            <div class="dashboard-panel-body">
                @if ($received === 0)<p class="dashboard-chart-empty">{{ __('dashboard.no_chart_jobs') }}</p>@endif
                <div class="dashboard-bars" role="img" aria-label="{{ __('dashboard.repair_progress') }}">
                    @foreach ($stages as [$label, $count, $color])
                        <div class="dashboard-bar-row"><span>{{ $label }}</span><div class="dashboard-bar-track"><div class="dashboard-bar-fill" style="width: {{ round($count / $largestStage * 100, 2) }}%; background: {{ $color }}"></div></div><strong>{{ $count }}</strong></div>
                    @endforeach
                </div>
                <details class="dashboard-details"><summary>{{ __('dashboard.chart_data') }}</summary><table class="w-full text-left"><caption class="sr-only">{{ __('dashboard.repair_progress') }}</caption><thead><tr><th>{{ __('dashboard.status') }}</th><th>{{ __('dashboard.jobs_label') }}</th></tr></thead><tbody>@foreach ($stages as [$label, $count])<tr><td>{{ $label }}</td><td>{{ $count }}</td></tr>@endforeach</tbody></table></details>
            </div>
        </section>
        @endcan

        @if (auth()->user()->can('invoices.view') || auth()->user()->can('payments.view') || auth()->user()->can('credit.view') || auth()->user()->can('reports.financial'))
        <section class="dashboard-panel">
            <div class="dashboard-panel-heading"><div><h2><x-icon name="wallet" :size="19" />{{ __('dashboard.finance_overview') }}</h2><p>{{ __('dashboard.selected_period') }} &middot; {{ config('business.currency') }}</p></div></div>
            <div class="dashboard-panel-body">
                @can('invoices.view')<div class="dashboard-finance-total"><span>{{ __('dashboard.repair_sales') }}</span><strong>{{ config('business.currency') }} {{ $sales['repair'] }}</strong></div>@endcan
                @can('reports.financial')
                <div class="dashboard-sales-mix">
                    <div class="dashboard-donut" role="img" aria-label="{{ __('dashboard.cash_sales') }}: {{ $sales['cash_terms'] }}; {{ __('dashboard.credit_sales') }}: {{ $sales['credit_terms'] }}">
                        <svg viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="46" fill="none" stroke="#e2e8f0" stroke-width="13" />@if ($mixTotal > 0)<circle cx="60" cy="60" r="46" fill="none" stroke="#2563eb" stroke-width="13" pathLength="100" stroke-dasharray="{{ $cashPercent }} {{ 100 - $cashPercent }}" transform="rotate(-90 60 60)" /><circle cx="60" cy="60" r="46" fill="none" stroke="#8b5cf6" stroke-width="13" pathLength="100" stroke-dasharray="{{ $creditPercent }} {{ 100 - $creditPercent }}" stroke-dashoffset="{{ -$cashPercent }}" transform="rotate(-90 60 60)" />@endif</svg>
                        <div><strong>{{ $mixTotal > 0 ? '100%' : '0' }}</strong><span>{{ __('dashboard.sales_mix') }}</span></div>
                    </div>
                    <dl class="dashboard-sales-legend"><div><dt><i style="background:#2563eb"></i>{{ __('dashboard.cash_sales') }}</dt><dd>{{ $sales['cash_terms'] }} <small>{{ $cashPercent }}%</small></dd></div><div><dt><i style="background:#8b5cf6"></i>{{ __('dashboard.credit_sales') }}</dt><dd>{{ $sales['credit_terms'] }} <small>{{ $creditPercent }}%</small></dd></div></dl>
                </div>
                @if ($mixTotal == 0)<p class="dashboard-muted">{{ __('dashboard.no_sales_chart') }}</p>@endif
                <div class="dashboard-finance-row"><span>{{ __('dashboard.total_sales') }}</span><strong>{{ $sales['total'] }}</strong></div>
                @endcan
                <dl class="dashboard-finance-list">
                    @can('payments.view')@foreach ([[__('dashboard.payments_collected'), $payments['collected']], [__('dashboard.advances'), $payments['advances']], [__('dashboard.credit_collections'), $payments['credit_collections']]] as [$label, $value])<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach @endcan
                    @can('credit.view')<div class="dashboard-outstanding"><dt>{{ __('dashboard.outstanding_credit') }}</dt><dd>{{ $payments['customer_credit'] }}</dd></div>@endcan
                </dl>
                <p class="dashboard-muted">{{ __('dashboard.finance_help') }}</p>
            </div>
        </section>
        @endif

        @can('jobs.view')
        <section class="dashboard-panel">
            <div class="dashboard-panel-heading"><h2><x-icon name="clipboard" :size="19" />{{ __('dashboard.recent_jobs') }}</h2><a href="{{ route('repair-jobs.index') }}">{{ __('dashboard.view_all') }} <x-icon name="arrow-right" :size="15" /></a></div>
            <div class="overflow-x-auto"><table class="w-full text-left"><thead><tr><th class="px-5">{{ __('dashboard.job_number') }}</th><th>{{ __('dashboard.customer') }} / {{ __('dashboard.item') }}</th><th class="pr-5">{{ __('dashboard.status') }}</th></tr></thead><tbody>@forelse ($recent_jobs->take(4) as $job)<tr><td class="px-5 py-3"><a class="font-semibold text-blue-700" href="{{ route('repair-jobs.show', $job->id) }}">{{ $job->job_number }}</a></td><td class="py-3"><span class="font-semibold">{{ $job->customer_name }}</span><span class="dashboard-table-secondary">{{ $job->item_name }}</span></td><td class="py-3 pr-5"><x-status-badge :status="$job->status" /></td></tr>@empty<tr><td colspan="3" class="dashboard-empty">{{ __('dashboard.no_jobs') }}</td></tr>@endforelse</tbody></table></div>
        </section>

        <section class="dashboard-panel">
            <div class="dashboard-panel-heading"><div><h2><x-icon name="bag" :size="19" />{{ __('dashboard.ready_collection') }}</h2><p>{{ __('dashboard.collection_help') }}</p></div><a href="{{ route('repair-jobs.index', ['status' => \App\Enums\JobStatus::ReadyForCollection->value]) }}">{{ __('dashboard.view_all') }} <span class="dashboard-count">{{ $jobs['ready_for_collection'] ?? 0 }}</span></a></div>
            <div class="dashboard-item-list">@forelse ($ready_jobs->take(4) as $job)<a class="dashboard-list-item" href="{{ route('repair-jobs.show', $job->id) }}"><span><strong>{{ $job->job_number }}</strong><small>{{ $job->item_name }}</small></span><x-icon name="arrow-right" :size="17" /></a>@empty<div class="dashboard-empty"><x-icon name="check-circle" :size="30" /><p>{{ __('dashboard.no_ready_jobs') }}</p></div>@endforelse</div>
        </section>
        @endcan

        @can('inventory.view')
        <section class="dashboard-panel">
            <div class="dashboard-panel-heading"><div><h2><x-icon name="box" :size="19" />{{ __('dashboard.inventory_overview') }}</h2><p>{{ __('dashboard.stock_help') }}</p></div><a href="{{ route('inventory.index') }}">{{ __('dashboard.view_all') }} <x-icon name="arrow-right" :size="15" /></a></div>
            <div class="dashboard-panel-body">
                <div class="dashboard-stock-numbers">@foreach ([[__('dashboard.spare_parts'), $stockTotal], [__('dashboard.low_stock'), $lowStock], [__('dashboard.out_of_stock'), $outStock]] as [$label, $value])<div><strong>{{ $value }}</strong><span>{{ $label }}</span></div>@endforeach</div>
                <div class="dashboard-stock-meter" role="img" aria-label="{{ __('dashboard.healthy_stock') }}: {{ $healthyStock }}; {{ __('dashboard.low_stock') }}: {{ $lowStock }}; {{ __('dashboard.out_of_stock') }}: {{ $outStock }}">@foreach ([[$healthyStock, '#16a34a'], [$lowStock, '#f59e0b'], [$outStock, '#ef4444']] as [$count, $color])<span style="width: {{ $stockTotal > 0 ? $count / $stockTotal * 100 : 0 }}%; background: {{ $color }}"></span>@endforeach</div>
                <div class="dashboard-stock-legend"><span><i style="background:#16a34a"></i>{{ __('dashboard.healthy_stock') }}</span><span><i style="background:#f59e0b"></i>{{ __('dashboard.low_stock') }}</span><span><i style="background:#ef4444"></i>{{ __('dashboard.out_of_stock') }}</span></div>
                <div class="dashboard-finance-row"><span>{{ __('dashboard.current_stock') }}</span><strong>{{ $inventory['current_stock'] }}</strong></div>
                <div class="flex items-center justify-between"><h3 class="dashboard-list-title">{{ __('dashboard.low_stock_alerts') }}</h3><a class="text-xs font-semibold text-blue-700" href="{{ route('inventory.index', ['state' => 'low']) }}">{{ __('dashboard.view_all') }}</a></div>
                <div class="dashboard-item-list">@forelse ($low_stock_items->take(4) as $part)<div class="dashboard-list-item"><span><strong>{{ $part->name }}</strong><small>{{ $part->sku }} &middot; {{ $part->category_name ?: __('dashboard.uncategorized') }}</small></span><span class="status-badge {{ $part->available_quantity <= 0 ? 'status-badge--red' : 'status-badge--amber' }}">{{ $part->available_quantity }}</span></div>@empty<p class="dashboard-empty">{{ __('dashboard.no_low_stock') }}</p>@endforelse</div>
            </div>
        </section>
        @endcan

        @can('invoices.view')
        <section class="dashboard-panel">
            <div class="dashboard-panel-heading"><h2><x-icon name="invoice" :size="19" />{{ __('dashboard.recent_invoices') }}</h2><a href="{{ route('invoices.index') }}">{{ __('dashboard.view_all') }} <x-icon name="arrow-right" :size="15" /></a></div>
            <div class="dashboard-item-list">@forelse ($recent_invoices->take(4) as $invoice)<a class="dashboard-list-item" href="{{ route('invoices.show', $invoice->id) }}" target="_blank" rel="noopener noreferrer"><span><strong>{{ $invoice->invoice_number }}</strong><small>{{ $invoice->customer_name }} &middot; {{ $invoice->invoice_date }}</small></span><strong class="dashboard-invoice-amount">{{ config('business.currency') }} {{ $invoice->total }}</strong></a>@empty<div class="dashboard-empty"><x-icon name="invoice" :size="30" /><p>{{ __('dashboard.no_invoices') }}</p></div>@endforelse</div>
            @can('reports.view')<div class="dashboard-panel-footer"><a href="{{ route('reports.index') }}">{{ __('dashboard.view_report') }} <x-icon name="arrow-right" :size="16" /></a></div>@endcan
        </section>
        @endcan
    </div>
</div>
@endsection
