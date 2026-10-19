<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }} · {{ $job->automatic_number }}</title>
    <style>
        @page { size: {{ $paperSize }} {{ $orientation }}; margin: {{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17252d; font-family: Arial, sans-serif; font-size: {{ $fontSize }}pt; line-height: 1.25; }
        .screen-actions { display: flex; gap: 8px; justify-content: flex-end; margin: 0 auto 14px; max-width: 600px; }
        .screen-actions a, .screen-actions button { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 7px; background: #fff; color: #17252d; font-size: 11px; text-decoration: none; cursor: pointer; }
        .preview { width: {{ $pageWidth }}; margin: auto; }
        .page { position: relative; width: {{ $pageWidth }}; min-height: {{ $pageHeight }}; padding: 4mm; border: 1px solid #d9e0e5; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .business { display: flex; align-items: center; gap: 6px; border-bottom: 1.5px solid #0f766e; padding-bottom: 4px; }
        .business img { max-width: 22mm; max-height: 15mm; object-fit: contain; }
        .business h1 { margin: 0; font-size: 11pt; }
        .business p { margin: 1px 0; color: #52616b; font-size: 7pt; }
        .title-row { display: flex; align-items: center; justify-content: space-between; gap: 4px; margin: 5px 0; }
        .title-row h2 { margin: 0; font-size: 10pt; letter-spacing: .05em; }
        .duplicate { border: 1px solid #b91c1c; padding: 2px 4px; color: #b91c1c; font-size: 7pt; font-weight: 700; }
        .job-no { margin: 4px 0; padding: 4px; background: #edf7f6; font-size: 9pt; font-weight: 700; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 3px 5px; font-size: 7.5pt; }
        .section { margin-top: 5px; }
        .section h3 { margin: 0 0 2px; border-bottom: 1px solid #cbd5e1; padding-bottom: 2px; font-size: 7.5pt; text-transform: uppercase; }
        .value { min-height: 4mm; white-space: pre-wrap; overflow-wrap: anywhere; }
        .writing { min-height: 17mm; border-bottom: 1px solid #94a3b8; background-image: repeating-linear-gradient(to bottom, transparent 0, transparent 5mm, #d6dee3 5mm, #d6dee3 5.2mm); }
        .writing.large { min-height: 25mm; }
        .line { display: flex; align-items: end; gap: 4px; margin: 4px 0; }
        .line strong { min-width: 24mm; font-size: 7pt; }
        .line span { flex: 1; min-height: 5mm; border-bottom: 1px solid #94a3b8; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
        .print-date { position: absolute; right: 4mm; bottom: 2mm; left: 4mm; border-top: 1px solid #cbd5e1; padding-top: 2px; color: #52616b; font-size: 6pt; }
        @media screen { .page { margin-bottom: 12px; box-shadow: 0 5px 18px #0f172a14; } }
        @media print { .screen-actions { display: none; } .preview { margin: 0; } .page { border: 0; } }
    </style>
</head>
<body>
    <nav class="screen-actions" aria-label="{{ __('ui.print_actions') }}">
        <a href="{{ route('repair-jobs.show', $job) }}">{{ __('ui.back') }}</a>
        @can('job-cards.print')<a href="{{ route('job-cards.duplicate', $job) }}">{{ __('ui.reprint_job_card') }}</a>@endcan
        <button type="button" onclick="window.print()">{{ __('ui.print') }}</button>
    </nav>
    <main class="preview">
        <article class="page">
            <header class="business">
                @if ($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $businessName }}">@endif
                <div><h1>{{ $businessName }}</h1>@if ($businessAddress)<p>{{ $businessAddress }}</p>@endif @if ($businessTelephone)<p>{{ $businessTelephone }}</p>@endif</div>
            </header>
            <div class="title-row"><h2>{{ $documentTitle }}</h2>@if ($duplicate)<span class="duplicate">{{ $duplicateLabel }}</span>@endif</div>
            <div class="job-no">{{ __('ui.job_number') }}: {{ $job->automatic_number }} <span style="font-weight:400">/ {{ $job->job_number }}</span></div>
            <div class="meta">
                <div><strong>{{ __('ui.received_date') }}:</strong> {{ $job->received_at->timezone(config('business.timezone'))->format(config('business.date_format')) }}</div>
                <div><strong>{{ __('ui.job_status') }}:</strong> {{ $job->status->value }}</div>
                <div><strong>{{ __('ui.customer') }}:</strong> {{ $job->customer->display_name }}</div>
                <div><strong>{{ __('ui.technician') }}:</strong> {{ $job->technician?->name ?: '________________' }}</div>
            </div>
            <section class="section">
                <h3>{{ __('ui.item_name') }}</h3>
                <div class="value"><strong>{{ $job->item_name }}</strong> · {{ trim(($job->brand ?? '').' '.($job->model ?? '')) ?: '—' }}</div>
                <div class="value">{{ $job->item_description ?: '—' }}</div>
            </section>
            <section class="section">
                <h3>{{ __('ui.reported_fault') }}</h3>
                <div class="value">{{ $job->reported_fault ?: '—' }}</div>
            </section>
            <section class="section">
                <h3>{{ __('ui.inspection') }}</h3>
                <div class="writing large"></div>
                <div class="two-col">
                    <div class="line"><strong>{{ __('ui.inspected_by') }}</strong><span>{{ $inspection?->inspectedByEmployee?->name }}</span></div>
                    <div class="line"><strong>{{ __('ui.inspection_date') }}</strong><span>{{ $inspection?->inspected_at?->timezone(config('business.timezone'))->format(config('business.date_format')) }}</span></div>
                </div>
            </section>
            <footer class="print-date">{{ $footerText ?: __('ui.print_date') }}: {{ $printedAt->format(config('business.date_format').' '.config('business.time_format')) }}</footer>
        </article>
        <article class="page">
            <header class="business">
                @if ($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $businessName }}">@endif
                <div><h1>{{ $businessName }}</h1><p>{{ __('ui.job_card_continued') }} · {{ $job->automatic_number }}</p></div>
            </header>
            <div class="title-row"><h2>{{ __('ui.repair_details') }}</h2>@if ($duplicate)<span class="duplicate">{{ $duplicateLabel }}</span>@endif</div>
            <div class="value">{{ $job->repair_description ?: '' }}</div>
            <div class="writing large"></div>
            <section class="section">
                <h3>{{ __('ui.parts_used') }}</h3>
                <div class="value">{{ $inspection?->parts_used }}</div>
                <div class="writing"></div>
                <div class="two-col">
                    <div class="line"><strong>{{ __('ui.quantity') }}</strong><span>{{ $inspection?->parts_quantity }}</span></div>
                    <div class="line"><strong>{{ __('ui.parts_cost') }}</strong><span>{{ $job->parts_cost ? config('business.currency').' '.$job->parts_cost : '' }}</span></div>
                </div>
            </section>
            <section class="section">
                <h3>{{ __('ui.technician_details') }}</h3>
                <div class="two-col">
                    <div class="line"><strong>{{ __('ui.repaired_by') }}</strong><span>{{ $inspection?->repairedByEmployee?->name ?: $job->technician?->name }}</span></div>
                    <div class="line"><strong>{{ __('ui.completion_date') }}</strong><span>{{ $job->completed_at?->timezone(config('business.timezone'))->format(config('business.date_format')) }}</span></div>
                </div>
            </section>
            <section class="section">
                <h3>{{ __('ui.completion_notes') }}</h3>
                <div class="value">{{ $inspection?->special_notes }}</div>
                <div class="writing"></div>
            </section>
            <section class="section"><h3>{{ __('ui.special_notes') }}</h3><div class="value">{{ $job->special_notes }}</div></section>
            <footer class="print-date">{{ $footerText ?: __('ui.print_date') }}: {{ $printedAt->format(config('business.date_format').' '.config('business.time_format')) }}</footer>
        </article>
    </main>
</body>
</html>
