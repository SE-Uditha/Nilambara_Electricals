<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('ui.customer_statement') }} · {{ $customer->customer_code }}</title>
    <style>
        @page { margin: 26px; }
        body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { margin: 0; font-size: 17px; }
        p { margin: 4px 0; }
        .header { border-bottom: 2px solid #0f766e; padding-bottom: 10px; margin-bottom: 14px; }
        .summary { display: flex; gap: 28px; margin: 15px 0; }
        .summary strong { display: block; margin-top: 4px; font-size: 12px; }
        .label { color: #64748b; font-size: 8px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; color: #475569; text-align: left; text-transform: uppercase; font-size: 8px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 6px 5px; }
        .right { text-align: right; white-space: nowrap; }
        .footer { margin-top: 14px; color: #64748b; text-align: right; }
    </style>
</head>
<body>
    <header class="header">
        <h1>{{ config('business.name') }}</h1>
        <p>{{ config('business.address') }} · {{ config('business.contact_number') }}</p>
        <p><strong>{{ __('ui.customer_statement') }}</strong> · {{ $customer->customer_code }} · {{ $customer->display_name }} · {{ $customer->mobile_number }} · {{ $customer->address }}</p>
        @if (! empty($filters['date_from']) || ! empty($filters['date_to']))<p>{{ __('payments.statement_period') }}: {{ $filters['date_from'] ?? '—' }} – {{ $filters['date_to'] ?? '—' }}</p>@endif
    </header>
    <div class="summary">
        <div><span class="label">{{ __('payments.opening_balance') }}</span><strong>{{ config('business.currency') }} {{ $customer->opening_balance }}</strong></div>
        <div><span class="label">{{ __('payments.balance_brought_forward') }}</span><strong>{{ config('business.currency') }} {{ $openingBalance }}</strong></div>
        <div><span class="label">{{ __('payments.closing_balance') }}</span><strong>{{ config('business.currency') }} {{ $closingBalance }}</strong></div>
    </div>
    <table>
        <thead><tr><th>{{ __('ui.date') }}</th><th>{{ __('ui.type') }}</th><th>{{ __('ui.reference') }}</th><th>{{ __('ui.description') }}</th><th class="right">{{ __('payments.advance_applied') }}</th><th class="right">{{ __('ui.amount') }}</th><th class="right">{{ __('payments.running_balance') }}</th></tr></thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr><td>{{ $entry->occurred_at->timezone(config('business.timezone'))->format(config('business.date_format')) }}</td><td>{{ __('payments.entry_type_'.$entry->entry_type) }}</td><td>{{ $entry->reference_number ?: '—' }}</td><td>{{ $entry->description ?: '—' }}</td><td class="right">{{ $entry->advance_applied !== '0.00' ? config('business.currency').' '.$entry->advance_applied : '—' }}</td><td class="right">{{ config('business.currency') }} {{ $entry->amount }}</td><td class="right">{{ config('business.currency') }} {{ $entry->running_balance }}</td></tr>
            @empty
                <tr><td colspan="7">{{ __('ui.no_statement_entries') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="footer">{{ __('payments.printed_at') }} {{ $printedAt->format(config('business.date_format').' '.config('business.time_format')) }}</p>
</body>
</html>
