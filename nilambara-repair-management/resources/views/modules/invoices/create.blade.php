@extends('layouts.app')

@section('title', ($invoice ? __('invoices.edit_draft') : __('invoices.new_invoice')).' · '.config('app.name'))

@section('content')
    @php
        $editing = $invoice !== null;
        $snapshot = $invoice?->document_snapshot ?? [];
    @endphp
    <div class="mb-7">
        <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.sales_finance') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $editing ? __('invoices.edit_draft') : __('invoices.new_invoice') }}</h1>
        @if ($editing)
            <p class="mt-1 text-sm text-slate-500">{{ $invoice->invoice_number }} · {{ __('invoices.draft_jobs_cannot_be_removed') }}</p>
        @else
            <p class="mt-1 text-sm text-slate-500">{{ __('invoices.description') }}</p>
        @endif
    </div>

    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST"
        action="{{ $editing ? route('invoices.update', $invoice) : route('invoices.store') }}"
        class="space-y-5"
        id="invoice-form"
        data-customer-search="{{ route('customers.search') }}"
        data-jobs-url="{{ route('invoices.jobs', ['customer' => '__CUSTOMER__']) }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-bold text-slate-900">{{ __('invoices.customer_details') }}</h2>
            @if ($editing)
                <input type="hidden" id="customer_id" value="{{ $invoice->customer_id }}">
                <div id="selected-customer" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="font-semibold text-slate-900">{{ $invoice->customer->display_name }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $invoice->customer->customer_code }} · {{ $invoice->customer->address }} · {{ $invoice->customer->mobile_number }}</p>
                </div>
            @else
                <label for="customer-search" class="form-label mt-4">{{ __('invoices.customer_search') }}</label>
                <input id="customer-search" type="search" class="form-input" autocomplete="off" placeholder="{{ __('invoices.customer_search') }}" value="{{ $selectedCustomer?->customer_code.' · '.$selectedCustomer?->display_name }}">
                <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id', $selectedCustomer?->getKey()) }}">
                <div id="customer-results" class="mt-2 hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"></div>
                <div id="selected-customer" class="mt-3 {{ $selectedCustomer ? '' : 'hidden' }} rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900">{{ $selectedCustomer?->display_name }} · {{ $selectedCustomer?->address }} · {{ $selectedCustomer?->mobile_number }}</div>
                <p class="mt-2 text-xs text-slate-500">{{ __('invoices.select_customer') }}</p>
            @endif
            @error('customer_id')<p class="mt-2 text-xs text-red-700">{{ $message }}</p>@enderror
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">{{ __('invoices.repair_jobs') }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ __('invoices.no_billable_jobs') }}</p>
                </div>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-semibold text-amber-800">{{ __('invoices.approved_quote_estimate_notice') }}</span>
            </div>
            <div id="job-results" class="mt-4 space-y-3">
                @if (! $editing)
                    <p class="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">{{ __('invoices.save_customer_first') }}</p>
                @endif
            </div>
            @error('repair_job_ids')<p class="mt-3 text-xs text-red-700">{{ $message }}</p>@enderror
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-bold text-slate-900">{{ __('invoices.invoice_details') }}</h2>
                <div>
                    <label for="invoice_date" class="form-label">{{ __('invoices.invoice_date') }}</label>
                    <input id="invoice_date" name="invoice_date" type="date" required value="{{ old('invoice_date', $invoiceDate) }}" class="form-input">
                    @error('invoice_date')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="payment_terms" class="form-label">{{ __('invoices.payment_terms') }}</label>
                        <select id="payment_terms" name="payment_terms" class="form-input">
                            <option value="cash" @selected(old('payment_terms', $invoice?->payment_terms?->value ?? 'cash') === 'cash')>{{ __('invoices.cash') }}</option>
                            <option value="credit" @selected(old('payment_terms', $invoice?->payment_terms?->value) === 'credit')>{{ __('invoices.credit') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="payment_method" class="form-label">{{ __('invoices.payment_method') }}</label>
                        <select id="payment_method" name="payment_method" class="form-input">
                            <option value="">{{ __('invoices.select_method') }}</option>
                            @foreach (['cash' => __('invoices.cash'), 'bank_transfer' => __('invoices.bank_transfer'), 'cheque' => __('invoices.cheque')] as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_method', $invoice?->payment_method?->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="-mt-2 text-xs text-slate-500">{{ __('invoices.method_is_not_payment') }}</p>
                <div>
                    <label for="due_date" class="form-label">{{ __('invoices.due_date') }}</label>
                    <input id="due_date" name="due_date" type="date" value="{{ old('due_date', $invoice?->due_date?->toDateString()) }}" class="form-input">
                    @error('due_date')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900">
                    {{ __('invoices.pending_step_8') }}
                    <div class="mt-2 flex justify-between border-t border-amber-200 pt-2"><span>{{ __('invoices.current_payment') }}</span><span class="font-semibold">{{ config('business.currency') }} 0.00</span></div>
                </div>
            </section>

            <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-bold text-slate-900">{{ __('invoices.collection_details') }}</h2>
                <div>
                    <label for="collected_by" class="form-label">{{ __('invoices.collected_by') }}</label>
                    <input id="collected_by" name="collected_by" maxlength="255" value="{{ old('collected_by', $invoice?->collected_by) }}" class="form-input">
                </div>
                <div>
                    <label for="vehicle_number" class="form-label">{{ __('invoices.vehicle_number') }}</label>
                    <input id="vehicle_number" name="vehicle_number" maxlength="40" value="{{ old('vehicle_number', $invoice?->vehicle_number) }}" class="form-input">
                </div>
                <div>
                    <label for="delivery_at" class="form-label">{{ __('invoices.delivery_date') }}</label>
                    <input id="delivery_at" name="delivery_at" type="datetime-local" value="{{ old('delivery_at', $invoice?->delivery_at?->format('Y-m-d\TH:i')) }}" class="form-input">
                </div>
                <div>
                    <label for="delivery_information" class="form-label">{{ __('invoices.delivery_information') }}</label>
                    <textarea id="delivery_information" name="delivery_information" rows="2" maxlength="5000" class="form-input">{{ old('delivery_information', $invoice?->delivery_information) }}</textarea>
                </div>
                <div>
                    <label for="special_notes" class="form-label">{{ __('invoices.special_notes') }}</label>
                    <textarea id="special_notes" name="special_notes" rows="2" maxlength="5000" class="form-input">{{ old('special_notes', $invoice?->special_notes) }}</textarea>
                </div>
                <p class="text-xs text-slate-500">{{ __('invoices.no_delivery_status_change') }}</p>
            </section>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-bold text-slate-900">{{ __('invoices.invoice_summary') }}</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-3"><span class="text-xs text-slate-500">{{ __('invoices.job_count') }}</span><p id="summary-job-count" class="mt-1 text-lg font-bold text-slate-900">0</p></div>
                <div class="rounded-xl bg-teal-50 p-3"><span class="text-xs text-teal-800">{{ __('invoices.invoice_total') }}</span><p id="summary-total" class="mt-1 text-lg font-bold text-teal-900">{{ config('business.currency') }} 0.00</p></div>
            </div>
            <p class="mt-3 text-xs text-slate-500">{{ __('invoices.server_calculates_totals') }}</p>
        </section>

        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ $editing ? route('invoices.show', $invoice) : route('invoices.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.cancel') }}</a>
            <button type="submit" name="intent" value="draft" class="rounded-xl border border-teal-700 px-4 py-2.5 text-xs font-semibold text-teal-800 hover:bg-teal-50">{{ __('invoices.save_draft') }}</button>
            @can('invoices.print')
                <button type="submit" name="intent" value="preview" class="rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('invoices.save_preview') }}</button>
            @endcan
            @can('invoices.issue')
                <button type="submit" name="intent" value="issue" data-confirm="{{ __('invoices.issue_confirmation') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('invoices.issue_invoice') }}</button>
            @endcan
        </div>
    </form>

    <script>
        (() => {
            const form = document.getElementById('invoice-form');
            const customerSearch = document.getElementById('customer-search');
            const customerId = document.getElementById('customer_id');
            const customerResults = document.getElementById('customer-results');
            const selectedCustomer = document.getElementById('selected-customer');
            const jobResults = document.getElementById('job-results');
            const selectedJobs = new Map();
            const existingIds = new Set(@js($editing ? array_map(fn ($job) => (string) $job['id'], $selectedJobs) : []));
            const initialJobs = @js($selectedJobs);
            const initialCustomerId = @js($selectedCustomer?->getKey());
            let debounce;
            const labels = {
                noCustomer: @js(__('invoices.save_customer_first')),
                noJobs: @js(__('invoices.no_billable_jobs')),
                job: @js(__('invoices.job_number')),
                total: @js(__('invoices.job_total')),
                quote: @js(__('invoices.approved_quote')),
                currency: @js(config('business.currency')),
            };

            const escape = (value) => String(value ?? '');
            const formatMoney = (value) => `${labels.currency} ${Number(value || 0).toFixed(2)}`;
            const renderSummary = () => {
                document.getElementById('summary-job-count').textContent = String(selectedJobs.size);
                const total = [...selectedJobs.values()].reduce((sum, job) => sum + Number(job.total || 0), 0);
                document.getElementById('summary-total').textContent = formatMoney(total);
            };

            const appendSelectedJob = (job, fixed = false) => {
                const id = String(job.id);
                if (selectedJobs.has(id)) return;
                selectedJobs.set(id, job);
                const card = document.createElement('article');
                card.className = 'rounded-xl border border-teal-200 bg-teal-50 p-3';
                card.dataset.selectedJob = id;
                const head = document.createElement('div');
                head.className = 'flex flex-wrap items-start justify-between gap-2';
                const details = document.createElement('div');
                const title = document.createElement('p');
                title.className = 'font-semibold text-slate-900';
                title.textContent = `${job.job_number} · ${job.item_name}`;
                const subtitle = document.createElement('p');
                subtitle.className = 'mt-1 text-xs text-slate-600';
                subtitle.textContent = job.description || job.status || '';
                details.append(title, subtitle);
                const amount = document.createElement('strong');
                amount.className = 'whitespace-nowrap text-sm text-teal-900';
                amount.textContent = formatMoney(job.total);
                head.append(details, amount);
                card.append(head);
                if (job.approved_quotation) {
                    const quote = document.createElement('p');
                    quote.className = 'mt-2 text-[11px] text-slate-600';
                    quote.textContent = `${labels.quote}: ${job.approved_quotation}`;
                    card.append(quote);
                }
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'repair_job_ids[]';
                hidden.value = id;
                card.append(hidden);
                if (!fixed) {
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'mt-2 text-xs font-semibold text-red-700 hover:underline';
                    remove.textContent = 'Remove';
                    remove.addEventListener('click', () => {
                        selectedJobs.delete(id);
                        card.remove();
                        const checkbox = document.querySelector(`[data-job-option="${CSS.escape(id)}"]`);
                        if (checkbox) checkbox.checked = false;
                        renderSummary();
                    });
                    card.append(remove);
                }
                jobResults.append(card);
                renderSummary();
            };

            const renderJobs = (jobs) => {
                jobResults.replaceChildren();
                for (const job of jobs) {
                    const row = document.createElement('label');
                    row.className = 'flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 hover:border-teal-300';
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'mt-1 rounded border-slate-300 text-teal-700';
                    checkbox.dataset.jobOption = String(job.id);
                    checkbox.checked = existingIds.has(String(job.id));
                    checkbox.disabled = checkbox.checked;
                    const text = document.createElement('span');
                    text.className = 'min-w-0 flex-1';
                    const title = document.createElement('span');
                    title.className = 'block text-sm font-semibold text-slate-900';
                    title.textContent = `${job.job_number} · ${job.item_name}`;
                    const info = document.createElement('span');
                    info.className = 'mt-1 block text-xs text-slate-500';
                    info.textContent = `${job.status} · ${job.description || ''} · ${labels.total}: ${formatMoney(job.total)}`;
                    text.append(title, info);
                    if (job.approved_quotation) {
                        const quote = document.createElement('span');
                        quote.className = 'mt-1 block text-xs text-teal-800';
                        quote.textContent = `${labels.quote}: ${job.approved_quotation}`;
                        text.append(quote);
                    }
                    row.append(checkbox, text);
                    checkbox.addEventListener('change', () => {
                        if (checkbox.checked) appendSelectedJob(job);
                        else {
                            selectedJobs.delete(String(job.id));
                            document.querySelector(`[data-selected-job="${CSS.escape(String(job.id))}"]`)?.remove();
                            renderSummary();
                        }
                    });
                    jobResults.append(row);
                }
                if (jobs.length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500';
                    empty.textContent = labels.noJobs;
                    jobResults.append(empty);
                }
            };

            const loadCustomerJobs = async (id) => {
                jobResults.replaceChildren();
                const url = form.dataset.jobsUrl.replace('__CUSTOMER__', encodeURIComponent(id));
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`Unable to load eligible repair jobs (${response.status}).`);
                const result = await response.json();
                renderJobs(result.data || []);
                if (String(customerId.value) === String(initialCustomerId)) {
                    for (const job of initialJobs) {
                        const fixed = existingIds.has(String(job.id));
                        appendSelectedJob(job, fixed);
                        const checkbox = document.querySelector(`[data-job-option="${CSS.escape(String(job.id))}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            checkbox.disabled = fixed;
                        }
                    }
                }
            };

            if (customerSearch) {
                customerSearch.addEventListener('input', () => {
                    clearTimeout(debounce);
                    debounce = setTimeout(async () => {
                        try {
                            const term = customerSearch.value.trim();
                            if (term.length < 2) {
                                customerResults.classList.add('hidden');
                                customerResults.replaceChildren();
                                return;
                            }
                            const url = new URL(form.dataset.customerSearch, window.location.origin);
                            url.searchParams.set('q', term);
                            const response = await fetch(url, { headers: { Accept: 'application/json' } });
                            if (!response.ok) throw new Error(`Unable to search customers (${response.status}).`);
                            const result = await response.json();
                            customerResults.replaceChildren();
                            for (const customer of result.data || []) {
                                const button = document.createElement('button');
                                button.type = 'button';
                                button.className = 'block w-full border-b border-slate-100 px-4 py-3 text-left text-sm hover:bg-teal-50';
                                button.textContent = `${customer.customer_code} · ${customer.name} · ${customer.mobile_number || ''}`;
                                button.addEventListener('click', async () => {
                                    customerId.value = customer.id;
                                    selectedCustomer.classList.remove('hidden');
                                    selectedCustomer.textContent = `${customer.name} · ${customer.address || ''} · ${customer.mobile_number || ''}`;
                                    customerResults.classList.add('hidden');
                                    selectedJobs.clear();
                                    try {
                                        await loadCustomerJobs(customer.id);
                                    } catch (error) {
                                        jobResults.textContent = error.message;
                                    }
                                });
                                customerResults.append(button);
                            }
                            if ((result.data || []).length === 0) {
                                const empty = document.createElement('p');
                                empty.className = 'px-4 py-3 text-sm text-slate-500';
                                empty.textContent = @js(__('invoices.no_customer_match'));
                                customerResults.append(empty);
                            }
                            customerResults.classList.remove('hidden');
                        } catch (error) {
                            customerResults.replaceChildren();
                            const failure = document.createElement('p');
                            failure.className = 'px-4 py-3 text-sm text-red-700';
                            failure.textContent = error.message;
                            customerResults.append(failure);
                            customerResults.classList.remove('hidden');
                        }
                    }, 200);
                });
                if (customerId.value) {
                    loadCustomerJobs(customerId.value).catch((error) => { jobResults.textContent = error.message; });
                }
            } else {
                if (customerId.value) {
                    loadCustomerJobs(customerId.value).catch((error) => { jobResults.textContent = error.message; });
                }
            }

            form.addEventListener('click', (event) => {
                const button = event.target.closest('[data-confirm]');
                if (button && !window.confirm(button.dataset.confirm)) event.preventDefault();
            });
            form.addEventListener('submit', (event) => {
                if (!customerId.value && customerSearch) {
                    event.preventDefault();
                    customerSearch.focus();
                }
            });
        })();
    </script>
@endsection
