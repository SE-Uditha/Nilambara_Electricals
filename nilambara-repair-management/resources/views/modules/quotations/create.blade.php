@extends('layouts.app')

@section('title', ($quotation ? __('ui.edit_quotation') : __('ui.new_quotation')).' · '.config('app.name'))

@section('content')
    @php
        $inspection = $job?->jobInspections?->sortByDesc('inspected_at')->first();
    @endphp
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.quotations') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $quotation ? __('ui.edit_quotation') : __('ui.new_quotation') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('ui.quotation_form_description') }}</p>
        </div>
        <a href="{{ $quotation ? route('quotations.show', $quotation) : route('quotations.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">{{ __('ui.cancel_editing') }}</a>
    </div>

    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ __('ui.review_quotation_fields') }}</div>
    @endif

    <form method="POST" action="{{ $quotation ? route('quotations.update', $quotation) : route('quotations.store') }}" class="space-y-5" id="quotation-form">
        @csrf
        @if ($quotation) @method('PUT') @endif
        <input type="hidden" name="repair_job_id" id="repair_job_id" value="{{ old('repair_job_id', $job?->id) }}">

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <h2 class="mb-5 text-sm font-bold text-slate-900">{{ __('ui.quotation_information') }}</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div><label for="quotation-number" class="form-label">{{ __('ui.quotation_number') }}</label><input id="quotation-number" class="form-input" value="{{ $quotation?->quotation_number ?? __('ui.generated_after_save') }}" readonly></div>
                <div><label for="quotation_date" class="form-label">{{ __('ui.quotation_date') }}</label><input id="quotation_date" name="quotation_date" type="date" class="form-input" value="{{ old('quotation_date', $quotationDate) }}" required>@error('quotation_date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="valid_until" class="form-label">{{ __('ui.valid_until') }}</label><input id="valid_until" name="valid_until" type="date" class="form-input" value="{{ old('valid_until', $validUntil) }}" required>@error('valid_until')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
            </div>
            <p class="mt-4 text-xs text-slate-500">{{ __('ui.prepared_by') }}: {{ auth()->user()->name }}</p>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <h2 class="mb-4 text-sm font-bold text-slate-900">{{ __('ui.repair_job_information') }}</h2>
            @if (! $quotation)
                <div class="relative mb-4">
                    <label for="job-search" class="form-label">{{ __('ui.search_repair_job') }}</label>
                    <input id="job-search" type="search" class="form-input" autocomplete="off" placeholder="{{ __('ui.quotation_job_search_hint') }}" @if ($job) disabled @endif>
                    <div id="job-results" class="absolute z-20 mt-1 hidden max-h-64 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg" role="listbox"></div>
                    @error('repair_job_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @endif
            <div id="job-details" class="rounded-xl bg-slate-50 p-4" @if (! $job) hidden @endif>
                @if ($job)
                    <dl class="grid gap-3 text-xs sm:grid-cols-2 lg:grid-cols-3">
                        <div><dt class="form-label">{{ __('ui.job_number') }}</dt><dd data-job="job_number">{{ $job->automatic_number }} / {{ $job->job_number }}</dd></div>
                        <div><dt class="form-label">{{ __('ui.customer') }}</dt><dd data-job="customer">{{ $job->customer->display_name }}</dd></div>
                        <div><dt class="form-label">{{ __('ui.customer_id') }}</dt><dd data-job="customer_code">{{ $job->customer->customer_code }}</dd></div>
                        <div><dt class="form-label">{{ __('ui.address') }}</dt><dd data-job="address">{{ $job->customer->address }}</dd></div>
                        <div><dt class="form-label">{{ __('ui.contact_number') }}</dt><dd data-job="mobile_number">{{ $job->customer->mobile_number }}</dd></div>
                        <div><dt class="form-label">{{ __('ui.item_name') }}</dt><dd data-job="item_name">{{ $job->item_name }}</dd></div>
                        <div><dt class="form-label">{{ __('ui.brand_model') }}</dt><dd data-job="brand_model">{{ trim(($job->brand ?? '').' '.($job->model ?? '')) ?: '—' }}</dd></div>
                        <div><dt class="form-label">{{ __('ui.item_description') }}</dt><dd data-job="item_description">{{ $job->item_description ?: '—' }}</dd></div>
                        <div><dt class="form-label">{{ __('ui.reported_fault') }}</dt><dd data-job="reported_fault">{{ $job->reported_fault ?: '—' }}</dd></div>
                        <div class="sm:col-span-2 lg:col-span-3"><dt class="form-label">{{ __('ui.inspection_summary') }}</dt><dd data-job="inspection_summary">{{ $inspection?->inspection_result ?: $inspection?->technical_information ?: '—' }}</dd></div>
                    </dl>
                @else
                    <dl id="job-details-list" class="grid gap-3 text-xs sm:grid-cols-2 lg:grid-cols-3"></dl>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <h2 class="mb-5 text-sm font-bold text-slate-900">{{ __('ui.proposed_repair') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label for="proposed_repair_description" class="form-label">{{ __('ui.proposed_repair_details') }}</label><textarea id="proposed_repair_description" name="proposed_repair_description" rows="4" class="form-input" required>{{ old('proposed_repair_description', $quotation?->proposed_repair_description) }}</textarea>@error('proposed_repair_description')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="labour_charge" class="form-label">{{ __('ui.labour_charge_lkr') }}</label><input id="labour_charge" name="labour_charge" type="number" min="0" max="99999999999999999.99" step="0.01" class="form-input" value="{{ old('labour_charge', $quotation?->labour_charge ?? '0.00') }}" required>@error('labour_charge')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="text-sm font-bold text-slate-900">{{ __('ui.spare_parts_estimate') }}</h2><p class="mt-1 text-xs text-slate-500">{{ __('ui.no_stock_deduction_notice') }}</p></div>
                <button type="button" id="add-item" class="rounded-xl border border-teal-200 bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-800 hover:bg-teal-100">{{ __('ui.add_quotation_item') }}</button>
            </div>
            <div id="quotation-items" class="space-y-4">
                @foreach (old('items', $items) as $index => $item)
                    <div class="quotation-item rounded-xl border border-slate-200 p-4" data-item-row>
                        <input type="hidden" name="items[{{ $index }}][spare_part_id]" value="{{ $item['spare_part_id'] ?? '' }}" data-part-id>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(100px,1fr)_minmax(120px,1fr)_auto]">
                            <div class="relative">
                                <label class="form-label">{{ __('ui.search_registered_part') }}</label>
                                <input type="search" class="form-input part-search" autocomplete="off" placeholder="{{ __('ui.search_part_hint') }}">
                                <div class="part-results absolute z-20 mt-1 hidden max-h-52 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg"></div>
                            </div>
                            <div><label class="form-label">{{ __('ui.part_description') }}</label><input name="items[{{ $index }}][description]" class="form-input part-description" value="{{ old("items.$index.description", $item['description'] ?? '') }}" placeholder="{{ __('ui.manual_part_description') }}" required>@error("items.$index.description")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                            <div><label class="form-label">{{ __('ui.quantity') }}</label><input name="items[{{ $index }}][quantity]" type="number" min="0.001" step="0.001" class="form-input item-quantity" value="{{ old("items.$index.quantity", $item['quantity'] ?? '1') }}" required></div>
                            <div><label class="form-label">{{ __('ui.unit_price_lkr') }}</label><input name="items[{{ $index }}][unit_price]" type="number" min="0" step="0.0001" class="form-input item-price part-price" value="{{ old("items.$index.unit_price", $item['unit_price'] ?? '') }}" @if (! empty($item['spare_part_id'])) readonly @endif required></div>
                            <div class="flex items-end"><button type="button" class="remove-item w-full rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-red-700 hover:bg-red-50">{{ __('ui.remove') }}</button></div>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('items')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            <template id="item-template">
                <div class="quotation-item rounded-xl border border-slate-200 p-4" data-item-row>
                    <input type="hidden" data-part-id>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_minmax(100px,1fr)_minmax(120px,1fr)_auto]">
                        <div class="relative"><label class="form-label">{{ __('ui.search_registered_part') }}</label><input type="search" class="form-input part-search" autocomplete="off" placeholder="{{ __('ui.search_part_hint') }}"><div class="part-results absolute z-20 mt-1 hidden max-h-52 w-full overflow-auto rounded-xl border border-slate-200 bg-white shadow-lg"></div></div>
                        <div><label class="form-label">{{ __('ui.part_description') }}</label><input data-name="description" class="form-input part-description" placeholder="{{ __('ui.manual_part_description') }}" required></div>
                        <div><label class="form-label">{{ __('ui.quantity') }}</label><input data-name="quantity" type="number" min="0.001" step="0.001" class="form-input item-quantity" value="1" required></div>
                        <div><label class="form-label">{{ __('ui.unit_price_lkr') }}</label><input data-name="unit_price" type="number" min="0" step="0.0001" class="form-input item-price part-price" required></div>
                        <div class="flex items-end"><button type="button" class="remove-item w-full rounded-lg border border-slate-200 px-3 py-2.5 text-xs font-semibold text-red-700 hover:bg-red-50">{{ __('ui.remove') }}</button></div>
                    </div>
                </div>
            </template>
            <div class="mt-5 grid gap-2 rounded-xl bg-slate-50 p-4 text-sm sm:max-w-sm sm:ml-auto">
                <div class="flex justify-between"><span>{{ __('ui.parts_total') }}</span><strong id="parts-total-preview">LKR 0.00</strong></div>
                <div class="flex justify-between"><span>{{ __('ui.labour_charge') }}</span><strong id="labour-total-preview">LKR 0.00</strong></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-base"><span>{{ __('ui.quotation_total') }}</span><strong id="quotation-total-preview">LKR 0.00</strong></div>
                <p class="text-[10px] leading-4 text-slate-500">{{ __('ui.backend_calculation_notice') }}</p>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <h2 class="mb-4 text-sm font-bold text-slate-900">{{ __('ui.terms_and_conditions') }}</h2>
            <textarea id="terms_and_conditions" name="terms_and_conditions" rows="4" class="form-input" placeholder="{{ __('ui.terms_optional_hint') }}">{{ old('terms_and_conditions', $quotation?->terms_and_conditions) }}</textarea>
            @error('terms_and_conditions')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </section>

        <div class="flex flex-wrap justify-end gap-3">
            <button type="submit" name="intent" value="draft" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.save_draft') }}</button>
            @can('quotations.print')
                <button type="submit" name="intent" value="preview" class="rounded-xl border border-teal-200 bg-teal-50 px-5 py-3 text-xs font-semibold text-teal-800 hover:bg-teal-100">{{ __('ui.save_and_preview') }}</button>
            @endcan
            @can('quotations.issue')
                <button type="submit" name="intent" value="issue" class="rounded-xl bg-teal-700 px-5 py-3 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.issue_quotation') }}</button>
            @endcan
        </div>
    </form>

    <script>
        (() => {
            const form = document.getElementById('quotation-form');
            const rows = document.getElementById('quotation-items');
            const template = document.getElementById('item-template');
            const money = (value) => `LKR ${Number(value || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            const updateTotals = () => {
                let parts = 0;
                rows.querySelectorAll('[data-item-row]').forEach((row) => {
                    parts += Number(row.querySelector('.item-quantity').value || 0) * Number(row.querySelector('.item-price').value || 0);
                });
                const labour = Number(document.getElementById('labour_charge').value || 0);
                document.getElementById('parts-total-preview').textContent = money(parts);
                document.getElementById('labour-total-preview').textContent = money(labour);
                document.getElementById('quotation-total-preview').textContent = money(parts + labour);
            };
            const addDetail = (list, label, value, wide = false) => {
                const wrapper = document.createElement('div');
                if (wide) wrapper.className = 'sm:col-span-2 lg:col-span-3';
                const title = document.createElement('dt');
                title.className = 'form-label';
                title.textContent = label;
                const detail = document.createElement('dd');
                detail.textContent = value || '—';
                wrapper.append(title, detail);
                list.append(wrapper);
            };
            const renumber = () => rows.querySelectorAll('[data-item-row]').forEach((row, index) => {
                row.querySelector('[data-part-id]').name = `items[${index}][spare_part_id]`;
                row.querySelectorAll('[data-name]').forEach((field) => field.name = `items[${index}][${field.dataset.name}]`);
            });
            document.getElementById('add-item').addEventListener('click', () => {
                rows.append(template.content.cloneNode(true));
                renumber();
                updateTotals();
            });
            rows.addEventListener('click', (event) => {
                if (event.target.classList.contains('remove-item')) {
                    const allRows = rows.querySelectorAll('[data-item-row]');
                    if (allRows.length > 1) event.target.closest('[data-item-row]').remove();
                    renumber();
                    updateTotals();
                }
                const option = event.target.closest('[data-part]');
                if (option) {
                    const row = option.closest('[data-item-row]');
                    row.querySelector('[data-part-id]').value = option.dataset.id;
                    row.querySelector('.part-description').value = option.dataset.name;
                    row.querySelector('.part-price').value = option.dataset.price;
                    row.querySelector('.part-price').readOnly = true;
                    row.querySelector('.part-results').classList.add('hidden');
                    row.querySelector('.part-search').value = `${option.dataset.sku} · ${option.dataset.name}`;
                    updateTotals();
                }
            });
            rows.addEventListener('input', async (event) => {
                if (event.target.classList.contains('part-search')) {
                    const row = event.target.closest('[data-item-row]');
                    const results = row.querySelector('.part-results');
                    const term = event.target.value.trim();
                    row.querySelector('[data-part-id]').value = '';
                    row.querySelector('.part-price').readOnly = false;
                    if (!term) { results.classList.add('hidden'); return; }
                    try {
                        const response = await fetch(`{{ route('quotations.spare-parts') }}?q=${encodeURIComponent(term)}`, {headers: {'Accept': 'application/json'}});
                        if (!response.ok) throw new Error(@js(__('ui.search_failed')));
                        const parts = await response.json();
                        results.replaceChildren();
                        if (!parts.length) {
                            const empty = document.createElement('p');
                            empty.className = 'px-3 py-2 text-xs text-slate-500';
                            empty.textContent = @js(__('ui.no_spare_parts_found'));
                            results.append(empty);
                        }
                        parts.forEach((part) => {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.dataset.part = '';
                            button.dataset.id = part.id;
                            button.dataset.name = part.name;
                            button.dataset.sku = part.sku;
                            button.dataset.price = part.selling_price;
                            button.className = 'block w-full px-3 py-2 text-left text-xs hover:bg-slate-50';
                            button.textContent = `${part.sku} · ${part.name} — ${money(part.selling_price)}`;
                            results.append(button);
                        });
                        results.classList.remove('hidden');
                    } catch (error) {
                        results.replaceChildren();
                        const message = document.createElement('p');
                        message.className = 'px-3 py-2 text-xs text-red-700';
                        message.textContent = error.message;
                        results.append(message);
                        results.classList.remove('hidden');
                    }
                }
                if (event.target.classList.contains('item-quantity') || event.target.classList.contains('item-price')) updateTotals();
            });
            document.getElementById('labour_charge').addEventListener('input', updateTotals);

            const jobInput = document.getElementById('job-search');
            const jobResults = document.getElementById('job-results');
            const jobId = document.getElementById('repair_job_id');
            const jobDetails = document.getElementById('job-details');
            let searchTimer;
            if (jobInput) {
                jobInput.addEventListener('input', () => {
                    clearTimeout(searchTimer);
                    const term = jobInput.value.trim();
                    if (term.length < 2) { jobResults.classList.add('hidden'); return; }
                    searchTimer = setTimeout(async () => {
                        try {
                            const response = await fetch(`{{ route('quotations.repair-jobs') }}?q=${encodeURIComponent(term)}`, {headers: {'Accept': 'application/json'}});
                            if (!response.ok) throw new Error(@js(__('ui.search_failed')));
                            const jobs = await response.json();
                            jobResults.replaceChildren();
                            if (!jobs.length) {
                                const empty = document.createElement('p');
                                empty.className = 'px-3 py-3 text-xs text-slate-500';
                                empty.textContent = @js(__('ui.no_repair_jobs_found'));
                                jobResults.append(empty);
                            }
                            jobs.forEach((job) => {
                                const button = document.createElement('button');
                                button.type = 'button';
                                button.dataset.jobOption = '';
                                Object.assign(button.dataset, {
                                    value: job.id,
                                    customer: job.customer,
                                    customerCode: job.customer_code || '',
                                    address: job.address || '',
                                    mobile: job.mobile_number,
                                    item: job.item_name,
                                    itemDescription: job.item_description || '',
                                    reportedFault: job.reported_fault || '',
                                    inspectionSummary: job.inspection_summary || '',
                                    brand: [job.brand, job.model].filter(Boolean).join(' '),
                                    jobNumber: `${job.automatic_number} / ${job.job_number}`,
                                });
                                button.className = 'block w-full border-b border-slate-100 px-3 py-3 text-left text-xs hover:bg-slate-50';
                                button.textContent = `${job.automatic_number} / ${job.job_number} · ${job.customer} · ${job.item_name}`;
                                jobResults.append(button);
                            });
                            jobResults.classList.remove('hidden');
                        } catch (error) {
                            jobResults.replaceChildren();
                            const message = document.createElement('p');
                            message.className = 'px-3 py-3 text-xs text-red-700';
                            message.textContent = error.message;
                            jobResults.append(message);
                            jobResults.classList.remove('hidden');
                        }
                    }, 250);
                });
                jobResults.addEventListener('click', async (event) => {
                    const option = event.target.closest('[data-job-option]');
                    if (!option) return;
                    jobId.value = option.dataset.value;
                    jobInput.value = option.dataset.jobNumber;
                    jobResults.classList.add('hidden');
                    jobDetails.hidden = false;
                    const details = document.createElement('dl');
                    details.id = 'job-details-list';
                    details.className = 'grid gap-3 text-xs sm:grid-cols-2 lg:grid-cols-3';
                    addDetail(details, @js(__('ui.job_number')), option.dataset.jobNumber);
                    addDetail(details, @js(__('ui.customer')), option.dataset.customer);
                    addDetail(details, @js(__('ui.customer_id')), option.dataset.customerCode);
                    addDetail(details, @js(__('ui.address')), option.dataset.address);
                    addDetail(details, @js(__('ui.contact_number')), option.dataset.mobile);
                    addDetail(details, @js(__('ui.item_name')), option.dataset.item);
                    addDetail(details, @js(__('ui.brand_model')), option.dataset.brand);
                    addDetail(details, @js(__('ui.item_description')), option.dataset.itemDescription);
                    addDetail(details, @js(__('ui.reported_fault')), option.dataset.reportedFault);
                    addDetail(details, @js(__('ui.inspection_summary')), option.dataset.inspectionSummary, true);
                    jobDetails.replaceChildren(details);
                });
            }
            rows.addEventListener('input', updateTotals);
            form.addEventListener('submit', (event) => {
                if (!jobId.value) {
                    event.preventDefault();
                    jobInput?.focus();
                    jobResults.innerHTML = `<p class="px-3 py-3 text-xs text-red-700">${@js(__('ui.quotation_job_required'))}</p>`;
                    jobResults.classList.remove('hidden');
                }
            });
            renumber();
            updateTotals();
        })();
    </script>
@endsection
