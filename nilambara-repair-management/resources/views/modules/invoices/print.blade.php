@php
    $money = fn ($value) => \Brick\Math\BigDecimal::of((string) ($value ?? '0.00'))->toScale(2);
    $unitPrice = fn ($value) => \Brick\Math\BigDecimal::of((string) ($value ?? '0.0000'))->toScale(4);
    $jobs = collect($document['jobs'] ?? []);
    $payments = $balance;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $invoice->invoice_number }} · {{ $documentTitle }}</title>
    <style>
        @font-face{font-family:Noto;src:url('{{ $fontRegularPath }}')}@font-face{font-family:Noto;src:url('{{ $fontBoldPath }}');font-weight:700}
        @page{size:{{ $paperSize }} {{ $orientation }};margin:{{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm}
        *{box-sizing:border-box}body{font-family:"DejaVu Sans",Noto,sans-serif;font-size:{{ $fontSize }}pt;line-height:1.45;color:#172033;margin:0}
        .toolbar{display:flex;justify-content:flex-end;gap:8px;margin:12px auto;max-width:148mm}.toolbar a,.toolbar button{border:1px solid #cbd5e1;border-radius:6px;background:#fff;color:#0f766e;padding:7px 10px;text-decoration:none;font:inherit;font-size:9pt;cursor:pointer}
        .invoice{max-width:{{ $paperSize === 'A4' ? '190mm' : ($paperSize === 'A6' ? '95mm' : '148mm') }};margin:auto}.header{display:flex;align-items:center;gap:12px;border-bottom:2px solid #0f766e;padding-bottom:10px}.logo{max-width:58px;max-height:58px;object-fit:contain}.business h1{font-size:16pt;margin:0;color:#0f766e}.business p{margin:2px 0;color:#475569;font-size:8.5pt}.doc-head{display:flex;justify-content:space-between;align-items:flex-start;margin:12px 0}.doc-head h2{font-size:14pt;margin:0}.duplicate{border:2px solid #b91c1c;color:#b91c1c;padding:4px 7px;font-weight:700;font-size:9pt;transform:rotate(-3deg)}
        .meta{display:grid;grid-template-columns:1fr 1fr;gap:7px 14px;margin:8px 0 12px}.label{display:block;font-size:7.5pt;color:#64748b}.value{display:block;font-weight:600;overflow-wrap:anywhere}.section-title{font-weight:700;font-size:9pt;color:#0f766e;margin:12px 0 5px}
        table{width:100%;border-collapse:collapse;table-layout:fixed}thead{display:table-header-group}th{background:#f1f5f9;text-align:left;font-weight:700}th,td{border:1px solid #cbd5e1;padding:5px;vertical-align:top;overflow-wrap:anywhere}.amount{text-align:right;white-space:nowrap;width:27%}.job-heading{background:#ecfeff;font-weight:700;page-break-after:avoid}.job-lines tr{page-break-inside:avoid}.totals{margin:10px 0 0 auto;width:70%}.totals td{border:0;border-bottom:1px solid #e2e8f0;padding:4px}.totals .grand{border-top:2px solid #0f766e;font-size:11pt;font-weight:700}.summary{display:grid;grid-template-columns:1fr 1fr;gap:5px 12px;margin-top:10px}.summary div{border-bottom:1px solid #e2e8f0;padding:3px 0}.signatures{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:28px}.signature{border-top:1px solid #475569;padding-top:4px;text-align:center;font-size:8pt}.footer{margin-top:12px;border-top:1px solid #cbd5e1;padding-top:6px;text-align:center;color:#64748b;font-size:7.5pt}
        @if ($pdfOutput ?? false)
        .toolbar{display:none}.value{font-weight:700}body{line-height:1.15}.header{display:block;min-height:42px;padding-bottom:7px}.logo{float:left;max-width:45px;max-height:45px;margin-right:10px}.business h1{font-size:14pt}.doc-head{display:block;margin:8px 0}.doc-head h2{font-size:12pt;margin-bottom:5px}.meta{display:block;margin:6px 0}.meta>div{display:inline-block;width:48%;vertical-align:top;margin-bottom:4px}.summary{display:block;margin-top:6px}.summary>div{display:inline-block;width:48%;vertical-align:top}.section-title{margin:7px 0 4px}th,td{padding:3px}.totals{margin-top:7px}.signatures{display:block;margin-top:20px}.signature{display:inline-block;width:46%;margin-right:3%;vertical-align:top}.footer{margin-top:9px}
        @endif
        @media print{body{font-size:{{ $fontSize }}pt}.toolbar{display:none}.invoice{max-width:none}.job-lines tr{page-break-inside:avoid}}
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="Invoice actions">
        @can('print', $invoice)<a href="{{ route('invoices.duplicate', $invoice) }}">{{ __('invoices.duplicate_print') }}</a>@endcan
        @can('export', $invoice)<a href="{{ route('invoices.pdf', $invoice) }}">{{ __('invoices.export_pdf') }}</a>@endcan
        <button type="button" onclick="window.print()">{{ __('invoices.print') }}</button>
    </nav>
    <main class="invoice">
        @if ($duplicate)<div style="text-align:center;margin-bottom:8px;color:#b91c1c;font-weight:bold">{{ $duplicateLabel }}</div>@endif
        <header class="header">
            @if ($logoDataUri)<img class="logo" src="{{ $logoDataUri }}" alt="">@endif
            <div class="business">
                <h1>{{ $businessName }}</h1>
                @if ($businessAddress)<p>{{ $businessAddress }}</p>@endif
                @if ($businessTelephone)<p>{{ $businessTelephone }}</p>@endif
            </div>
        </header>
        <div class="doc-head">
            <h2>{{ $documentTitle }}</h2>
            <div class="meta" style="margin:0">
                <div><span class="label">{{ __('invoices.invoice_number') }}</span><span class="value">{{ $document['invoice_number'] ?? $invoice->invoice_number }}</span></div>
                <div><span class="label">{{ __('invoices.invoice_date') }}</span><span class="value">{{ $document['invoice_date'] ?? $invoice->invoice_date?->toDateString() }}</span></div>
            </div>
        </div>

        <p class="section-title">{{ __('invoices.customer_details') }}</p>
        <div class="meta">
            <div><span class="label">{{ __('invoices.customer') }}</span><span class="value">{{ data_get($document, 'customer.name', $invoice->customer->display_name) }}</span></div>
            <div><span class="label">{{ __('invoices.mobile') }}</span><span class="value">{{ data_get($document, 'customer.mobile_number', $invoice->customer->mobile_number) }}</span></div>
            <div style="grid-column:1 / -1"><span class="label">{{ __('invoices.address') }}</span><span class="value">{{ data_get($document, 'customer.address', $invoice->customer->address) ?: '—' }}</span></div>
        </div>

        <p class="section-title">{{ __('invoices.job_billing') }}</p>
        <table>
            <thead><tr><th>{{ __('invoices.job_number') }} / {{ __('invoices.description_label') }}</th><th class="amount">{{ __('invoices.job_amount') }}</th></tr></thead>
            <tbody class="job-lines">
                @foreach ($jobs as $job)
                    <tr class="job-heading"><td>{{ $job['job_number'] ?? '—' }} · {{ $job['item_name'] ?? '' }}<br>{{ $job['repair_description'] ?? '' }}</td><td class="amount">{{ config('business.currency') }} {{ $money($job['job_amount'] ?? '0.00') }}</td></tr>
                    @foreach ($job['lines'] ?? [] as $line)
                        <tr><td>{{ $line['description'] }} <span style="color:#64748b">({{ $line['quantity'] }} × {{ $unitPrice($line['unit_price']) }})</span></td><td class="amount">{{ config('business.currency') }} {{ $money($line['line_total']) }}</td></tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tbody>
                <tr><td>{{ __('invoices.subtotal') }}</td><td class="amount">{{ config('business.currency') }} {{ $money($document['subtotal'] ?? $invoice->subtotal) }}</td></tr>
                <tr><td>{{ __('invoices.discount') }}</td><td class="amount">{{ config('business.currency') }} {{ $money($document['discount_amount'] ?? $invoice->discount_amount) }}</td></tr>
                <tr class="grand"><td>{{ __('invoices.invoice_total') }}</td><td class="amount">{{ config('business.currency') }} {{ $money($document['total'] ?? $invoice->total) }}</td></tr>
            </tbody>
        </table>

        <div class="summary">
            <div><span class="label">{{ __('invoices.advance_paid') }}</span><span class="value">{{ config('business.currency') }} {{ $money($payments['advance_paid']) }}</span></div>
            <div><span class="label">{{ __('invoices.previous_payments') }}</span><span class="value">{{ config('business.currency') }} {{ $money($payments['previous_payments']) }}</span></div>
            <div><span class="label">{{ __('invoices.current_payment') }}</span><span class="value">{{ config('business.currency') }} {{ $money($payments['current_payment']) }}</span></div>
            <div><span class="label">{{ __('invoices.outstanding_balance') }}</span><span class="value">{{ config('business.currency') }} {{ $money($payments['outstanding']) }}</span></div>
        </div>
        <div class="meta" style="margin-top:10px">
            <div><span class="label">{{ __('invoices.payment_terms') }}</span><span class="value">{{ __('invoices.'.($document['payment_terms'] ?? 'cash')) }}</span></div>
            @if (! empty($document['due_date']))<div><span class="label">{{ __('invoices.due_date') }}</span><span class="value">{{ $document['due_date'] }}</span></div>@endif
            <div><span class="label">{{ __('invoices.payment_method') }}</span><span class="value">{{ ! empty($document['payment_method']) ? __('invoices.'.$document['payment_method']) : '—' }}</span></div>
            <div><span class="label">{{ __('invoices.cashier_name') }}</span><span class="value">{{ $document['cashier'] ?? $invoice->cashierEmployee->name }}</span></div>
            @if (!empty($document['collected_by']))<div><span class="label">{{ __('invoices.collected_by') }}</span><span class="value">{{ $document['collected_by'] ?: '—' }}</span></div>@endif
            @if (!empty($document['vehicle_number']))<div><span class="label">{{ __('invoices.vehicle_number') }}</span><span class="value">{{ $document['vehicle_number'] ?: '—' }}</span></div>@endif
            @if (!empty($document['delivery_at']))<div><span class="label">{{ __('invoices.delivery_date') }}</span><span class="value">{{ $document['delivery_at'] ?: '—' }}</span></div>@endif
        </div>
        @if (! empty($document['special_notes']))<p class="section-title">{{ __('invoices.special_notes') }}</p><p style="white-space:pre-line">{{ $document['special_notes'] }}</p>@endif
        <div class="signatures"><div class="signature">{{ __('invoices.customer_signature') }}</div><div class="signature">{{ __('invoices.authorized_signature') }}</div></div>
        <footer class="footer">{{ $footer ?: __('invoices.'.$payments['payment_status']) }} · {{ $printedAt->format(config('business.date_format').' '.config('business.time_format')) }}</footer>
    </main>
@include('components.document-layout-settings')
</body>
</html>
