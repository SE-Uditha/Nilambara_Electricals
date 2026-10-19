<section class="mb-5 grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-3">
    <div><p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('payments.opening_balance') }}</p><p class="mt-1 text-lg font-bold text-slate-900">{{ config('business.currency') }} {{ $customer->opening_balance }}</p></div>
    <div><p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('payments.balance_brought_forward') }}</p><p class="mt-1 text-lg font-bold text-slate-900">{{ config('business.currency') }} {{ $openingBalance }}</p></div>
    <div><p class="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">{{ __('payments.closing_balance') }}</p><p class="mt-1 text-lg font-bold text-teal-900">{{ config('business.currency') }} {{ $closingBalance }}</p></div>
</section>
