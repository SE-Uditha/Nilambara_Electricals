@extends('layouts.app')

@section('title', $quotation->quotation_number.' · '.config('app.name'))

@section('content')
    @php
        $snapshot = $quotation->document_snapshot ?? [];
        $job = $quotation->repairJob;
        $item = $snapshot['item'] ?? [];
        $customer = $snapshot['customer'] ?? [];
        $money = fn ($amount) => \Brick\Math\BigDecimal::of((string) ($amount ?? '0'))
            ->toScale(2, \Brick\Math\RoundingMode::HalfUp);
    @endphp
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.quotations') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $quotation->quotation_number }} <span class="text-sm font-medium text-slate-400">v{{ $quotation->revision }}</span></h1>
            <p class="mt-1 text-sm text-slate-500">{{ $job->automatic_number }} / {{ $job->job_number }} · {{ $quotation->customer->display_name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('quotations.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">{{ __('ui.back_to_quotations') }}</a>
            @can('view', $job)<a href="{{ route('repair-jobs.show', $job) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">{{ __('ui.view_repair_job') }}</a>@endcan
            @can('print', $quotation)
                <a href="{{ route('quotations.print', $quotation) }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.preview_quotation') }}</a>
                <a href="{{ route('quotations.pdf', ['quotation' => $quotation, 'paper' => 'A4']) }}" class="rounded-xl border border-teal-200 bg-teal-50 px-4 py-2.5 text-xs font-semibold text-teal-800 hover:bg-teal-100">{{ __('ui.download_pdf_a4') }}</a>
            @endcan
        </div>
    </div>
    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-slate-900">{{ __('ui.quotation_information') }}</h2>
                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ __('ui.preparation_'.$quotation->preparation_status->value) }}</span>
                        <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">{{ __('ui.approval_'.$quotation->approval_status->value) }}</span>
                    </div>
                </div>
                @if ($quotation->preparation_status->value === 'draft')
                    <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs font-semibold text-amber-900">{{ __('ui.draft_not_approved') }}</p>
                @endif
                <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="form-label">{{ __('ui.quotation_date') }}</dt><dd>{{ $quotation->quotation_date->format(config('business.date_format')) }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.valid_until') }}</dt><dd>{{ $quotation->valid_until?->format(config('business.date_format')) ?: '—' }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.issued_at') }}</dt><dd>{{ $quotation->issued_at?->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) ?: '—' }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.customer') }}</dt><dd>{{ $customer['name'] ?? $quotation->customer->display_name }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.customer_id') }}</dt><dd>{{ $customer['customer_code'] ?? $quotation->customer->customer_code }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.contact_number') }}</dt><dd>{{ $customer['mobile_number'] ?? $quotation->customer->mobile_number }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.job_number') }}</dt><dd>{{ $job->automatic_number }} / {{ $job->job_number }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.item_name') }}</dt><dd>{{ $item['name'] ?? $job->item_name }}</dd></div>
                    <div><dt class="form-label">{{ __('ui.brand_model') }}</dt><dd>{{ trim(($item['brand'] ?? $job->brand ?? '').' '.($item['model'] ?? $job->model ?? '')) ?: '—' }}</dd></div>
                    <div class="sm:col-span-2 lg:col-span-3"><dt class="form-label">{{ __('ui.address') }}</dt><dd class="whitespace-pre-line">{{ $customer['address'] ?? $quotation->customer->address }}</dd></div>
                    <div class="sm:col-span-2 lg:col-span-3"><dt class="form-label">{{ __('ui.item_description') }}</dt><dd class="whitespace-pre-line">{{ $item['description'] ?? $job->item_description ?: '—' }}</dd></div>
                    <div class="sm:col-span-2 lg:col-span-3"><dt class="form-label">{{ __('ui.reported_fault') }}</dt><dd class="whitespace-pre-line">{{ $item['reported_fault'] ?? $job->reported_fault ?: '—' }}</dd></div>
                    <div class="sm:col-span-2 lg:col-span-3"><dt class="form-label">{{ __('ui.inspection_summary') }}</dt><dd class="whitespace-pre-line">{{ $item['inspection_summary'] ?? '—' }}</dd></div>
                    <div class="sm:col-span-2 lg:col-span-3"><dt class="form-label">{{ __('ui.proposed_repair_details') }}</dt><dd class="whitespace-pre-line">{{ $snapshot['proposed_repair_description'] ?? $quotation->proposed_repair_description }}</dd></div>
                </dl>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-bold text-slate-900">{{ __('ui.spare_parts_estimate') }}</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-semibold uppercase text-slate-500"><tr><th class="px-4 py-3">{{ __('ui.part_description') }}</th><th class="px-4 py-3">{{ __('ui.quantity') }}</th><th class="px-4 py-3">{{ __('ui.unit_price_lkr') }}</th><th class="px-4 py-3 text-right">{{ __('ui.line_total') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($quotation->items as $line)
                                <tr><td class="px-4 py-3">{{ $line->description }}</td><td class="px-4 py-3">{{ $line->quantity }}</td><td class="px-4 py-3">LKR {{ $money($line->unit_price) }}</td><td class="px-4 py-3 text-right font-semibold">LKR {{ $money($line->line_total) }}</td></tr>
                            @empty
                                @foreach (($snapshot['items'] ?? []) as $line)
                                    <tr><td class="px-4 py-3">{{ $line['description'] }}</td><td class="px-4 py-3">{{ $line['quantity'] }}</td><td class="px-4 py-3">LKR {{ $money($line['unit_price']) }}</td><td class="px-4 py-3 text-right font-semibold">LKR {{ $money($line['line_total']) }}</td></tr>
                                @endforeach
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="space-y-2 border-t border-slate-100 bg-slate-50 p-5 text-sm sm:ml-auto sm:max-w-sm">
                    <div class="flex justify-between"><span>{{ __('ui.parts_total') }}</span><strong>LKR {{ $money($snapshot['parts_total'] ?? $quotation->parts_total) }}</strong></div>
                    <div class="flex justify-between"><span>{{ __('ui.labour_charge') }}</span><strong>LKR {{ $money($snapshot['labour_charge'] ?? $quotation->labour_charge) }}</strong></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base"><span>{{ __('ui.quotation_total') }}</span><strong>LKR {{ $money($snapshot['quotation_total'] ?? $quotation->total) }}</strong></div>
                </div>
                @if ($quotation->terms_and_conditions)
                    <div class="border-t border-slate-100 px-5 py-4"><h3 class="mb-2 text-xs font-bold">{{ __('ui.terms_and_conditions') }}</h3><p class="whitespace-pre-line text-xs text-slate-600">{{ $snapshot['terms_and_conditions'] ?? $quotation->terms_and_conditions }}</p></div>
                @endif
            </section>
        </div>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-bold text-slate-900">{{ __('ui.quotation_actions') }}</h2>
                <div class="space-y-2">
                    @can('update', $quotation)<a href="{{ route('quotations.edit', $quotation) }}" class="block rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.edit_draft') }}</a>@endcan
                    @can('issue', $quotation)
                        <form method="POST" action="{{ route('quotations.issue', $quotation) }}" onsubmit="return confirm(@js(__('ui.confirm_issue_quotation')))">@csrf<button class="w-full rounded-lg bg-teal-700 px-3 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.issue_quotation') }}</button></form>
                    @endcan
                    @can('print', $quotation)
                        <a href="{{ route('quotations.pdf', ['quotation' => $quotation, 'paper' => 'A5']) }}" class="block rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.download_pdf_a5') }}</a>
                        <a href="{{ route('quotations.duplicate', $quotation) }}" class="block rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.reprint_duplicate') }}</a>
                    @endcan
                    @can('revise', $quotation)
                        <form method="POST" action="{{ route('quotations.revise', $quotation) }}" onsubmit="return confirm(@js(__('ui.confirm_create_revision')))">@csrf<button class="w-full rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.create_revision') }}</button></form>
                    @endcan
                    @can('cancel', $quotation)
                        <form method="POST" action="{{ route('quotations.cancel', $quotation) }}" onsubmit="return confirm(@js(__('ui.confirm_cancel_quotation')))">@csrf<input name="notes" class="form-input mb-2" maxlength="1000" placeholder="{{ __('ui.cancellation_note_optional') }}"><button class="w-full rounded-lg border border-red-200 px-3 py-2.5 text-xs font-semibold text-red-700 hover:bg-red-50">{{ __('ui.cancel_quotation') }}</button></form>
                    @endcan
                </div>
            </section>

            @can('recordDecision', $quotation)
                <section class="rounded-2xl border border-teal-200 bg-white p-5">
                    <h2 class="mb-2 text-sm font-bold text-slate-900">{{ __('ui.record_customer_decision') }}</h2>
                    <p class="mb-4 text-xs leading-5 text-slate-500">{{ __('ui.customer_decision_disclaimer') }}</p>
                    <form method="POST" action="{{ route('quotations.decision', $quotation) }}" class="space-y-3">
                        @csrf
                        <div><label class="form-label" for="decision">{{ __('ui.customer_approval_status') }}</label><select id="decision" name="decision" class="form-input" required><option value="">{{ __('ui.select_decision') }}</option><option value="approved">{{ __('ui.approval_approved') }}</option><option value="rejected">{{ __('ui.approval_rejected') }}</option></select></div>
                        <div><label class="form-label" for="decision_date">{{ __('ui.decision_date') }}</label><input id="decision_date" type="datetime-local" name="decision_date" value="{{ now(config('business.timezone'))->format('Y-m-d\TH:i') }}" class="form-input" required></div>
                        <div><label class="form-label" for="customer_contact_name">{{ __('ui.customer_contact_name') }}</label><input id="customer_contact_name" name="customer_contact_name" maxlength="255" class="form-input" required></div>
                        <div><label class="form-label" for="approval_method">{{ __('ui.decision_method') }}</label><select id="approval_method" name="approval_method" class="form-input" required><option value="">{{ __('ui.select_method') }}</option>@foreach (['in_person', 'telephone', 'email', 'other'] as $method)<option value="{{ $method }}">{{ __('ui.decision_method_'.$method) }}</option>@endforeach</select></div>
                        <div><label class="form-label" for="notes">{{ __('ui.notes') }}</label><textarea id="notes" name="notes" rows="3" maxlength="4000" class="form-input"></textarea></div>
                        <button class="w-full rounded-lg bg-teal-700 px-3 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.record_customer_decision') }}</button>
                    </form>
                </section>
            @endcan

            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-bold text-slate-900">{{ __('ui.approval_history') }}</h2>
                <div class="space-y-3">
                    @forelse ($quotation->approvals as $approval)
                        <article class="border-l-2 border-teal-600 pl-3">
                            <p class="text-xs font-semibold">{{ __('ui.approval_'.$approval->decision->value) }} · {{ $approval->customer_contact_name }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">{{ $approval->decided_at->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }} · {{ __('ui.decision_method_'.$approval->approval_method) }} · {{ $approval->recordedByUser?->name }}</p>
                            @if ($approval->notes)<p class="mt-2 whitespace-pre-line text-xs text-slate-600">{{ $approval->notes }}</p>@endif
                        </article>
                    @empty
                        <p class="text-xs text-slate-500">{{ __('ui.no_decisions_recorded') }}</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-bold text-slate-900">{{ __('ui.quotation_history') }}</h2>
                <div class="space-y-3">
                    @foreach ($quotation->history as $event)
                        <article class="border-l-2 border-slate-200 pl-3">
                            <p class="text-xs font-semibold">{{ __('ui.quotation_event_'.$event->event_type) }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">{{ $event->event_at->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }} · {{ $event->actor?->name }}</p>
                        </article>
                    @endforeach
                </div>
                @if ($quotation->revisesQuotation)
                    <p class="mt-4 border-t border-slate-100 pt-3 text-xs">{{ __('ui.revises_quotation') }}: <a class="font-semibold text-teal-700" href="{{ route('quotations.show', $quotation->revisesQuotation) }}">{{ $quotation->revisesQuotation->quotation_number }}</a></p>
                @endif
                @foreach ($quotation->revisions as $revision)
                    <p class="mt-3 text-xs">{{ __('ui.revision') }}: <a class="font-semibold text-teal-700" href="{{ route('quotations.show', $revision) }}">{{ $revision->quotation_number }}</a> · {{ __('ui.preparation_'.$revision->preparation_status->value) }}</p>
                @endforeach
            </section>
        </aside>
    </div>
@endsection
