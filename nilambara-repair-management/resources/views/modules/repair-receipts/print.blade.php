<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }} · {{ $receipt->receipt_number }}</title>
    <style>
        @page { size: {{ $paperSize }} {{ $orientation }}; margin: {{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17252d; font-family: Arial, sans-serif; font-size: {{ $fontSize }}pt; line-height: 1.35; }
        .screen-actions { display: flex; gap: 10px; justify-content: flex-end; margin: 0 auto 16px; max-width: 700px; }
        .screen-actions a, .screen-actions button { padding: 9px 14px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #17252d; font-size: 12px; text-decoration: none; cursor: pointer; }
        .document { max-width: 700px; margin: auto; border: 1px solid #d9e0e5; padding: 20px; }
        .business { display: flex; align-items: center; gap: 14px; border-bottom: 2px solid #0f766e; padding-bottom: 12px; }
        .business img { max-width: 72px; max-height: 55px; object-fit: contain; }
        .business h1 { margin: 0 0 3px; font-size: 16pt; }
        .business p { margin: 2px 0; color: #52616b; }
        .doc-title { display: flex; justify-content: space-between; align-items: center; margin: 13px 0; }
        .doc-title h2 { margin: 0; font-size: 13pt; letter-spacing: .04em; }
        .duplicate { padding: 5px 9px; border: 2px solid #b91c1c; color: #b91c1c; font-weight: 700; letter-spacing: .08em; }
        .reference { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 18px; padding: 10px; background: #f1f5f6; font-size: 10pt; }
        .section { margin-top: 13px; }
        .section h3 { margin: 0 0 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; font-size: 9pt; text-transform: uppercase; letter-spacing: .06em; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 18px; }
        .field { min-height: 17px; white-space: pre-wrap; overflow-wrap: anywhere; }
        .field strong { display: inline-block; min-width: 112px; }
        .wide { grid-column: 1 / -1; }
        .signature { display: flex; justify-content: space-between; gap: 24px; margin-top: 24px; }
        .signature div { width: 48%; border-top: 1px solid #475569; padding-top: 4px; text-align: center; }
        .print-date { margin-top: 13px; border-top: 1px solid #cbd5e1; padding-top: 6px; color: #52616b; font-size: 8pt; }
        @media print { .screen-actions { display: none; } .document { max-width: none; border: 0; padding: 0; } }
    </style>
</head>
<body>
    <nav class="screen-actions" aria-label="{{ __('ui.print_actions') }}">
        <a href="{{ route('repair-receipts.show', $receipt) }}">{{ __('ui.back') }}</a>
        <button type="button" onclick="window.print()">{{ __('ui.print') }}</button>
    </nav>
    <main class="document">
        <header class="business">
            @if (config('business.printing.show_logo', true) && $logoUrl)<img src="{{ $logoUrl }}" alt="{{ $businessName }}">@endif
            <div>
                <h1>{{ $businessName }}</h1>
                @if ($businessAddress)<p>{{ $businessAddress }}</p>@endif
                @if ($businessTelephone)<p>{{ $businessTelephone }}</p>@endif
            </div>
        </header>
        <div class="doc-title">
            <h2>{{ $documentTitle }}</h2>
            @if ($duplicate)<span class="duplicate">{{ $duplicateLabel }}</span>@endif
        </div>
        <div class="reference">
            <div><strong>{{ __('ui.job_reference') }}:</strong> {{ data_get($snapshot, 'job_reference', $receipt->receipt_number) }}</div>
            <div><strong>{{ __('ui.manual_job_number') }}:</strong> {{ data_get($snapshot, 'manual_job_number', '—') }}</div>
            <div><strong>{{ __('ui.received_date') }}:</strong> {{ $receipt->issued_at->timezone(config('business.timezone'))->format(config('business.date_format')) }}</div>
            <div><strong>{{ __('ui.received_time') }}:</strong> {{ $receipt->issued_at->timezone(config('business.timezone'))->format(config('business.time_format')) }}</div>
        </div>
        <section class="section">
            <h3>{{ __('ui.customer_information') }}</h3>
            <div class="grid">
                <div class="field wide"><strong>{{ __('ui.customer_name') }}:</strong> {{ data_get($snapshot, 'customer.name', '—') }}</div>
                <div class="field wide"><strong>{{ __('ui.address') }}:</strong> {{ data_get($snapshot, 'customer.address', '—') }}</div>
                <div class="field"><strong>{{ __('ui.mobile_number') }}:</strong> {{ data_get($snapshot, 'customer.mobile_number', '—') }}</div>
                <div class="field"><strong>{{ __('ui.contact_person') }}:</strong> {{ data_get($snapshot, 'customer.contact_person') ?: '—' }}</div>
            </div>
        </section>
        <section class="section">
            <h3>{{ __('ui.appliance_information') }}</h3>
            <div class="grid">
                <div class="field"><strong>{{ __('ui.item_name') }}:</strong> {{ data_get($snapshot, 'item.name', '—') }}</div>
                <div class="field"><strong>{{ __('ui.brand_model') }}:</strong> {{ trim((string) data_get($snapshot, 'item.brand').' '.(string) data_get($snapshot, 'item.model')) ?: '—' }}</div>
                <div class="field wide"><strong>{{ __('ui.item_description') }}:</strong> {{ data_get($snapshot, 'item.description') ?: '—' }}</div>
                <div class="field wide"><strong>{{ __('ui.reported_fault') }}:</strong> {{ data_get($snapshot, 'item.reported_fault') ?: '—' }}</div>
                <div class="field wide"><strong>{{ __('ui.accessories_received') }}:</strong> {{ data_get($snapshot, 'item.accessories_received') ?: '—' }}</div>
                <div class="field wide"><strong>{{ __('ui.physical_condition') }}:</strong> {{ data_get($snapshot, 'item.physical_condition') ?: '—' }}</div>
                <div class="field wide"><strong>{{ __('ui.visible_damage') }}:</strong> {{ data_get($snapshot, 'item.visible_damage') ?: '—' }}</div>
            </div>
        </section>
        <section class="section">
            <h3>{{ __('ui.payment_information') }}</h3>
            <div class="grid">
                <div class="field"><strong>{{ __('ui.advance_payment') }}:</strong> {{ config('business.currency') }} {{ data_get($snapshot, 'advance_payment', '0.00') }}</div>
                @if (data_get($snapshot, 'advance_payment_number'))<div class="field"><strong>{{ __('payments.number') }}:</strong> {{ data_get($snapshot, 'advance_payment_number') }}</div>@endif
                <div class="field"><strong>{{ __('ui.labor_cost') }}:</strong> {{ config('business.currency') }} {{ data_get($snapshot, 'labor_cost', '0.00') }}</div>
            </div>
        </section>
        <section class="section">
            <div class="grid">
                <div class="field"><strong>{{ __('ui.received_by') }}:</strong> {{ data_get($snapshot, 'received_by', '—') }}</div>
                <div class="field wide"><strong>{{ __('ui.special_notes') }}:</strong> {{ data_get($snapshot, 'special_notes') ?: '—' }}</div>
            </div>
        </section>
        <div class="signature">
            <div>{{ __('ui.customer_signature') }}</div>
            <div>{{ __('ui.received_by') }}</div>
        </div>
        <footer class="print-date">{{ $footerText ?: __('ui.print_date') }}: {{ $printTime->format(config('business.date_format').' '.config('business.time_format')) }} · {{ config('business.timezone') }}</footer>
    </main>
@include('components.document-layout-settings')
</body>
</html>
