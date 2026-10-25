@extends('layouts.app')

@section('title', __('ui.new_repair_job'))

@section('content')
    <div class="mb-7">
        <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.repair_jobs') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.new_repair_job') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('ui.job_acceptance_description') }}</p>
    </div>

    @if ($errors->any())
        <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ __('ui.review_repair_receipt_fields') }}</div>
    @endif

    <form method="POST" action="{{ route('repair-jobs.store') }}" x-data="{ selectedCustomer: @js($selectedCustomer), advancePayment: @js(old('advance_payment', '0.00')), showLabour: @js(old('labor_cost') !== null), labourCost: @js(old('labor_cost', '')), submitting: false }" x-on:customer-selected="selectedCustomer = $event.detail" x-on:submit="if (submitting) { $event.preventDefault(); return; } setTimeout(() => submitting = true, 0)">
        @csrf
        <div class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">{{ __('ui.customer_information') }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ __('ui.customer_receipt_info_help') }}</p>
                    </div>
                    @can('customers.create')
                        <a href="{{ route('customers.create') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">{{ __('ui.register_customer') }}</a>
                    @endcan
                </div>
                <x-customer-search name="customer_id" :required="true" :customer="$selectedCustomer" />
                @error('customer_id')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
                <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div><span class="form-label">{{ __('ui.customer_id') }}</span><p class="text-sm font-medium text-slate-700" x-text="selectedCustomer?.customer_code || '—'"></p></div>
                    <div><span class="form-label">{{ __('ui.customer_type') }}</span><p class="text-sm font-medium text-slate-700" x-text="selectedCustomer?.type || '—'"></p></div>
                    <div><span class="form-label">{{ __('ui.customer_name') }}</span><p class="text-sm font-medium text-slate-700" x-text="selectedCustomer?.name || '—'"></p></div>
                    <div><span class="form-label">{{ __('ui.contact_person') }}</span><p class="text-sm font-medium text-slate-700" x-text="selectedCustomer?.contact_person || '—'"></p></div>
                    <div><span class="form-label">{{ __('ui.address') }}</span><p class="text-sm font-medium text-slate-700" x-text="selectedCustomer?.address || '—'"></p></div>
                    <div><span class="form-label">{{ __('ui.mobile_number') }}</span><p class="text-sm font-medium text-slate-700" x-text="selectedCustomer?.mobile_number || '—'"></p></div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <h2 class="mb-5 text-sm font-bold text-slate-900">{{ __('ui.job_information') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label class="form-label" for="automatic-reference">{{ __('ui.automatic_job_reference') }}</label>
                        <input id="automatic-reference" value="{{ __('ui.generated_after_save') }}" class="form-input bg-slate-50" readonly>
                    </div>
                    <div>
                        <label class="form-label" for="job_number">{{ __('ui.manual_job_number') }} <span class="text-red-600">*</span></label>
                        <input id="job_number" name="job_number" value="{{ old('job_number') }}" class="form-input" required maxlength="60">
                        @error('job_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="form-label" for="received_at">{{ __('ui.received_date_time') }}</label>
                        <input id="received_at" value="{{ $receivedAt->format(config('business.date_format').' '.config('business.time_format')) }}" class="form-input bg-slate-50" readonly>
                        <p class="mt-1 text-[10px] text-slate-400">{{ config('business.timezone') }}</p>
                    </div>
                    <div>
                        <label class="form-label" for="job_type">{{ __('ui.job_type') }} <span class="text-red-600">*</span></label>
                        <select id="job_type" name="job_type" class="form-input" required>
                            @foreach (['normal', 'special', 'urgent'] as $type)
                                <option value="{{ $type }}" @selected(old('job_type', 'normal') === $type)>{{ __('ui.job_type_'.$type) }}</option>
                            @endforeach
                        </select>
                        @error('job_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <h2 class="mb-5 text-sm font-bold text-slate-900">{{ __('ui.appliance_information') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="form-label" for="item_name">{{ __('ui.item_name') }} <span class="text-red-600">*</span></label>
                        <input id="item_name" name="item_name" value="{{ old('item_name') }}" class="form-input" required maxlength="255">
                        @error('item_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label" for="brand">{{ __('ui.brand') }}</label>
                            <input id="brand" name="brand" value="{{ old('brand') }}" class="form-input" maxlength="150">
                            @error('brand')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="model">{{ __('ui.model') }}</label>
                            <input id="model" name="model" value="{{ old('model') }}" class="form-input" maxlength="150">
                            @error('model')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <p class="col-span-2 text-[10px] text-slate-400">{{ __('ui.brand_or_model_required') }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label" for="item_description">{{ __('ui.item_description') }}</label>
                        <textarea id="item_description" name="item_description" rows="3" class="form-input">{{ old('item_description') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label" for="reported_fault">{{ __('ui.reported_fault') }}</label>
                        @include('modules.repair-jobs.partials.quick-add', ['config' => 'common_faults', 'target' => 'reported_fault'])
                        <textarea id="reported_fault" name="reported_fault" rows="4" class="form-input">{{ old('reported_fault') }}</textarea>
                    </div>
                    <div>
                        <label class="form-label" for="accessories_received">{{ __('ui.accessories_received') }}</label>
                        <textarea id="accessories_received" name="accessories_received" rows="3" class="form-input">{{ old('accessories_received') }}</textarea>
                    </div>
                    <div>
                        <label class="form-label" for="physical_condition">{{ __('ui.physical_condition') }}</label>
                        @include('modules.repair-jobs.partials.quick-add', ['config' => 'common_physical_conditions', 'target' => 'physical_condition'])
                        <textarea id="physical_condition" name="physical_condition" rows="3" class="form-input">{{ old('physical_condition') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label" for="visible_damage">{{ __('ui.visible_damage') }}</label>
                        @include('modules.repair-jobs.partials.quick-add', ['config' => 'common_visible_damages', 'target' => 'visible_damage'])
                        <textarea id="visible_damage" name="visible_damage" rows="3" class="form-input">{{ old('visible_damage') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="form-label" for="special_notes">{{ __('ui.special_notes') }}</label>
                        <textarea id="special_notes" name="special_notes" rows="3" class="form-input">{{ old('special_notes') }}</textarea>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <h2 class="mb-5 text-sm font-bold text-slate-900">{{ __('ui.payment_information') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @if (auth()->user()->can('payments.create') && auth()->user()->can('payments.receive'))
                        <div>
                            <label class="form-label" for="advance_payment">{{ __('ui.advance_payment') }}</label>
                            <input id="advance_payment" name="advance_payment" type="number" min="0" step="0.01" value="{{ old('advance_payment', '0.00') }}" x-model="advancePayment" class="form-input">
                            @error('advance_payment')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div x-show="Number(advancePayment || 0) > 0">
                            <label class="form-label" for="payment_method">{{ __('ui.payment_method') }} <span class="text-red-600">*</span></label>
                            <select id="payment_method" name="payment_method" x-bind:required="Number(advancePayment || 0) > 0" class="form-input">
                                <option value="">{{ __('ui.select_payment_method') }}</option>
                                @foreach (['cash', 'bank_transfer', 'cheque'] as $method)
                                    <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ __('ui.payment_method_'.$method) }}</option>
                                @endforeach
                            </select>
                            @error('payment_method')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @else
                        <input type="hidden" name="advance_payment" value="0.00">
                        <p class="text-xs text-slate-500">{{ __('payments.advance_entry_restricted') }}</p>
                    @endif

                </div>
                <div class="mt-4">
                    <button type="button" class="rounded-xl border border-teal-200 bg-teal-50 px-4 py-2 text-xs font-semibold text-teal-800 hover:bg-teal-100" x-on:click="showLabour = ! showLabour; if (! showLabour) labourCost = ''" x-text="showLabour ? 'Remove labour / inspection charge' : '+ Add labour / inspection charge'"></button>
                    <div class="mt-3 max-w-xs" x-show="showLabour" x-cloak>
                        <label class="form-label" for="labor_cost">{{ __('ui.labor_cost') }}</label>
                        <input id="labor_cost" name="labor_cost" type="number" min="0" step="0.01" x-model="labourCost" x-bind:disabled="! showLabour" class="form-input">
                        <p class="mt-1 text-[11px] text-slate-500">Optional. Use when the item is inspected and the customer declines repair, so the inspection is still charged.</p>
                        @error('labor_cost')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <p class="mt-3 text-[11px] text-slate-500">{{ __('ui.advance_payment_ledger_help') }}</p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <h2 class="mb-5 text-sm font-bold text-slate-900">{{ __('ui.received_by') }}</h2>
                <div class="max-w-md">
                    <label class="form-label" for="received_by_employee_id">{{ __('ui.received_by') }} <span class="text-red-600">*</span></label>
                    <select id="received_by_employee_id" name="received_by_employee_id" class="form-input" required>
                        <option value="">{{ __('ui.select_employee') }}</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected((string) old('received_by_employee_id', $defaultReceivedById) === (string) $employee->id)>{{ $employee->name }}</option>
                        @endforeach
                    </select>
                    @error('received_by_employee_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-3">
                <a href="{{ route('repair-jobs.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">{{ __('ui.cancel') }}</a>
                <button type="submit" name="intent" value="save" x-bind:disabled="submitting" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 disabled:opacity-60">{{ __('ui.accept_repair_job') }}</button>
                @can('receipts.print')
                    <button type="submit" name="intent" value="print" x-bind:disabled="submitting" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800 disabled:opacity-60">{{ __('ui.accept_and_print_receipt') }}</button>
                @endcan
            </div>
        </div>
    </form>
@endsection
