@extends('layouts.app')

@section('title', __('pos.title'))

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div><p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.sales_finance') }}</p><h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('pos.title') }}</h1><p class="mt-1 text-sm text-slate-500">{{ __('pos.subtitle') }}</p></div>
        @can('pos.sales.view')<a href="{{ route('pos.sales.index') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700">{{ __('pos.sales_history') }}</a>@endcan
    </div>

    @if (session('status'))<div role="status" class="mb-4 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>@endif
    @if ($errors->any())<div role="alert" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="grid items-start gap-4 xl:grid-cols-[1.05fr_1.15fr_.85fr]">
        <section class="rounded-2xl border border-slate-200 bg-white p-4">
            <h2 class="mb-3 text-sm font-semibold text-slate-900">{{ __('pos.scan_product') }}</h2>
            @can('pos.sale')
                <form method="POST" action="{{ route('pos.cart.add') }}" class="mb-5">
                    @csrf
                    <label for="barcode" class="mb-1.5 block text-xs font-medium text-slate-700">{{ __('pos.barcode_sku') }}</label>
                    <div class="flex gap-2"><input id="barcode" name="barcode" value="{{ old('barcode') }}" class="form-input min-w-0 flex-1" autocomplete="off" autofocus placeholder="{{ __('pos.scan_placeholder') }}"><button class="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white">{{ __('pos.add') }}</button></div>
                    <p class="mt-2 text-[11px] text-slate-500">{{ __('pos.scanner_help') }}</p>
                </form>
            @endcan
            <form method="GET" action="{{ route('pos.index') }}" class="mb-4 flex gap-2">
                <input name="q" value="{{ request('q') }}" class="form-input min-w-0 flex-1" placeholder="{{ __('pos.search_products') }}">
                <button class="rounded-xl border border-teal-700 px-4 py-2 text-xs font-semibold text-teal-800">{{ __('ui.search') }}</button>
            </form>
            <div class="space-y-2">
                @forelse ($parts as $part)
                    <article class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 p-3">
                        <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ $part->name }}</p><p class="mt-1 text-[11px] text-slate-500">{{ $part->sku }} · {{ $part->brand?->name ?: __('pos.no_brand') }}</p><p class="mt-1 text-xs text-slate-600">{{ config('business.currency') }} {{ $part->selling_price }} · {{ __('pos.available') }}: {{ $part->saleable_quantity }} {{ $part->unit->code }}</p></div>
                        @can('pos.sale')
                            @if ($part->saleable_quantity->isGreaterThan(0) && $part->defaultLocation?->is_active)
                                <form method="POST" action="{{ route('pos.cart.add') }}" class="shrink-0">@csrf<input type="hidden" name="part_id" value="{{ $part->id }}"><button class="rounded-lg bg-teal-50 px-3 py-2 text-xs font-semibold text-teal-800">{{ __('pos.add') }}</button></form>
                            @else
                                <span class="shrink-0 rounded-lg bg-slate-100 px-3 py-2 text-[11px] text-slate-500">{{ __('pos.out_of_stock') }}</span>
                            @endif
                        @endcan
                    </article>
                @empty
                    @if (request()->filled('q'))<p class="rounded-xl bg-slate-50 p-4 text-center text-xs text-slate-500">{{ __('pos.no_products') }}</p>@else<p class="rounded-xl bg-slate-50 p-4 text-center text-xs text-slate-500">{{ __('pos.search_prompt') }}</p>@endif
                @endforelse
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4">
            <div class="mb-3 flex items-center justify-between gap-2"><h2 class="text-sm font-semibold text-slate-900">{{ __('pos.cart') }}</h2><span class="text-xs text-slate-500">{{ count($cart['items']) }} {{ __('pos.lines') }}</span></div>
            <div class="space-y-3">
                @forelse ($cart['items'] as $line)
                    @php($part = $line['part'])
                    <article class="rounded-xl border border-slate-100 p-3">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ $part->name }}</p><p class="text-[11px] text-slate-500">{{ $part->sku }} · {{ config('business.currency') }} {{ $line['unit_price'] }}</p></div><p class="whitespace-nowrap text-sm font-semibold text-slate-900">{{ config('business.currency') }} {{ $line['line_total'] }}</p></div>
                        <div class="mt-3 flex items-center justify-between">
                            @can('pos.sale')
                                <div class="flex items-center gap-2"><form method="POST" action="{{ route('pos.cart.update', $part) }}" class="flex items-center gap-1">@csrf @method('PUT')<label class="sr-only" for="quantity-{{ $part->id }}">{{ __('pos.quantity') }}</label><input id="quantity-{{ $part->id }}" type="number" name="quantity" min="0.001" max="{{ $line['available'] }}" step="{{ $part->unit->quantity_scale ? '0.'.str_repeat('0', $part->unit->quantity_scale - 1).'1' : '1' }}" value="{{ $line['quantity'] }}" class="form-input w-24 px-2 py-1.5"><button class="rounded-lg border border-slate-200 px-2 py-1.5 text-[11px]">{{ __('pos.update') }}</button></form>
                                <form method="POST" action="{{ route('pos.cart.remove', $part) }}">@csrf @method('DELETE')<button class="text-[11px] font-semibold text-rose-700">{{ __('pos.remove') }}</button></form></div>
                            @endcan
                            <span class="text-[10px] text-slate-500">{{ __('pos.available') }} {{ $line['available'] }}</span>
                        </div>
                    </article>
                @empty
                    <p class="rounded-xl bg-slate-50 p-8 text-center text-sm text-slate-500">{{ __('pos.cart_empty') }}</p>
                @endforelse
            </div>
            @can('pos.sale')
                @if (count($cart['items']))
                    <form method="POST" action="{{ route('pos.cart.clear') }}" class="mt-3 text-right">@csrf @method('DELETE')<button class="text-xs font-semibold text-rose-700">{{ __('pos.clear_cart') }}</button></form>
                @endif
            @endcan
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 xl:sticky xl:top-4">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">{{ __('pos.checkout') }}</h2>
            <div class="space-y-2 border-b border-slate-100 pb-4 text-sm"><div class="flex justify-between"><span class="text-slate-500">{{ __('pos.subtotal') }}</span><span>{{ config('business.currency') }} {{ $cart['subtotal'] }}</span></div>
                <div class="flex justify-between font-semibold text-slate-900"><span>{{ __('pos.total') }}</span><span>{{ config('business.currency') }} {{ $cart['total'] }}</span></div></div>
            @can('pos.sale')
                <form method="POST" action="{{ route('pos.checkout') }}" class="mt-4 space-y-3">
                    @csrf<input type="hidden" name="idempotency_key" value="{{ $checkoutKey }}">
                    <div><label for="customer_id" class="mb-1 block text-xs font-medium text-slate-700">{{ __('pos.customer_optional') }}</label>
                        <select id="customer_id" name="customer_id" class="form-input"><option value="">{{ __('pos.walk_in') }}</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->customer_code }} · {{ $customer->display_name }} · {{ $customer->mobile_number }}</option>@endforeach</select>
                        <div class="mt-2 flex gap-2"><input name="customer_q" value="{{ $customerTerm }}" class="form-input min-w-0 flex-1" placeholder="{{ __('pos.search_customers') }}"><button formaction="{{ route('pos.index') }}" formmethod="GET" class="rounded-lg border border-slate-200 px-3 text-[11px]">{{ __('ui.search') }}</button></div>
                    </div>
                    <div><label for="payment_method" class="mb-1 block text-xs font-medium text-slate-700">{{ __('pos.payment_method') }}</label><select id="payment_method" name="payment_method" class="form-input" required>@foreach ($paymentMethods as $method)<option value="{{ $method->value }}" @selected(old('payment_method', 'cash') === $method->value)>{{ __('payments.method_'.$method->value) }}</option>@endforeach</select></div>
                    <div><label for="amount_tendered" class="mb-1 block text-xs font-medium text-slate-700">{{ __('pos.amount_tendered') }}</label><input id="amount_tendered" name="amount_tendered" type="number" min="{{ $cart['total'] }}" step="0.01" value="{{ old('amount_tendered', (string) $cart['total']) }}" class="form-input"></div>
                    <div><label for="payment_reference" class="mb-1 block text-xs font-medium text-slate-700">{{ __('pos.payment_reference') }}</label><input id="payment_reference" name="payment_reference" value="{{ old('payment_reference') }}" class="form-input" maxlength="100"></div>
                    <div><label for="cheque_date" class="mb-1 block text-xs font-medium text-slate-700">{{ __('payments.cheque_date') }}</label><input id="cheque_date" name="cheque_date" type="date" value="{{ old('cheque_date') }}" class="form-input"></div>
                    <div><label for="bank_name" class="mb-1 block text-xs font-medium text-slate-700">{{ __('payments.bank_name') }}</label><input id="bank_name" name="bank_name" value="{{ old('bank_name') }}" class="form-input" maxlength="150"></div>
                    <div><label for="discount_amount" class="mb-1 block text-xs font-medium text-slate-700">{{ __('pos.discount') }}</label><input id="discount_amount" name="discount_amount" type="number" min="0" max="{{ $cart['subtotal'] }}" step="0.01" value="{{ old('discount_amount', '0.00') }}" class="form-input" @cannot('pos.discount') readonly @endcannot></div>
                    <div><label for="notes" class="mb-1 block text-xs font-medium text-slate-700">{{ __('ui.notes') }}</label><textarea id="notes" name="notes" rows="2" class="form-input">{{ old('notes') }}</textarea></div>
                    <p class="rounded-lg bg-amber-50 p-3 text-[11px] leading-5 text-amber-900">{{ __('pos.pending_payment_policy') }}</p>
                    <button @disabled(! count($cart['items'])) class="w-full rounded-xl bg-teal-700 px-4 py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:bg-slate-300">{{ __('pos.complete_sale') }}</button>
                </form>
            @else
                <p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-500">{{ __('pos.checkout_permission_required') }}</p>
            @endcan
        </section>
    </div>
@endsection
