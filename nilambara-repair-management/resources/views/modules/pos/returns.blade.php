@extends('layouts.app')

@section('title', __('pos.returns'))

@section('content')
    <div class="mb-6"><p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.sales_finance') }}</p><h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('pos.returns') }}</h1></div>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="overflow-x-auto"><table class="w-full min-w-[700px] text-left text-xs">
        <thead class="bg-slate-50 text-[10px] font-semibold uppercase text-slate-500"><tr><th class="px-4 py-3">{{ __('pos.return_number') }}</th><th class="px-4 py-3">{{ __('payments.date') }}</th><th class="px-4 py-3">{{ __('pos.original_sale') }}</th><th class="px-4 py-3">{{ __('pos.product') }}</th><th class="px-4 py-3">{{ __('pos.quantity') }}</th><th class="px-4 py-3">{{ __('pos.refund_amount') }}</th><th class="px-4 py-3">{{ __('pos.processed_by') }}</th></tr></thead>
        <tbody class="divide-y divide-slate-100">@forelse ($returns as $return)@php($item = $return->items->first())<tr><td class="px-4 py-3 font-mono font-semibold">{{ $return->return_number }}</td><td class="px-4 py-3">{{ $return->returned_at->timezone(config('business.timezone'))->format(config('business.date_format')) }}</td><td class="px-4 py-3"><a class="text-teal-800 hover:underline" href="{{ route('pos.sales.show', $return->posSale) }}">{{ $return->posSale?->sale_number }}</a></td><td class="px-4 py-3">{{ $item?->posSaleItem?->description }}</td><td class="px-4 py-3">{{ $item?->quantity }} · {{ __('pos.'.$item?->condition) }}</td><td class="px-4 py-3">{{ config('business.currency') }} {{ $return->refund_amount }}</td><td class="px-4 py-3">{{ $return->recordedByUser?->name }}</td></tr>@empty<tr><td colspan="7" class="px-4 py-14 text-center text-sm text-slate-500">{{ __('pos.no_returns') }}</td></tr>@endforelse</tbody></table></div>
        @if ($returns->hasPages())<div class="border-t border-slate-100 px-4 py-4">{{ $returns->links() }}</div>@endif
    </div>
@endsection
