@extends('layouts.app')

@section('title', __('ui.customers'))

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.workshop') }}</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.customers') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('ui.customer_list_description') }}</p>
        </div>
        @can('customers.create')
            <a href="{{ route('customers.create') }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white transition hover:bg-teal-800">{{ __('ui.register_customer') }}</a>
        @endcan
    </div>

    @if (session('status'))
        <div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('customers.index') }}" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,1fr)_180px_160px_auto_auto]">
        <label class="sr-only" for="customer-search">{{ __('ui.search_customers') }}</label>
        <input id="customer-search" name="q" value="{{ request('q') }}" class="form-input" placeholder="{{ __('ui.customer_search_placeholder') }}">
        <select name="type" class="form-input" aria-label="{{ __('ui.customer_type') }}">
            <option value="">{{ __('ui.all_types') }}</option>
            <option value="individual" @selected(request('type') === 'individual')>{{ __('ui.individual') }}</option>
            <option value="company" @selected(request('type') === 'company')>{{ __('ui.company') }}</option>
        </select>
        <select name="status" class="form-input" aria-label="{{ __('ui.status') }}">
            <option value="">{{ __('ui.all_statuses') }}</option>
            <option value="active" @selected(request('status') === 'active')>{{ __('ui.active') }}</option>
            <option value="inactive" @selected(request('status') === 'inactive')>{{ __('ui.inactive') }}</option>
        </select>
        <button class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('ui.filter') }}</button>
        @if (request()->hasAny(['q', 'type', 'status']))
            <a href="{{ route('customers.index') }}" class="rounded-xl px-3 py-2 text-center text-xs font-semibold text-slate-500 hover:bg-slate-50">{{ __('ui.clear') }}</a>
        @endif
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[980px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    <tr>
                        <th class="px-5 py-3">{{ __('ui.customer_id') }}</th>
                        <th class="px-5 py-3">{{ __('ui.customer_type') }}</th>
                        <th class="px-5 py-3">{{ __('ui.customer_name') }}</th>
                        <th class="px-5 py-3">{{ __('ui.contact_person') }}</th>
                        <th class="px-5 py-3">{{ __('ui.mobile_number') }}</th>
                        <th class="px-5 py-3">{{ __('ui.address') }}</th>
                        <th class="px-5 py-3">{{ __('ui.registration_date') }}</th>
                        <th class="px-5 py-3">{{ __('ui.status') }}</th>
                        <th class="px-5 py-3">{{ __('ui.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $customer)
                        <tr class="text-slate-700">
                            <td class="px-5 py-4 font-mono text-[11px] text-slate-500">{{ $customer->customer_code }}</td>
                            <td class="px-5 py-4">{{ $customer->customer_type->label() }}</td>
                            <td class="px-5 py-4">
                                <a href="{{ route('customers.show', $customer) }}" class="font-semibold text-slate-900 hover:text-teal-700">{{ $customer->display_name }}</a>
                                @if ($customer->email)<span class="mt-1 block text-[10px] text-slate-400">{{ $customer->email }}</span>@endif
                            </td>
                            <td class="px-5 py-4">{{ $customer->contact_person ?: '—' }}</td>
                            <td class="px-5 py-4">{{ $customer->mobile_number }}</td>
                            <td class="max-w-56 truncate px-5 py-4" title="{{ $customer->address }}">{{ $customer->address }}</td>
                            <td class="px-5 py-4">{{ $customer->registration_date->format(config('business.date_format', 'd M Y')) }}</td>
                            <td class="px-5 py-4"><span @class(['rounded-full px-2.5 py-1 text-[10px] font-semibold', 'bg-emerald-50 text-emerald-700' => $customer->is_active, 'bg-slate-100 text-slate-500' => ! $customer->is_active])>{{ $customer->is_active ? __('ui.active') : __('ui.inactive') }}</span></td>
                            <td class="px-5 py-4">
                                <div class="flex gap-3">
                                    <a href="{{ route('customers.show', $customer) }}" class="font-semibold text-teal-700 hover:text-teal-900">{{ __('ui.view') }}</a>
                                    @can('customers.update')
                                        <a href="{{ route('customers.edit', $customer) }}" class="font-semibold text-slate-600 hover:text-slate-900">{{ __('ui.edit') }}</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-14 text-center"><p class="font-semibold text-slate-700">{{ __('ui.no_customers_found') }}</p><p class="mt-1 text-xs text-slate-400">{{ __('ui.no_customers_description') }}</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
            <p class="text-xs text-slate-500">{{ __('ui.showing_customers', ['count' => $customers->count(), 'total' => $customers->total()]) }}</p>
            @if ($customers->hasPages()){{ $customers->links() }}@endif
        </div>
    </div>
@endsection
