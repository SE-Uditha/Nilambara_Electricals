@extends('layouts.app')

@section('title', $data['title'])

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <a class="text-xs text-teal-700 hover:underline" href="{{ route('reports.index') }}">{{ __('reports.title') }}</a>
            <h1 class="mt-2 text-2xl font-bold text-slate-900">{{ $data['title'] }}</h1>
        </div>
        <div class="flex gap-2">
            <button type="button" class="rounded-lg border px-3 py-2 text-xs" onclick="window.print()">Print</button>
            @can('reports.export')
            <a class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold" href="{{ route('reports.export', ['report' => $report, 'format' => 'xlsx'] + request()->query()) }}">{{ __('reports.export_excel') }}</a>
            <a class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold" href="{{ route('reports.export', ['report' => $report, 'format' => 'pdf'] + request()->query()) }}">{{ __('reports.export_pdf') }}</a>
            <a class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold" href="{{ route('reports.export', ['report' => $report, 'format' => 'csv'] + request()->query()) }}">{{ __('reports.export_csv') }}</a>
            @endcan
        </div>
    </div>

    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <label class="text-xs text-slate-600">{{ __('reports.date_from') }}<input class="form-input mt-1" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
        <label class="text-xs text-slate-600">{{ __('reports.date_to') }}<input class="form-input mt-1" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
        <label class="text-xs text-slate-600">{{ __('reports.search') }}<input class="form-input mt-1" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100"></label>
        @if (in_array($report, ['repair', 'technicians'], true))
            <label class="text-xs text-slate-600">{{ __('reports.status') }}<select class="form-input mt-1" name="status"><option value="">{{ __('reports.all_statuses') }}</option>@foreach (\App\Enums\JobStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
        @endif
        @if (in_array($report, ['stock', 'valuation'], true))
            <label class="text-xs text-slate-600">{{ __('reports.stock_state') }}<select class="form-input mt-1" name="stock_state"><option value="">{{ __('reports.all_stock') }}</option><option value="low" @selected(($filters['stock_state'] ?? '') === 'low')>{{ __('reports.low_stock') }}</option><option value="out" @selected(($filters['stock_state'] ?? '') === 'out')>{{ __('reports.out_of_stock') }}</option></select></label>
        @endif
        @if (in_array($report, ['collections', 'advances'], true))
            <label class="text-xs text-slate-600">{{ __('reports.method') }}<select class="form-input mt-1" name="method"><option value="">{{ __('reports.all_methods') }}</option>@foreach (['cash', 'bank_transfer', 'cheque'] as $method)<option value="{{ $method }}" @selected(($filters['method'] ?? '') === $method)>{{ __('reports.'.$method) }}</option>@endforeach</select></label>
        @endif
        <button class="rounded-lg bg-teal-700 px-4 py-2 text-xs font-semibold text-white">{{ __('reports.apply_filters') }}</button>
        @foreach (['customer_id' => 'Customer ID', 'employee_id' => 'Employee ID', 'supplier_id' => 'Supplier ID', 'item_id' => 'Part ID', 'category_id' => 'Category ID'] as $field => $label)
            @if (($field === 'customer_id' && ! in_array($report, ['stock','stock-movements','valuation','supplier-statement','supplier-outstanding','purchases','purchase-history','supplier-purchases','adjustments','repair-parts','pos-parts'], true)) || ($field === 'employee_id' && in_array($report,['repair','technicians','sales','cashier-sales','collections','advances','refunds'],true)) || ($field === 'supplier_id' && in_array($report,['supplier-statement','supplier-outstanding','purchases','purchase-history','supplier-purchases','stock','valuation'],true)) || (in_array($field,['item_id','category_id'],true) && in_array($report,['stock','valuation','stock-movements','repair-parts','pos-parts','adjustments','purchase-history','supplier-purchases'],true)))
            <label class="text-xs">{{ $label }}<input class="form-input" type="number" name="{{ $field }}" min="1" value="{{ $filters[$field] ?? '' }}"></label>
            @endif
        @endforeach
        <label class="text-xs">Sort<select class="form-input" name="sort"><option value="">Default</option>@foreach ($data['columns'] as $column)<option value="{{ $column['field'] }}" @selected(($filters['sort'] ?? '') === $column['field'])>{{ $column['label'] }}</option>@endforeach</select></label>
        <select class="form-input" name="direction" aria-label="Sort direction"><option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>Ascending</option><option value="desc" @selected(($filters['direction'] ?? '') === 'desc')>Descending</option></select>
        <a class="text-xs text-teal-700 underline" href="{{ route('reports.show', $report) }}">Reset</a>
    </form>

    @if ($data['summary'])
        <div class="mb-5 flex flex-wrap gap-3">
            @foreach ($data['summary'] as $label => $value)
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3"><p class="text-[10px] uppercase text-slate-500">{{ __('reports.'.$label) }}</p><p class="mt-1 text-lg font-bold text-slate-900">{{ $value }}</p></div>
            @endforeach
        </div>
    @endif
    @foreach ($data['warnings'] as $warning)
        <p class="mb-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">{{ $warning }}</p>
    @endforeach

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600"><tr>@foreach ($data['columns'] as $column)<th class="whitespace-nowrap px-4 py-3">{{ $column['label'] }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr>@foreach ($data['columns'] as $column)<td class="whitespace-nowrap px-4 py-3">{{ data_get($row, $column['field'], '') }}</td>@endforeach</tr>
                @empty
                    <tr><td class="px-4 py-10 text-center text-slate-500" colspan="{{ count($data['columns']) }}">{{ __('reports.no_rows') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
@endsection
