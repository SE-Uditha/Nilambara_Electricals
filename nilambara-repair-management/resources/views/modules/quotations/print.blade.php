@php
    $snapshot = $snapshot ?? $quotation->document_snapshot ?? [];
    $customer = $snapshot['customer'] ?? [];
    $item = $snapshot['item'] ?? [];
    $money = function ($amount): string {
        $decimal = (string) \Brick\Math\BigDecimal::of((string) ($amount ?? '0'))
            ->toScale(2, \Brick\Math\RoundingMode::HalfUp);
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '00');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);

        return $whole.'.'.$fraction;
    };
    $preparationStatus = $quotation->preparation_status->value;
    $approvalStatus = $quotation->approval_status->value;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }} · {{ $quotation->quotation_number }}</title>
    <style>
        @font-face{font-family:NotoSinhala;src:url("{{ $fontRegularPath }}") format("truetype");font-style:normal;font-weight:400}
        @font-face{font-family:NotoSinhala;src:url("{{ $fontBoldPath }}") format("truetype");font-style:normal;font-weight:700}
        @page{size:{{ $paperSize }} portrait;margin:{{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm}
        *{box-sizing:border-box}
        body{font-family:NotoSinhala,"DejaVu Sans",sans-serif;font-size:{{ $fontSize }}pt;color:#1e293b;line-height:1.5;margin:0}
        .toolbar{display:flex;justify-content:center;gap:10px;margin:20px auto;max-width:210mm}
        .toolbar a,.toolbar button{border:0;border-radius:7px;background:#0f766e;color:white;padding:9px 14px;font-size:12px;text-decoration:none;cursor:pointer}
        .toolbar a.secondary{background:#475569}
        .page{max-width:210mm;margin:0 auto;padding:0}
        .watermark{border:2px solid #b45309;background:#fffbeb;color:#92400e;text-align:center;font-size:18pt;font-weight:700;padding:5px;margin-bottom:12px;letter-spacing:.04em}
        .superseded{border-color:#64748b;background:#f8fafc;color:#475569}
        header{display:flex;align-items:center;gap:14px;border-bottom:2px solid #0f766e;padding-bottom:13px;margin-bottom:15px}
        header img{width:76px;height:76px;object-fit:contain}
        .business h1{margin:0;font-size:18pt;color:#0f3d3b}
        .business p{margin:2px 0;color:#475569}
        .title{margin:8px 0 12px;text-align:center;font-size:17pt;letter-spacing:.08em}
        .status{display:inline-block;border-radius:99px;padding:3px 10px;background:#e2e8f0;font-size:9pt;font-weight:700}
        .status.pending{background:#fef3c7;color:#92400e}.status.approved{background:#dcfce7;color:#166534}.status.rejected{background:#fee2e2;color:#991b1b}
        .meta{display:grid;grid-template-columns:1fr 1fr;gap:7px 20px;margin-bottom:13px}
        .meta div{border-bottom:1px solid #e2e8f0;padding-bottom:4px}
        .label{display:block;font-size:8pt;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.03em}
        .value{white-space:pre-wrap}
        section{margin:12px 0;page-break-inside:avoid}
        h2{font-size:10pt;text-transform:uppercase;letter-spacing:.04em;color:#0f766e;border-bottom:1px solid #cbd5e1;padding-bottom:4px;margin:0 0 7px}
        .two-col{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        table{width:100%;border-collapse:collapse;margin:7px 0}
        th,td{border:1px solid #cbd5e1;padding:6px 7px;text-align:left;vertical-align:top}
        th{background:#f1f5f9;font-size:8pt}
        td.amount,th.amount{text-align:right;white-space:nowrap}
        .totals{width:64%;margin-left:auto}
        .totals td{border:0;border-bottom:1px solid #e2e8f0}
        .grand-total td{border-top:2px solid #0f766e;font-size:12pt;font-weight:700;color:#0f3d3b}
        .signature{display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-top:28px}
        .sign-line{padding-top:25px;border-top:1px solid #475569;text-align:center;font-size:8pt}
        footer{margin-top:15px;border-top:1px solid #cbd5e1;padding-top:6px;text-align:center;font-size:8pt;color:#64748b}
        .muted{color:#64748b}
        @media screen{body{background:#e2e8f0}.page{background:white;max-width:210mm;min-height:297mm;padding:18mm 16mm;box-shadow:0 4px 24px #0f172a20;margin-bottom:25px}}
        @media print{.toolbar{display:none}.page{max-width:none;min-height:0;padding:0}a{color:inherit;text-decoration:none}thead{display:table-header-group}tr{page-break-inside:avoid}}
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="{{ __('ui.print_actions') }}">
        <a class="secondary" href="{{ route('quotations.show', $quotation) }}">{{ __('ui.back') }}</a>
        @can('print', $quotation)<a href="{{ route('quotations.pdf', ['quotation' => $quotation, 'paper' => $paperSize]) }}">{{ __('ui.download_pdf') }} · {{ $paperSize }}</a>@endcan
        <button type="button" onclick="window.print()">{{ __('ui.print') }}</button>
    </nav>
    <main class="page">
        @if ($duplicate)<div class="watermark">{{ $duplicateLabel }}</div>@endif
        @if ($preparationStatus === 'draft')
            <div class="watermark">{{ __('ui.draft_not_approved') }}</div>
        @elseif ($preparationStatus === 'superseded')
            <div class="watermark superseded">{{ __('ui.preparation_superseded') }}</div>
        @elseif ($preparationStatus === 'cancelled')
            <div class="watermark superseded">{{ __('ui.preparation_cancelled') }}</div>
        @endif
        <header>
            @if ($logoDataUri)<img src="{{ $logoDataUri }}" alt="{{ $businessName }} logo">@endif
            <div class="business">
                <h1>{{ $businessName }}</h1>
                @if ($businessAddress)<p>{{ $businessAddress }}</p>@endif
                @if ($businessTelephone)<p>{{ $businessTelephone }}</p>@endif
            </div>
        </header>
        <h1 class="title">{{ $documentTitle }}</h1>
        <div class="meta">
            <div><span class="label">{{ __('ui.quotation_number') }}</span><span class="value">{{ $snapshot['quotation_number'] ?? $quotation->quotation_number }}@if (($snapshot['revision'] ?? $quotation->revision) > 1) · v{{ $snapshot['revision'] ?? $quotation->revision }}@endif</span></div>
            <div><span class="label">{{ __('ui.preparation_status') }}</span><span class="status">{{ __('ui.preparation_'.$preparationStatus) }}</span></div>
            <div><span class="label">{{ __('ui.quotation_date') }}</span><span class="value">{{ \Illuminate\Support\Carbon::parse($snapshot['quotation_date'] ?? $quotation->quotation_date)->format(config('business.date_format')) }}</span></div>
            <div><span class="label">{{ __('ui.valid_until') }}</span><span class="value">{{ ! empty($snapshot['valid_until']) ? \Illuminate\Support\Carbon::parse($snapshot['valid_until'])->format(config('business.date_format')) : '—' }}</span></div>
            <div><span class="label">{{ __('ui.job_number') }}</span><span class="value">{{ $snapshot['job_number'] ?? $quotation->repairJob->job_number }}</span></div>
            <div><span class="label">{{ __('ui.customer_approval_status') }}</span><span class="status {{ $approvalStatus }}">{{ __('ui.approval_'.$approvalStatus) }}</span></div>
        </div>
        <div class="two-col">
            <section>
                <h2>{{ __('ui.customer_information') }}</h2>
                <div><span class="label">{{ __('ui.customer') }}</span><span class="value">{{ $customer['name'] ?? $quotation->customer->display_name }}</span></div>
                <div><span class="label">{{ __('ui.address') }}</span><span class="value">{{ $customer['address'] ?? $quotation->customer->address }}</span></div>
                <div><span class="label">{{ __('ui.contact_number') }}</span><span class="value">{{ $customer['mobile_number'] ?? $quotation->customer->mobile_number }}</span></div>
            </section>
            <section>
                <h2>{{ __('ui.repair_job_information') }}</h2>
                <div><span class="label">{{ __('ui.item_name') }}</span><span class="value">{{ $item['name'] ?? $quotation->repairJob->item_name }}</span></div>
                <div><span class="label">{{ __('ui.brand_model') }}</span><span class="value">{{ trim(($item['brand'] ?? $quotation->repairJob->brand ?? '').' '.($item['model'] ?? $quotation->repairJob->model ?? '')) ?: '—' }}</span></div>
                <div><span class="label">{{ __('ui.item_description') }}</span><span class="value">{{ $item['description'] ?? $quotation->repairJob->item_description ?: '—' }}</span></div>
            </section>
        </div>
        <section>
            <h2>{{ __('ui.reported_fault') }}</h2>
            <div class="value">{{ $item['reported_fault'] ?? $quotation->repairJob->reported_fault ?: '—' }}</div>
        </section>
        @if (! empty($item['inspection_summary']) || ! empty($item['technical_information']))
            <section><h2>{{ __('ui.inspection_summary') }}</h2><div class="value">{{ $item['inspection_summary'] ?? '' }}@if (! empty($item['technical_information'])) · {{ $item['technical_information'] }}@endif</div></section>
        @endif
        <section>
            <h2>{{ __('ui.proposed_repair_details') }}</h2>
            <div class="value">{{ $snapshot['proposed_repair_description'] ?? $quotation->proposed_repair_description }}</div>
        </section>
        <section>
            <h2>{{ __('ui.spare_parts_estimate') }}</h2>
            <table>
                <thead><tr><th>{{ __('ui.part_description') }}</th><th>{{ __('ui.quantity') }}</th><th class="amount">{{ __('ui.unit_price_lkr') }}</th><th class="amount">{{ __('ui.line_total') }}</th></tr></thead>
                <tbody>
                    @foreach (($snapshot['items'] ?? []) as $line)
                        <tr><td>{{ $line['description'] }}</td><td>{{ $line['quantity'] }}</td><td class="amount">LKR {{ $money($line['unit_price']) }}</td><td class="amount">LKR {{ $money($line['line_total']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <table class="totals">
                <tbody>
                    <tr><td>{{ __('ui.parts_total') }}</td><td class="amount">LKR {{ $money($snapshot['parts_total'] ?? $quotation->parts_total) }}</td></tr>
                    <tr><td>{{ __('ui.labour_charge') }}</td><td class="amount">LKR {{ $money($snapshot['labour_charge'] ?? $quotation->labour_charge) }}</td></tr>
                    <tr class="grand-total"><td>{{ __('ui.quotation_total') }}</td><td class="amount">LKR {{ $money($snapshot['quotation_total'] ?? $quotation->total) }}</td></tr>
                </tbody>
            </table>
        </section>
        @if (! empty($snapshot['terms_and_conditions'] ?? $quotation->terms_and_conditions))
            <section><h2>{{ __('ui.terms_and_conditions') }}</h2><div class="value">{{ $snapshot['terms_and_conditions'] ?? $quotation->terms_and_conditions }}</div></section>
        @endif
        <div class="signature">
            <div class="sign-line">{{ __('ui.customer_contact_name') }} / {{ __('ui.customer_signature') }}</div>
            <div class="sign-line">{{ __('ui.prepared_by') }}: {{ $snapshot['prepared_by'] ?? $quotation->createdByUser?->name }}</div>
        </div>
        <footer>{{ __('ui.quotation_print_footer') }} · {{ __('ui.print_date') }}: {{ $printedAt->format(config('business.date_format').' '.config('business.time_format')) }}</footer>
    </main>
</body>
</html>
