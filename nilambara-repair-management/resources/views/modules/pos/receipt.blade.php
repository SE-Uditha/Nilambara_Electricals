<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }} · {{ $sale->sale_number }}</title>
    <style>
        @page { size: {{ $paperSize === 'thermal' ? $thermalWidth.'mm 280mm' : $paperSize.' '.$orientation }}; margin: {{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: {{ $paperSize === 'thermal' ? '8' : $fontSize }}pt; }
        .receipt { max-width: {{ $paperSize === 'thermal' ? $thermalWidth.'mm' : '190mm' }}; margin: auto; }
        header { border-bottom: 1px dashed #64748b; text-align: center; padding-bottom: 8px; }
        .logo { max-width: 48mm; max-height: 20mm; object-fit: contain; }
        h1 { margin: 4px 0; font-size: 1.4em; }
        p { margin: 3px 0; }
        h2 { margin: 10px 0 7px; text-align: center; font-size: 1.15em; }
        .duplicate { font-weight: bold; text-align: center; }
        .meta { margin: 8px 0; }
        .meta div, .total-row { display: flex; justify-content: space-between; gap: 6px; padding: 3px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 2px; border-bottom: 1px dotted #94a3b8; }
        th { text-align: left; }
        .num { text-align: right; white-space: nowrap; }
        .grand { border-top: 1px dashed #334155; font-weight: bold; font-size: 1.2em; margin-top: 4px; padding-top: 6px; }
        footer { margin-top: 12px; text-align: center; }
        .actions { margin-top: 12px; display: flex; justify-content: center; gap: 8px; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <article class="receipt">
        <header>
            @if ($logoDataUri)<img class="logo" src="{{ $logoDataUri }}" alt="{{ $businessName }}">@endif
            <h1>{{ $businessName }}</h1><p>{{ $businessAddress }}</p><p>{{ $businessTelephone }}</p>
        </header>
        <h2>{{ $documentTitle }}</h2>
        @if ($duplicate)<p class="duplicate">{{ $duplicateLabel }}</p>@endif
        <div class="meta"><div><span>{{ __('pos.sale_number') }}</span><strong>{{ $sale->sale_number }}</strong></div><div><span>{{ __('payments.date') }}</span><span>{{ $sale->sale_at->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }}</span></div><div><span>{{ __('pos.cashier') }}</span><span>{{ $snapshot['cashier'] ?? $sale->cashierEmployee?->name }}</span></div><div><span>{{ __('pos.customer') }}</span><span>{{ $snapshot['customer']['name'] ?? __('pos.walk_in') }}</span></div></div>
        <table><thead><tr><th>{{ __('pos.product') }}</th><th class="num">{{ __('pos.quantity') }}</th><th class="num">{{ __('pos.unit_price') }}</th><th class="num">{{ __('pos.line_total') }}</th></tr></thead><tbody>
            @foreach ($sale->items as $item)<tr><td>{{ $item->description }}</td><td class="num">{{ $item->quantity }}</td><td class="num">{{ $item->unit_price }}</td><td class="num">{{ $item->line_total }}</td></tr>@endforeach
        </tbody></table>
        <div class="total-row"><span>{{ __('pos.subtotal') }}</span><span>{{ config('business.currency') }} {{ $sale->subtotal }}</span></div><div class="total-row"><span>{{ __('pos.discount') }}</span><span>{{ config('business.currency') }} {{ $sale->discount_amount }}</span></div><div class="total-row grand"><span>{{ __('pos.total') }}</span><span>{{ config('business.currency') }} {{ $sale->total }}</span></div>
        <div class="meta"><div><span>{{ __('pos.payment_method') }}</span><span>{{ $sale->payment_method ? __('payments.method_'.$sale->payment_method) : '—' }}</span></div>@if ($payment)<div><span>{{ __('pos.payment_reference') }}</span><span>{{ $payment->payment_number }}</span></div>@endif
            @if ($sale->payment_method === 'cash')<div><span>{{ __('pos.amount_tendered') }}</span><span>{{ config('business.currency') }} {{ $sale->amount_tendered }}</span></div><div><span>{{ __('pos.change_due') }}</span><span>{{ config('business.currency') }} {{ $sale->change_due }}</span></div>@endif</div>
        @if ($sale->payment_method === 'cheque')<p>{{ __('pos.cheque_not_cleared') }}</p>@endif
        <footer><p>{{ $footer ?: __('pos.thank_you') }}</p><p>{{ __('payments.printed_at') }} {{ $printedAt->format(config('business.date_format').' '.config('business.time_format')) }}</p></footer>
    </article>
    <div class="actions"><button type="button" onclick="window.print()">{{ __('pos.print_receipt') }}</button><a href="{{ route('pos.sales.reprint', ['sale' => $sale, 'paper' => $paperSize]) }}">{{ __('payments.print') }} · {{ __('pos.duplicate_copy') }}</a></div>
@include('components.document-layout-settings')
</body>
</html>
