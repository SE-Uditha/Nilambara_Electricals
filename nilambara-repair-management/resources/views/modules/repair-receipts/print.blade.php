<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }} · {{ $receipt->receipt_number }}</title>
    <style>
        @page { size: {{ $paperSize }} {{ $orientation }}; margin: {{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #182c38; font-family: Arial, sans-serif; font-size: {{ $fontSize }}pt; line-height: 1.15; background: #eef2f4; }
        .screen-actions { max-width: 700px; margin: 16px auto; text-align: right; }
        .screen-actions a, .screen-actions button { display: inline-block; margin-left: 6px; padding: 9px 14px; border: 1px solid #cbd5e1; border-radius: 6px; background: white; color: #0f766e; font: inherit; font-size: 10pt; text-decoration: none; cursor: pointer; }
        .document { max-width: 700px; margin: auto; padding: 24px; background: white; border-top: 5px solid #0f766e; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td { vertical-align: top; overflow-wrap: anywhere; word-wrap: break-word; }
        .business { border-bottom: 1px solid #cbd5e1; padding-bottom: 10px; }
        .business h1 { font-size: 13pt; margin: 0 0 4px; color: #115e59; }
        .business p { margin: 2px 0; color: #475569; font-size: 8.5pt; }
        .logo { width: 76px; }
        .logo img { max-width: 50px; max-height: 44px; }
        .heading { margin: 8px 0; }
        .heading h2 { font-size: 13pt; margin: 0 0 4px; text-transform: uppercase; }
        .copy { color: #64748b; font-size: 8pt; }
        .reference { text-align: right; width: 42%; }
        .reference strong { color: #0f766e; font-size: 12pt; }
        .duplicate { display: inline-block; border: 2px solid #b91c1c; color: #b91c1c; padding: 3px 7px; font-weight: bold; }
        .section { margin-top: 6px; }
        .section h3 { margin: 0; padding: 4px 6px; background: #edf5f4; border-bottom: 1px solid #c7dcd8; color: #115e59; font-size: 8.5pt; text-transform: uppercase; }
        .details td { padding: 2px 6px; border-bottom: 1px solid #e2e8f0; }
        .label { color: #64748b; font-size: 7.5pt; display: block; margin-bottom: 0; }
        .value { white-space: pre-wrap; }
        .money { text-align: right; white-space: nowrap; font-weight: bold; color: #115e59; }
        .collection-note { margin: 8px 0 0; padding: 7px; border-left: 3px solid #0f766e; background: #f8fafc; font-size: 8pt; }
        .signatures { margin-top: 18px; }
        .signatures td { width: 50%; text-align: center; padding: 0 10px; }
        .signature-line { border-top: 1px solid #64748b; padding-top: 5px; font-size: 8pt; }
        .print-date { border-top: 1px solid #cbd5e1; margin-top: 8px; padding-top: 6px; color: #64748b; text-align: center; font-size: 7pt; }
        tr, .signatures, .collection-note { page-break-inside: avoid; }
        h3 { page-break-after: avoid; }
        @media print { body { background: white; } .screen-actions { display: none; } .document { max-width: none; padding: 0; } }
    </style>
</head>
<body>
    <nav class="screen-actions" aria-label="{{ __('ui.print_actions') }}">
        <a href="{{ route('repair-jobs.show', $receipt->repair_job_id) }}">{{ __('ui.back_to_jobs') }}</a>
        <button type="button" onclick="window.print()">{{ __('ui.print') }}</button>
        <a href="{{ route('repair-jobs.receipt.pdf', ['job' => $receipt->repair_job_id, 'duplicate' => $duplicate ? 1 : 0]) }}">PDF</a>
    </nav>
    <main class="document">
        <header class="business">
            <table><tr>
                @if (config('business.printing.show_logo', true) && $logoUrl)<td class="logo" style="width:20%"><img src="{{ $logoUrl }}" alt="{{ $businessName }}"></td>@endif
                <td style="width:80%"><h1>{{ $businessName }}</h1>@if ($businessAddress)<p>{{ $businessAddress }}</p>@endif @if ($businessTelephone)<p>{{ $businessTelephone }}</p>@endif</td>
            </tr></table>
        </header>
        <table class="heading"><tr>
            <td><h2>{{ $documentTitle }}</h2><span class="copy">{{ __('ui.customer_copy') }}</span>@if ($duplicate)<br><span class="duplicate">{{ $duplicateLabel }}</span>@endif</td>
            <td class="reference"><strong>{{ $receipt->receipt_number }}</strong><br><span class="copy">{{ $receipt->issued_at->timezone(config('business.timezone'))->format(config('business.date_format').' / '.config('business.time_format')) }}</span></td>
        </tr></table>
        <table class="details"><tr>
            <td><span class="label">{{ __('ui.job_reference') }}</span><strong>{{ data_get($snapshot, 'job_reference', $receipt->receipt_number) }}</strong></td>
            <td><span class="label">{{ __('ui.manual_job_number') }}</span><strong>{{ data_get($snapshot, 'manual_job_number') ?: '—' }}</strong></td>
        </tr></table>
        <section class="section">
            <h3>{{ __('ui.customer_information') }}</h3>
            <table class="details">
                <tr><td><span class="label">{{ __('ui.customer_name') }}</span><span class="value">{{ data_get($snapshot, 'customer.name') ?: '—' }}</span></td><td><span class="label">{{ __('ui.mobile_number') }}</span><span class="value">{{ data_get($snapshot, 'customer.mobile_number') ?: '—' }}</span></td></tr>
                <tr><td colspan="2"><span class="label">{{ __('ui.address') }}</span><span class="value">{{ data_get($snapshot, 'customer.address') ?: '—' }}</span></td></tr>
                @if (data_get($snapshot, 'customer.contact_person'))<tr><td colspan="2"><span class="label">{{ __('ui.contact_person') }}</span><span class="value">{{ data_get($snapshot, 'customer.contact_person') }}</span></td></tr>@endif
            </table>
        </section>
        <section class="section">
            <h3>{{ __('ui.appliance_information') }}</h3>
            <table class="details">
                <tr><td><span class="label">{{ __('ui.item_name') }}</span><span class="value">{{ data_get($snapshot, 'item.name') ?: '—' }}</span></td><td><span class="label">{{ __('ui.brand_model') }}</span><span class="value">{{ trim((string) data_get($snapshot, 'item.brand').' '.(string) data_get($snapshot, 'item.model')) ?: '—' }}</span></td></tr>
                @foreach (['description' => 'item_description', 'reported_fault' => 'reported_fault', 'accessories_received' => 'accessories_received', 'physical_condition' => 'physical_condition', 'visible_damage' => 'visible_damage'] as $key => $label)
                    @if (data_get($snapshot, 'item.'.$key))<tr><td style="width:38%"><span class="label">{{ __('ui.'.$label) }}</span></td><td class="value">{{ data_get($snapshot, 'item.'.$key) }}</td></tr>@endif
                @endforeach
            </table>
        </section>
        @if (\Brick\Math\BigDecimal::of((string) data_get($snapshot, 'advance_payment', '0.00'))->isGreaterThan(0))
        <section class="section">
            <h3>{{ __('ui.advance_payment') }}</h3>
            <table class="details"><tr><td>{{ __('ui.advance_payment') }}@if (data_get($snapshot, 'advance_payment_number'))<br><span class="label">{{ data_get($snapshot, 'advance_payment_number') }}</span>@endif</td><td class="money">{{ config('business.currency') }} {{ data_get($snapshot, 'advance_payment') }}</td></tr></table>
        </section>
        @endif
        @if (data_get($snapshot, 'special_notes'))<section class="section"><h3>{{ __('ui.special_notes') }}</h3><table class="details"><tr><td class="value">{{ data_get($snapshot, 'special_notes') }}</td></tr></table></section>@endif
        <p class="collection-note">{{ __('ui.receipt_collection_note') }}</p>
        <table class="signatures"><tr><td><div class="signature-line">{{ __('ui.customer_signature') }}</div></td><td><div class="signature-line">{{ data_get($snapshot, 'received_by') ?: '—' }}<br>{{ __('ui.received_by') }}</div></td></tr></table>
        <footer class="print-date">@if ($footerText){{ $footerText }}<br>@endif {{ __('ui.print_date') }}: {{ $printTime->format(config('business.date_format').' '.config('business.time_format')) }} · {{ config('business.timezone') }}</footer>
    </main>
@include('components.document-layout-settings')
</body>
</html>
