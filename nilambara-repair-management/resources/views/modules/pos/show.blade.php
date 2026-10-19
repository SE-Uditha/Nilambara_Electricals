@extends('layouts.app')

@section('title', $sale->sale_number)

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3"><div><p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('pos.title') }}</p><h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $sale->sale_number }}</h1><p class="mt-1 text-sm text-slate-500">{{ $sale->sale_at->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }}</p></div>
        <div class="flex flex-wrap gap-2">@can('pos.sales.view')<a href="{{ route('pos.sales.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold">{{ __('pos.sales_history') }}</a>@endcan
            @if ($sale->status === \App\Enums\DocumentStatus::Finalized && auth()->user()->can('pos.receipt.print'))<a href="{{ route('pos.sales.print', $sale) }}" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white">{{ __('pos.print_receipt') }}</a><a href="{{ route('pos.sales.pdf', ['sale' => $sale, 'paper' => 'A5']) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold">{{ __('payments.download_pdf') }}</a>@endif
        </div>
    </div>
    @if (session('status'))<div role="status" class="mb-4 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>@endif
    @if ($errors->any())<div role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="mb-5 grid gap-3 md:grid-cols-4">
        @foreach ([
            __('pos.sale_status') => __('pos.sale_status_'.$sale->status->value),
            __('pos.payment_status') => __('pos.payment_status_'.$sale->payment_status),
            __('pos.customer') => $sale->customer?->display_name ?: __('pos.walk_in'),
            __('pos.cashier') => $sale->cashierEmployee?->name,
        ] as $label => $value)<div class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-2 text-sm font-semibold text-slate-900">{{ $value }}</p></div>@endforeach
    </div>

    @if ($sale->status === \App\Enums\DocumentStatus::Draft && $sale->payment_status === 'pending')
        <div class="mb-5 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <div><p class="font-semibold text-amber-950">{{ __('pos.payment_verification_required') }}</p><p class="mt-1 text-xs text-amber-900">{{ __('payments.method_'.$sale->payment_method) }} · {{ $sale->payment_reference }} @if($sale->bank_name) · {{ $sale->bank_name }} @endif</p><p class="mt-1 text-xs text-amber-900">{{ __('pos.pending_payment_no_stock') }}</p></div>
            <div class="flex gap-2">@can('pos.payment.verify')<form method="POST" action="{{ route('pos.sales.verify', $sale) }}" onsubmit="return confirm('{{ __('pos.confirm_verify') }}')">@csrf<button class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white">{{ __('pos.verify_and_finalize') }}</button></form>@endcan
                @can('pos.sale.cancel')<form method="POST" action="{{ route('pos.sales.cancel', $sale) }}" class="flex gap-2">@csrf<input name="reason" required minlength="5" class="form-input w-44" placeholder="{{ __('pos.cancellation_reason') }}"><button class="rounded-xl border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700">{{ __('pos.cancel_pending') }}</button></form>@endcan</div>
        </div>
    @endif

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-xs"><thead class="bg-slate-50 text-[10px] font-semibold uppercase text-slate-500"><tr><th class="px-4 py-3">{{ __('pos.product') }}</th><th class="px-4 py-3">{{ __('pos.location') }}</th><th class="px-4 py-3 text-right">{{ __('pos.quantity') }}</th><th class="px-4 py-3 text-right">{{ __('pos.unit_price') }}</th><th class="px-4 py-3 text-right">{{ __('pos.line_total') }}</th><th class="px-4 py-3">{{ __('pos.stock_reference') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">@foreach ($sale->items as $item)<tr><td class="px-4 py-3">{{ $item->description }}</td><td class="px-4 py-3">{{ $item->location?->name }}</td><td class="px-4 py-3 text-right">{{ $item->quantity }}</td><td class="px-4 py-3 text-right">{{ config('business.currency') }} {{ $item->unit_price }}</td><td class="px-4 py-3 text-right font-semibold">{{ config('business.currency') }} {{ $item->line_total }}</td><td class="px-4 py-3">{{ $item->stockMovements?->reference_number ?: '—' }}</td></tr>@endforeach</tbody></table></div>
        <div class="ml-auto max-w-sm space-y-2 border-t border-slate-100 p-4 text-sm"><div class="flex justify-between"><span>{{ __('pos.subtotal') }}</span><span>{{ config('business.currency') }} {{ $sale->subtotal }}</span></div><div class="flex justify-between"><span>{{ __('pos.discount') }}</span><span>{{ config('business.currency') }} {{ $sale->discount_amount }}</span></div><div class="flex justify-between border-t border-slate-100 pt-2 font-bold"><span>{{ __('pos.total') }}</span><span>{{ config('business.currency') }} {{ $sale->total }}</span></div><div class="flex justify-between text-xs"><span>{{ __('pos.payment_method') }}</span><span>{{ $sale->payment_method ? __('payments.method_'.$sale->payment_method) : '—' }}</span></div>
            @if ($sale->payment_method === 'cash')<div class="flex justify-between text-xs"><span>{{ __('pos.amount_tendered') }}</span><span>{{ config('business.currency') }} {{ $sale->amount_tendered }}</span></div><div class="flex justify-between text-xs"><span>{{ __('pos.change_due') }}</span><span>{{ config('business.currency') }} {{ $sale->change_due }}</span></div>@endif
            @foreach ($sale->paymentAllocations as $allocation)<div class="flex justify-between text-xs"><span>{{ __('pos.payment_reference') }}</span><span>{{ $allocation->payment?->payment_number }} · {{ config('business.currency') }} {{ $allocation->amount }}</span></div>@endforeach
        </div>
    </section>

    @if ($sale->status === \App\Enums\DocumentStatus::Finalized && auth()->user()->can('pos.return'))
        <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4"><h2 class="mb-3 text-sm font-semibold text-slate-900">{{ __('pos.process_return') }}</h2><div class="space-y-4">
            @foreach ($sale->items as $item)
                @php($returned = $item->returnItems->where('stockReturn.status', \App\Enums\DocumentStatus::Finalized)->sum('quantity'))
                @php($remaining = \Brick\Math\BigDecimal::of((string) $item->quantity)->minus((string) $returned)->toScale(3))
                @if ($remaining->isGreaterThan(0))
                    <form method="POST" action="{{ route('pos.returns.store') }}" class="grid gap-2 rounded-xl border border-slate-100 p-3 md:grid-cols-7">@csrf
                        <input type="hidden" name="pos_sale_item_id" value="{{ $item->id }}"><input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <div class="md:col-span-2"><p class="text-xs font-semibold">{{ $item->description }}</p><p class="text-[10px] text-slate-500">{{ __('pos.return_remaining') }} {{ $remaining }}</p></div>
                        <input name="quantity" type="number" min="0.001" max="{{ $remaining }}" step="{{ $item->sparePart->unit->quantity_scale ? '0.'.str_repeat('0', $item->sparePart->unit->quantity_scale - 1).'1' : '1' }}" required class="form-input" placeholder="{{ __('pos.quantity') }}">
                        <select name="condition" required class="form-input"><option value="resalable">{{ __('pos.resalable') }}</option><option value="damaged">{{ __('pos.damaged') }}</option></select>
                        <input name="refund_amount" type="number" min="0" max="{{ $item->line_total }}" step="0.01" value="0.00" class="form-input" placeholder="{{ __('pos.refund_amount') }}">
                        <select name="refund_method" class="form-input"><option value="">{{ __('pos.no_refund') }}</option>@foreach (\App\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}">{{ __('payments.method_'.$method->value) }}</option>@endforeach</select>
                        <div class="flex gap-2"><input name="reason" required minlength="5" class="form-input min-w-0" placeholder="{{ __('pos.return_reason') }}"><button class="rounded-lg bg-teal-700 px-3 text-xs font-semibold text-white">{{ __('pos.record_return') }}</button></div>
                    </form>
                @endif
            @endforeach
        </div></section>
    @endif

    @if ($sale->stockReturns->isNotEmpty())<section class="mt-5 rounded-2xl border border-slate-200 bg-white p-4"><h2 class="mb-3 text-sm font-semibold">{{ __('pos.return_history') }}</h2><div class="space-y-2">@foreach ($sale->stockReturns as $return)<div class="flex flex-wrap justify-between gap-2 rounded-xl bg-slate-50 p-3 text-xs"><span>{{ $return->return_number }} · {{ $return->items->first()?->posSaleItem?->description }}</span><span>{{ $return->items->first()?->quantity }} · {{ __('pos.'.$return->items->first()?->condition) }} · {{ __('pos.refund_amount') }} {{ config('business.currency') }} {{ $return->refund_amount }}</span></div>@endforeach</div></section>@endif
@endsection
