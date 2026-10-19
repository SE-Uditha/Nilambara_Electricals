@extends('layouts.app')

@section('title', __('ui.customer_statement'))

@section('content')
    <div class="mb-7">
        <a href="{{ route('customers.show', $customer) }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← {{ $customer->customer_code }} · {{ $customer->display_name }}</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.customer_statement') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('ui.customer_statement_description') }}</p>
    </div>

    <section class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5">
        <div>
            <p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('ui.outstanding_credit_balance') }}</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ config('business.currency') }} {{ number_format((float) $accountBalance, 2) }}</p>
        </div>
        <p class="max-w-xl text-xs text-slate-500">{{ __('ui.balance_ledger_source') }}</p>
    </section>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    <tr><th class="px-5 py-3">{{ __('ui.date') }}</th><th class="px-5 py-3">{{ __('ui.type') }}</th><th class="px-5 py-3">{{ __('ui.reference') }}</th><th class="px-5 py-3">{{ __('ui.description') }}</th><th class="px-5 py-3 text-right">{{ __('ui.amount') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($entries as $entry)
                        <tr class="text-slate-700">
                            <td class="px-5 py-4">{{ $entry->occurred_at->format(config('business.date_format', 'd M Y')) }}</td>
                            <td class="px-5 py-4">{{ str($entry->entry_type)->replace('_', ' ')->headline() }}</td>
                            <td class="px-5 py-4 font-mono">{{ $entry->reference_number }}</td>
                            <td class="px-5 py-4">{{ $entry->description }}</td>
                            <td class="px-5 py-4 text-right font-semibold">{{ config('business.currency') }} {{ number_format((float) $entry->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-14 text-center text-sm text-slate-500">{{ __('ui.no_statement_entries') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($entries->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $entries->links() }}</div>@endif
    </div>
@endsection
