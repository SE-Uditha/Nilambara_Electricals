@extends('layouts.app')

@section('title', $creditCollection ? __('payments.collect_credit') : __('payments.record_payment'))

@section('content')
    <div class="mb-7">
        <a href="{{ $creditCollection ? route('credit-invoices.index') : route('payments.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← {{ __('payments.back_to_payments') }}</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">{{ $creditCollection ? __('payments.collect_credit') : __('payments.record_payment') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('payments.payment_entry_help') }}</p>
    </div>

    @if ($errors->any())<div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if (! $invoice)
        <form method="GET" action="{{ route('payments.create') }}" class="mb-4 flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4">
            <div class="min-w-[240px] flex-1"><label for="invoice_q" class="form-label">{{ __('payments.find_invoice') }}</label><input id="invoice_q" name="invoice_q" value="{{ $invoiceSearch }}" placeholder="{{ __('payments.invoice_search_hint') }}" class="form-input"></div>
            <button class="self-end rounded-xl border border-teal-700 px-4 py-2.5 text-xs font-semibold text-teal-800 hover:bg-teal-50">{{ __('ui.search') }}</button>
        </form>
    @endif

    <form method="POST" action="{{ route('payments.store') }}" class="space-y-5" id="payment-form" data-jobs-url="{{ route('payments.jobs', ['customer' => '__CUSTOMER__']) }}">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        @if ($invoice)
            <input type="hidden" name="payment_type" value="invoice">
            <input type="hidden" name="invoice_id" value="{{ $invoice->getKey() }}">
            <section class="rounded-2xl border border-teal-200 bg-teal-50 p-5">
                <div class="flex flex-wrap justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold text-teal-900">{{ __('payments.invoice') }} · {{ $invoice->invoice_number }}</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $invoice->customer->display_name }}</p>
                        <p class="mt-1 text-xs text-slate-600">{{ __('payments.invoice_date') }}: {{ $invoice->invoice_date->format(config('business.date_format')) }} · {{ __('payments.terms_'.$invoice->payment_terms->value) }}</p>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-right text-xs">
                        <dt>{{ __('payments.invoice_total') }}</dt><dd class="font-semibold">{{ config('business.currency') }} {{ $invoice->total }}</dd>
                        <dt>{{ __('payments.advance_applied') }}</dt><dd>{{ config('business.currency') }} {{ $invoice->payment_summary['advance_paid'] }}</dd>
                        <dt>{{ __('payments.previous_payments') }}</dt><dd>{{ config('business.currency') }} {{ $invoice->payment_summary['previous_payments'] }}</dd>
                        <dt class="font-bold text-teal-900">{{ __('payments.outstanding') }}</dt><dd class="font-bold text-teal-900">{{ config('business.currency') }} {{ $invoice->payment_summary['outstanding'] }}</dd>
                    </dl>
                </div>
            </section>
        @else
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="mb-4 flex gap-3">
                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm"><input type="radio" name="payment_type" value="invoice" @checked(old('payment_type', 'invoice') === 'invoice')>{{ __('payments.invoice_payment') }}</label>
                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm"><input type="radio" name="payment_type" value="advance" @checked(old('payment_type') === 'advance')>{{ __('payments.type_advance') }}</label>
                </div>
                <div id="invoice-choice" class="space-y-2">
                    <label for="invoice_id" class="form-label">{{ __('payments.select_invoice') }}</label>
                    <select id="invoice_id" name="invoice_id" class="form-input">
                        <option value="">{{ __('payments.select_invoice') }}</option>
                        @foreach ($openInvoices as $openInvoice)
                            <option value="{{ $openInvoice->getKey() }}" @selected((string) old('invoice_id') === (string) $openInvoice->getKey())>{{ $openInvoice->invoice_number }} · {{ $openInvoice->customer->display_name }} · {{ config('business.currency') }} {{ $openInvoice->total }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-slate-500">{{ __('payments.select_invoice_help') }}</p>
                </div>
                <div id="advance-choice" class="mt-4 hidden grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="customer_id" class="form-label">{{ __('payments.customer') }}</label>
                        <select id="customer_id" name="customer_id" class="form-input">
                            <option value="">{{ __('payments.select_customer') }}</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->getKey() }}" @selected((string) old('customer_id') === (string) $customer->getKey())>{{ $customer->customer_code }} · {{ $customer->display_name }} · {{ $customer->mobile_number }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="repair_job_id" class="form-label">{{ __('payments.repair_job_optional') }}</label>
                        <select id="repair_job_id" name="repair_job_id" class="form-input"><option value="">{{ __('payments.customer_level_advance') }}</option></select>
                        <p id="job-loading" class="mt-1 text-[11px] text-slate-500" aria-live="polite"></p>
                    </div>
                </div>
            </section>
        @endif

        <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-2">
            @if (auth()->user()->can('payments.receive-for-others'))
                <div class="md:col-span-2">
                    <label for="received_by_employee_id" class="form-label">{{ __('payments.received_by') }}</label>
                    <select id="received_by_employee_id" name="received_by_employee_id" class="form-input" required>
                        <option value="">{{ __('payments.select_receiver') }}</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->getKey() }}" @selected((string) old('received_by_employee_id', $currentEmployee?->getKey()) === (string) $employee->getKey())>{{ $employee->employee_code }} · {{ $employee->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-500">{{ __('payments.receiver_recording_help') }}</p>
                    @error('received_by_employee_id')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
                </div>
            @else
                <div class="md:col-span-2 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    <span class="font-semibold text-slate-800">{{ __('payments.received_by') }}:</span>
                    {{ auth()->user()->employee?->name ?: __('payments.active_receiver_missing') }}
                    <span class="ml-2">{{ __('payments.receiver_is_authenticated_employee') }}</span>
                </div>
            @endif
            <div>
                <label for="payment_date" class="form-label">{{ __('payments.payment_date') }}</label>
                <input id="payment_date" name="payment_date" type="date" required max="{{ now(config('business.timezone'))->toDateString() }}" value="{{ old('payment_date', now(config('business.timezone'))->toDateString()) }}" class="form-input">
                @error('payment_date')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="amount" class="form-label">{{ __('payments.amount_received') }}</label>
                <div class="relative"><span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs text-slate-500">{{ config('business.currency') }}</span><input id="amount" name="amount" type="number" min="0.01" max="99999999999999999.99" step="0.01" required value="{{ old('amount') }}" class="form-input pl-14"></div>
                @error('amount')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="method" class="form-label">{{ __('payments.payment_method') }}</label>
                <select id="method" name="method" required class="form-input">@foreach (\App\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}" @selected(old('method') === $method->value)>{{ __('payments.method_'.$method->value) }}</option>@endforeach</select>
            </div>
            <div>
                <label for="reference_number" class="form-label">{{ __('payments.reference_number') }}</label>
                <input id="reference_number" name="reference_number" maxlength="100" value="{{ old('reference_number') }}" class="form-input">
                @error('reference_number')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="bank_name" class="form-label">{{ __('payments.bank_name') }}</label>
                <input id="bank_name" name="bank_name" maxlength="150" value="{{ old('bank_name') }}" class="form-input">
            </div>
            <div id="cheque-date-field" class="hidden">
                <label for="cheque_date" class="form-label">{{ __('payments.cheque_date') }}</label>
                <input id="cheque_date" name="cheque_date" type="date" value="{{ old('cheque_date') }}" class="form-input">
            </div>
            <div class="md:col-span-2">
                <label for="notes" class="form-label">{{ __('payments.notes') }}</label>
                <textarea id="notes" name="notes" rows="3" maxlength="5000" class="form-input">{{ old('notes') }}</textarea>
            </div>
        </section>

        <div class="flex justify-end gap-3"><a href="{{ route('payments.index') }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('ui.cancel') }}</a><button class="rounded-xl bg-teal-700 px-5 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('payments.save_payment') }}</button></div>
    </form>

    @if (! $invoice)
        <script>
            (() => {
                const radios = [...document.querySelectorAll('[name="payment_type"]')];
                const invoiceChoice = document.getElementById('invoice-choice');
                const advanceChoice = document.getElementById('advance-choice');
                const customer = document.getElementById('customer_id');
                const jobs = document.getElementById('repair_job_id');
                const loading = document.getElementById('job-loading');
                const urlTemplate = document.getElementById('payment-form').dataset.jobsUrl;
                const updateType = () => {
                    const advance = document.querySelector('[name="payment_type"]:checked')?.value === 'advance';
                    invoiceChoice?.classList.toggle('hidden', advance);
                    advanceChoice?.classList.toggle('hidden', !advance);
                    const invoiceSelect = document.getElementById('invoice_id');
                    if (invoiceSelect) invoiceSelect.disabled = advance;
                    if (customer) customer.disabled = !advance;
                    if (jobs) jobs.disabled = !advance;
                };
                const loadJobs = async () => {
                    jobs.replaceChildren();
                    jobs.add(new Option(@js(__('payments.customer_level_advance')), ''));
                    if (!customer.value) return;
                    loading.textContent = @js(__('payments.loading_jobs'));
                    try {
                        const response = await fetch(urlTemplate.replace('__CUSTOMER__', encodeURIComponent(customer.value)), { headers: { Accept: 'application/json' } });
                        if (!response.ok) throw new Error(@js(__('payments.jobs_load_failed')));
                        const result = await response.json();
                        for (const job of result.data || []) jobs.add(new Option(`${job.number} · ${job.item} · ${job.status}`, job.id));
                        loading.textContent = '';
                    } catch (error) {
                        loading.textContent = error.message;
                    }
                };
                radios.forEach((radio) => radio.addEventListener('change', updateType));
                customer?.addEventListener('change', loadJobs);
                document.getElementById('method').addEventListener('change', (event) => {
                    const cheque = event.target.value === 'cheque';
                    document.getElementById('cheque-date-field').classList.toggle('hidden', !cheque);
                    document.getElementById('reference_number').required = event.target.value !== 'cash';
                });
                updateType();
                if (customer?.value) loadJobs();
            })();
        </script>
    @endif
@endsection
