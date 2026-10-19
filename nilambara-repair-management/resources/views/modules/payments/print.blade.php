<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }} · {{ $payment->payment_number }}</title>
    <style>
        @page { size: {{ $paperSize }} {{ $orientation }}; margin: {{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm; }
        @font-face { font-family: Receipt; src: url("{{ $fontRegularPath }}"); font-weight: 400; }
        @font-face { font-family: Receipt; src: url("{{ $fontBoldPath }}"); font-weight: 700; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 20px; color: #172033; font: {{ $fontSize }}pt Receipt, sans-serif; }
        .receipt { max-width: 550px; margin: auto; border: 1px solid #cbd5e1; padding: 22px; }
        .header { display: flex; align-items: center; gap: 14px; border-bottom: 2px solid #0f766e; padding-bottom: 13px; }
        .logo { max-width: 75px; max-height: 55px; object-fit: contain; }
        .business { flex: 1; text-align: center; }
        .business h1 { margin: 0 0 4px; font-size: 16px; }
        .business p { margin: 2px 0; color: #475569; }
        .title { margin: 17px 0 12px; text-align: center; font-size: 16px; letter-spacing: .07em; }
        .duplicate { margin: -4px 0 12px; text-align: center; color: #b45309; font-weight: bold; }
        .row { display: flex; justify-content: space-between; gap: 10px; border-bottom: 1px solid #e2e8f0; padding: 7px 0; }
        .label { color: #64748b; }
        .value { text-align: right; font-weight: bold; }
        .amount { margin: 14px 0; border: 1px solid #99f6e4; background: #f0fdfa; padding: 12px; text-align: center; }
        .amount small { display: block; margin-bottom: 3px; color: #0f766e; }
        .amount strong { color: #134e4a; font-size: 19px; }
        .notes { margin-top: 12px; }
        .signature { margin-top: 34px; display: flex; justify-content: space-between; }
        .signature span { border-top: 1px solid #64748b; padding-top: 5px; min-width: 150px; text-align: center; }
        .footer { margin-top: 15px; color: #64748b; text-align: center; font-size: 8px; }
        @media print { body { padding: 0; } .receipt { border: 0; max-width: none; } .print-actions { display: none; } }
    </style>
</head>
<body>
    <article class="receipt">
        <header class="header">
            @if ($logoDataUri)<img class="logo" src="{{ $logoDataUri }}" alt="{{ $businessName }}">@endif
            <div class="business"><h1>{{ $businessName }}</h1><p>{{ $businessAddress }}</p><p>{{ $businessTelephone }}</p></div>
        </header>
        <h2 class="title">{{ $documentTitle }}</h2>
        @if ($duplicate)<p class="duplicate">{{ $duplicateLabel }}</p>@endif
        <div class="row"><span class="label">{{ __('payments.number') }}</span><span class="value">{{ $payment->payment_number }}</span></div>
        <div class="row"><span class="label">{{ __('payments.date') }}</span><span class="value">{{ $payment->paid_at->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }}</span></div>
        <div class="row"><span class="label">{{ __('payments.customer') }}</span><span class="value">{{ $payment->customer?->display_name ?: '—' }}</span></div>
        @if ($invoice)<div class="row"><span class="label">{{ __('payments.invoice') }}</span><span class="value">{{ $invoice->invoice_number }}</span></div>@endif
        @if ($posSale)<div class="row"><span class="label">{{ __('pos.sale_number') }}</span><span class="value">{{ $posSale->sale_number }}</span></div>@endif
        @foreach ($payment->paymentAllocations as $allocation)
            @if ($allocation->repairJob)<div class="row"><span class="label">{{ __('payments.repair_job') }}</span><span class="value">{{ $allocation->repairJob->automatic_number ?: $allocation->repairJob->job_number }}</span></div>@endif
        @endforeach
        <div class="row"><span class="label">{{ __('payments.type') }}</span><span class="value">{{ __('payments.type_'.$payment->payment_type) }}</span></div>
        <div class="row"><span class="label">{{ __('payments.method') }}</span><span class="value">{{ __('payments.method_'.$payment->method->value) }}</span></div>
        @if ($reference)<div class="row"><span class="label">{{ __('payments.reference_number') }}</span><span class="value">{{ $reference }}</span></div>@endif
        <div class="amount"><small>{{ __('payments.amount_received') }}</small><strong>{{ config('business.currency') }} {{ $payment->amount }}</strong></div>
        @if ($invoiceBalance)
            <div class="row"><span class="label">{{ __('payments.previous_paid') }}</span><span class="value">{{ config('business.currency') }} {{ $invoiceBalance['previous_paid'] }}</span></div>
            <div class="row"><span class="label">{{ __('payments.outstanding') }}</span><span class="value">{{ config('business.currency') }} {{ $invoiceBalance['outstanding'] }}</span></div>
        @endif
        <div class="row"><span class="label">{{ __('payments.received_by') }}</span><span class="value">{{ $payment->receivedByEmployee?->name ?: '—' }}</span></div>
        @if ($payment->notes)<div class="notes"><span class="label">{{ __('payments.notes') }}:</span><p>{{ $payment->notes }}</p></div>@endif
        <div class="signature"><span>{{ __('payments.authorized_signature') }}</span><span>{{ __('payments.customer_signature') }}</span></div>
        <p class="footer">{{ $footer ?: __('payments.printed_at') }} · {{ $printedAt->format(config('business.date_format').' '.config('business.time_format')) }}</p>
    </article>
    <div class="print-actions" style="text-align:center;margin-top:14px"><button type="button" onclick="window.print()">{{ __('payments.print') }}</button></div>
</body>
</html>
