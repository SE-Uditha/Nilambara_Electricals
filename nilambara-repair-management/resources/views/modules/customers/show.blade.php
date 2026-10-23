@extends('layouts.app')

@section('title', $customer->display_name)

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('customers.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← {{ __('ui.customers') }}</a>
            <p class="mt-4 font-mono text-[11px] text-slate-400">{{ $customer->customer_code }}</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ $customer->display_name }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $customer->customer_type->label() }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('customers.update')
                <a href="{{ route('customers.edit', $customer) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.edit_customer') }}</a>
            @endcan
            @if (\App\Modules\Approvals\Services\ApprovalAccess::canRequestChange(auth()->user(), 'customer_update'))
                <a href="{{ route('customers.request-edit', $customer) }}" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-semibold text-amber-900 hover:bg-amber-100">{{ __('ui.request_edit') }}</a>
            @endif
            @if ($customer->is_active)
                @can('customers.deactivate')
                    <form method="POST" action="{{ route('customers.deactivate', $customer) }}" data-confirm="{{ __('ui.confirm_deactivate_customer') }}" onsubmit="return confirm(this.dataset.confirm)">
                        @csrf
                        <button type="submit" class="rounded-xl border border-red-200 bg-white px-4 py-2.5 text-xs font-semibold text-red-700 hover:bg-red-50">{{ __('ui.deactivate') }}</button>
                    </form>
                @endcan
            @else
                @can('customers.update')
                    <form method="POST" action="{{ route('customers.activate', $customer) }}">
                        @csrf
                        <button type="submit" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">{{ __('ui.activate') }}</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>
    @endif

    <div class="grid gap-5 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-bold text-slate-900">{{ __('ui.customer_information') }}</h2>
                <span @class(['rounded-full px-2.5 py-1 text-[10px] font-semibold', 'bg-emerald-50 text-emerald-700' => $customer->is_active, 'bg-slate-100 text-slate-500' => ! $customer->is_active])>{{ $customer->is_active ? __('ui.active') : __('ui.inactive') }}</span>
            </div>
            <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                <div><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('ui.contact_person') }}</dt><dd class="mt-1 text-sm text-slate-700">{{ $customer->contact_person ?: '—' }}</dd></div>
                <div><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('ui.mobile_number') }}</dt><dd class="mt-1 text-sm text-slate-700">{{ $customer->mobile_number }}</dd></div>
                <div><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('ui.alternative_telephone') }}</dt><dd class="mt-1 text-sm text-slate-700">{{ $customer->alternate_phone ?: '—' }}</dd></div>
                <div><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('ui.email_address') }}</dt><dd class="mt-1 break-all text-sm text-slate-700">{{ $customer->email ?: '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('ui.address') }}</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $customer->address }}</dd></div>
                <div><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('ui.registration_date') }}</dt><dd class="mt-1 text-sm text-slate-700">{{ $customer->registration_date->format(config('business.date_format', 'd M Y')) }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('ui.special_notes') }}</dt><dd class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $customer->special_notes ?: '—' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-bold text-slate-900">{{ __('ui.customer_financial_information') }}</h2>
                @can('customers.statement', $customer)
                    <a href="{{ route('customers.statement', $customer) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">{{ __('ui.view_statement') }} →</a>
                @endcan
            </div>
            @can('customers.statement', $customer)
                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('ui.opening_balance') }}</dt><dd class="mt-1 text-lg font-semibold text-slate-800">{{ config('business.currency') }} {{ number_format((float) $customer->opening_balance, 2) }}</dd></div>
                    <div><dt class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('ui.outstanding_credit_balance') }}</dt><dd class="mt-1 text-lg font-semibold text-slate-800">{{ config('business.currency') }} {{ number_format((float) $accountBalance, 2) }}</dd></div>
                </dl>
                <p class="mt-4 text-xs text-slate-500">{{ __('ui.balance_ledger_source') }}</p>
            @else
                <p class="mt-4 text-sm text-slate-500">{{ __('ui.financial_information_restricted') }}</p>
            @endcan
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7 xl:col-span-2">
            <h2 class="text-sm font-bold text-slate-900">{{ __('ui.customer_history') }}</h2>
            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                @foreach ([__('ui.repair_jobs') => $customer->repair_jobs_count] as $label => $count)
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ $label }}</p>
                        <p class="mt-2 text-xl font-bold text-slate-800">{{ $count }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $count ? __('ui.related_records_exist') : __('ui.no_related_records') }}</p>
                    </div>
                @endforeach
                @can('customers.statement', $customer)
                    @foreach ([__('ui.invoices') => $customer->invoices_count, __('ui.payments') => $customer->payments_count] as $label => $count)
                        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                            <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ $label }}</p>
                            <p class="mt-2 text-xl font-bold text-slate-800">{{ $count }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $count ? __('ui.related_records_exist') : __('ui.no_related_records') }}</p>
                        </div>
                    @endforeach
                @endcan
            </div>
            <p class="mt-4 text-xs text-slate-500">{{ __('ui.customer_history_note') }}</p>
        </section>
    </div>
@endsection
