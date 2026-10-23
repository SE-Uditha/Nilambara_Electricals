<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }} · {{ $job->automatic_number }}</title>
    <style>
        @page { size: A5 portrait; margin: {{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17252d; font-family: Arial, sans-serif; font-size: {{ $fontSize }}pt; line-height: 1.2; }
        .screen-actions { display: flex; gap: 8px; justify-content: flex-end; margin: 0 auto 14px; max-width: 600px; }
        .screen-actions a, .screen-actions button { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 7px; background: #fff; color: #17252d; font-size: 11px; text-decoration: none; cursor: pointer; }
        .preview { width: {{ $pageWidth }}; margin: auto; }
        .page { width: {{ $pageWidth }}; padding: 2mm; border: 1px solid #17252d; page-break-after: auto; }
        .heading { position: relative; margin: -2mm -2mm 2mm; padding: 2mm; background: #203f85; color: #fff; text-align: center; }
        .heading h1 { margin: 0; font-size: 12pt; }
        .heading p { margin: 1mm 0 0; font-size: 6pt; }
        .heading img { position: absolute; top: 1mm; left: 2mm; max-width: 18mm; max-height: 9mm; object-fit: contain; }
        .duplicate { display: inline-block; margin-top: 1mm; border: 1px solid #b91c1c; padding: 1px 4px; color: #b91c1c; font-size: 7pt; font-weight: 700; }
        .instruction { margin: 0 0 1.5mm; font-size: 6.5pt; text-align: center; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .details th, .details td, .signoffs th, .signoffs td, .amounts th, .amounts td, .handover th, .handover td { border: 1px solid #263238; padding: .7mm; text-align: left; vertical-align: middle; }
        .details th, .amounts th, .handover th { background: #f0f0f0; }
        .details th { width: 25%; font-size: 8pt; }
        .details td { height: 4.5mm; overflow-wrap: anywhere; font-size: 8pt; }
        .section { margin-top: .5mm; }
        .section h2 { margin: 0; padding: 1mm 1.5mm; background: #d1d1d1; font-size: 8pt; }
        .writing td { height: 5mm; border: 1px solid #263238; }
        .writing.issues td { height: 5mm; }
        .writing.repair td { height: 5mm; }
        .writing.notes td { height: 5mm; }
        .signoffs th { width: 23%; }
        .signoffs td { height: 5mm; }
        .amounts th { width: 70%; height: 4.5mm; text-align: right; }
        .amounts td { height: 4.5mm; background: #f4f4f4; }
        .handover th { width: 24%; }
        .handover td { height: 5mm; }
        .footer { margin-top: 2mm; color: #52616b; font-size: 6pt; text-align: center; }
        @media screen { .page { margin-bottom: 12px; box-shadow: 0 5px 18px #0f172a14; } }
        @media print { .screen-actions { display: none; } .preview { margin: 0; } .page { border: 0; } }
    </style>
</head>
<body>
    <nav class="screen-actions" aria-label="{{ __('ui.print_actions') }}">
        <a href="{{ route('repair-jobs.show', $job) }}">{{ __('ui.back') }}</a>
        @can('job-cards.print')<a href="{{ route('job-cards.duplicate', $job) }}">{{ __('ui.reprint_job_card') }}</a>@endcan
        <button type="button" onclick="window.print()">{{ __('ui.print') }}</button>
        <a href="{{ route('job-cards.pdf',['job'=>$job,'duplicate'=>$duplicate ? 1 : 0]) }}">PDF</a>
    </nav>
    <main class="preview">
        <article class="page">
            <header class="heading">
                @if ($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $businessName }}">@endif
                <h1>{{ $documentTitle }}</h1>
                @if ($businessName || $businessAddress || $businessTelephone)<p>{{ implode(' · ', array_filter([$businessName, $businessAddress, $businessTelephone])) }}</p>@endif
                @if ($duplicate)<span class="duplicate">{{ $duplicateLabel }}</span>@endif
            </header>
            <p class="instruction">{{ __('ui.worksheet_instructions') }}</p>
            <table class="details">
                <tbody>
                    <tr><th>{{ __('ui.job_id') }}</th><td>{{ $job->automatic_number }}</td><th>{{ __('ui.worksheet_received_date') }}</th><td>{{ $job->received_at->timezone(config('business.timezone'))->format(config('business.date_format')) }}</td></tr>
                    <tr><th>{{ __('ui.worksheet_job_number') }}</th><td colspan="3">{{ $job->job_number }}</td></tr>
                    <tr><th>{{ __('ui.item_name') }}</th><td colspan="3">{{ $job->item_name }}</td></tr>
                    <tr><th>{{ __('ui.worksheet_brand_details') }}</th><td colspan="3">{{ trim(($job->brand ?? '').' '.($job->model ?? '').' '.($job->item_description ?? '')) ?: '-' }}</td></tr>
                    <tr><th>{{ __('ui.worksheet_item_fault') }}</th><td colspan="3">{{ $job->reported_fault ?: '-' }}</td></tr>
                    <tr><th>{{ __('ui.job_status') }}</th><td colspan="3">&nbsp;</td></tr>
                </tbody>
            </table>
            <section class="section">
                <h2>{{ __('ui.worksheet_issues_found') }}</h2>
                <table class="writing issues"><tbody><tr><td>&nbsp;</td></tr><tr><td>&nbsp;</td></tr></tbody></table>
            </section>
            <section class="section">
                <table class="signoffs">
                    <tbody>
                        <tr><th>{{ __('ui.worksheet_tested_by') }}</th><td></td><th>{{ __('ui.inspection_date') }}</th><td></td></tr>
                        <tr><th>{{ __('ui.repaired_by') }}</th><td></td><th>{{ __('ui.completion_date') }}</th><td></td></tr>
                    </tbody>
                </table>
            </section>
            <section class="section">
                <h2>{{ __('ui.repair_details') }}</h2>
                <table class="writing repair"><tbody><tr><td>&nbsp;</td></tr><tr><td>&nbsp;</td></tr><tr><td>&nbsp;</td></tr><tr><td>&nbsp;</td></tr><tr><td>&nbsp;</td></tr></tbody></table>
            </section>
            <section class="section">
                <table class="amounts">
                    <tbody>
                        <tr><th>{{ __('ui.parts_used') }}</th><td></td></tr>
                        <tr><th>{{ __('ui.parts_cost') }} ({{ config('business.currency') }})</th><td></td></tr>
                        <tr><th>{{ __('ui.total_amount') }} ({{ config('business.currency') }})</th><td></td></tr>
                    </tbody>
                </table>
            </section>
            <section class="section">
                <h2>{{ __('ui.special_notes') }}</h2>
                <table class="writing notes"><tbody><tr><td>&nbsp;</td></tr><tr><td>&nbsp;</td></tr></tbody></table>
            </section>
            <section class="section">
                <h2>{{ __('ui.worksheet_handover') }}</h2>
                <table class="handover">
                    <tbody>
                        <tr><th>{{ __('ui.worksheet_signature') }}</th><td></td><th>{{ __('ui.worksheet_return_date') }}</th><td></td></tr>
                        <tr><th>{{ __('ui.cashier_received') }}</th><td colspan="3"></td></tr>
                    </tbody>
                </table>
            </section>
            @if ($footerText)<footer class="footer">{{ $footerText }}</footer>@endif
        </article>
    </main>
@include('components.document-layout-settings')
</body>
</html>
