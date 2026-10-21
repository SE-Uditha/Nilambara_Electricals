@extends('layouts.app')

@section('title', __('payments.payment_details').' · '.$payment->payment_number)

@section('content')
<a class="mb-4 inline-block text-sm text-teal-700 underline" href="{{ route('approval-requests.create',['kind'=>'payment_refund','subject_id'=>$payment->id]) }}">Request refund / reversal approval</a>
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
            <a href="{{ route('payments.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← {{ __('payments.back_to_payments') }}</a>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">{{ $payment->payment_number }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('payments.payment_details') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('print', $payment)<a href="{{ route('payments.print', $payment) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700">{{ __('payments.print_receipt') }}</a>@endcan
            @can('export', $payment)<a href="{{ route('payments.pdf', $payment) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700">{{ __('payments.download_pdf') }}</a>@endcan
        </div>
    </div>

    @if (session('status'))<div role="status" class="mb-5 rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800">{{ session('status') }}</div>@endif

    <div class="grid gap-5 xl:grid-cols-[1fr_360px]">
        <div class="space-y-5">
            <section class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2 lg:grid-cols-3">
                <div><p class="form-label">{{ __('payments.customer') }}</p><p class="font-semibold text-slate-900">{{ $payment->customer?->display_name ?: '—' }}</p>@if ($payment->customer)<a href="{{ route('customers.show', $payment->customer) }}" class="mt-1 inline-block text-xs text-teal-800 hover:underline">{{ $payment->customer->customer_code }} · {{ __('payments.view_customer') }}</a>@endif</div>
                <div><p class="form-label">{{ __('payments.date') }}</p><p class="text-slate-800">{{ $payment->paid_at->timezone(config('business.timezone'))->format(config('business.date_format').' '.config('business.time_format')) }}</p></div>
                <div><p class="form-label">{{ __('payments.type') }}</p><p class="text-slate-800">{{ __('payments.type_'.$payment->payment_type) }}</p></div>
                <div><p class="form-label">{{ __('payments.method') }}</p><p class="text-slate-800">{{ __('payments.method_'.$payment->method->value) }}</p></div>
                <div><p class="form-label">{{ __('payments.received_by') }}</p><p class="text-slate-800">{{ $payment->receivedByEmployee?->name ?: '—' }}</p></div>
                <div><p class="form-label">{{ __('payments.reference_number') }}</p><p class="text-slate-800">{{ $payment->cheque_number ?: $payment->bank_reference ?: '—' }}</p></div>
                @if ($payment->bank_name)<div><p class="form-label">{{ __('payments.bank_name') }}</p><p class="text-slate-800">{{ $payment->bank_name }}</p></div>@endif
                @if ($payment->cheque_date)<div><p class="form-label">{{ __('payments.cheque_date') }}</p><p class="text-slate-800">{{ $payment->cheque_date->format(config('business.date_format')) }}</p></div>@endif
                @if ($payment->notes)<div class="sm:col-span-2 lg:col-span-3"><p class="form-label">{{ __('payments.notes') }}</p><p class="whitespace-pre-line text-slate-800">{{ $payment->notes }}</p></div>@endif
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-bold text-slate-900">{{ __('payments.allocation_history') }}</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase"><tr><th class="px-5 py-3">{{ __('payments.date') }}</th><th class="px-5 py-3">{{ __('payments.related_document') }}</th><th class="px-5 py-3">{{ __('payments.allocated_by') }}</th><th class="px-5 py-3 text-right">{{ __('payments.amount') }}</th><th class="px-5 py-3 text-right">{{ __('payments.released') }}</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($payment->paymentAllocations as $allocation)
                                @php($released = $allocation->releases->reduce(fn ($sum, $release) => $sum->plus((string) $release->amount), \Brick\Math\BigDecimal::zero()->toScale(2)))
                                <tr>
                                    <td class="px-5 py-3">{{ $allocation->allocated_at->format(config('business.date_format')) }}</td>
                                    <td class="px-5 py-3">
                                        @if ($allocation->invoice)
                                            @can('invoices.view')<a href="{{ route('invoices.show', $allocation->invoice) }}" class="text-teal-800 hover:underline">{{ $allocation->invoice->invoice_number }}</a>@else{{ $allocation->invoice->invoice_number }}@endcan
                                        @elseif ($allocation->repairJob)
                                            <a href="{{ route('repair-jobs.show', $allocation->repairJob) }}" class="text-teal-800 hover:underline">{{ $allocation->repairJob->automatic_number ?: $allocation->repairJob->job_number }}</a> · {{ __('payments.job_advance') }}
                                        @elseif ($allocation->posSale)
                                            @can('pos.sales.view')<a href="{{ route('pos.sales.show', $allocation->posSale) }}" class="text-teal-800 hover:underline">{{ $allocation->posSale->sale_number }}</a>@else{{ $allocation->posSale->sale_number }}@endcan
                                        @else{{ __('payments.unallocated') }}@endif
                                    </td>
                                    <td class="px-5 py-3">{{ $allocation->allocatedByUser?->name ?: '—' }}</td>
                                    <td class="px-5 py-3 text-right">{{ config('business.currency') }} {{ $allocation->amount }}</td>
                                    <td class="px-5 py-3 text-right">{{ config('business.currency') }} {{ $released }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">{{ __('payments.not_allocated_yet') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-bold text-slate-900">{{ __('payments.reversal_history') }}</h2></div>
                <div class="divide-y divide-slate-100">
                    @forelse ($payment->paymentReversals as $reversal)
                        <div class="grid gap-2 px-5 py-4 text-xs sm:grid-cols-4">
                            <div><p class="form-label">{{ __('payments.date') }}</p><p>{{ $reversal->reversed_at->format(config('business.date_format').' '.config('business.time_format')) }}</p></div>
                            <div><p class="form-label">{{ __('payments.reversal_type') }}</p><p>{{ __('payments.reversal_'.$reversal->reversal_type) }}</p></div>
                            <div><p class="form-label">{{ __('payments.amount') }} · {{ __('payments.reversed_by') }}</p><p>{{ config('business.currency') }} {{ $reversal->amount }} · {{ $reversal->reversedByUser?->name ?: '—' }}</p></div>
                            <div><p class="form-label">{{ __('payments.reason') }}</p><p>{{ $reversal->reason }}</p>@if ($reversal->external_reference)<p class="mt-1 text-slate-500">{{ $reversal->external_reference }}</p>@endif</div>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-center text-xs text-slate-500">{{ __('payments.no_reversals') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="mb-4 text-sm font-bold text-slate-900">{{ __('payments.payment_summary') }}</h2>
                <dl class="space-y-3 text-xs">
                    <div class="flex justify-between gap-4"><dt>{{ __('payments.amount') }}</dt><dd class="font-semibold">{{ config('business.currency') }} {{ $balance['amount'] }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>{{ __('payments.allocated') }}</dt><dd>{{ config('business.currency') }} {{ $balance['allocated'] }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>{{ __('payments.reversed') }}</dt><dd>{{ config('business.currency') }} {{ $balance['reversed'] }}</dd></div>
                    <div class="flex justify-between gap-4 border-t border-slate-100 pt-3"><dt class="font-semibold">{{ __('payments.available') }}</dt><dd class="font-bold">{{ config('business.currency') }} {{ $balance['available'] }}</dd></div>
                    <div><dt class="form-label">{{ __('payments.status') }}</dt><dd class="font-semibold">{{ __('payments.status_'.$balance['status']) }}</dd></div>
                </dl>
            </section>

            @can('reverse', $payment)
                @if ($balance['amount'] !== $balance['reversed'])
                    @php($remainingReversible = \Brick\Math\BigDecimal::of($balance['amount'])->minus($balance['reversed'])->toScale(2))
                    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                        <h2 class="mb-2 text-sm font-bold text-slate-900">{{ __('payments.reverse_payment') }}</h2>
                        <p class="mb-4 text-xs leading-5 text-slate-600">{{ __('payments.reversal_help') }}</p>
                        <form method="POST" action="{{ route('payments.reverse', $payment) }}" class="space-y-3" onsubmit="return confirm(@js(__('payments.confirm_reversal')))">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                            <div><label for="reversal_type" class="form-label">{{ __('payments.reversal_type') }}</label><select id="reversal_type" name="reversal_type" class="form-input" required><option value="refund">{{ __('payments.reversal_refund') }}</option><option value="reversal">{{ __('payments.reversal_reversal') }}</option></select></div>
                            <div><label for="reversal_amount" class="form-label">{{ __('payments.amount') }}</label><input id="reversal_amount" name="amount" type="number" min="0.01" max="{{ $remainingReversible }}" step="0.01" value="{{ $remainingReversible }}" class="form-input" required></div>
                            <div id="refund-method-field"><label for="refund_method" class="form-label">{{ __('payments.refund_method') }}</label><select id="refund_method" name="method" class="form-input" required>@foreach (\App\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}">{{ __('payments.method_'.$method->value) }}</option>@endforeach</select></div>
                            <div><label for="external_reference" class="form-label">{{ __('payments.external_reference') }}</label><input id="external_reference" name="external_reference" maxlength="100" class="form-input"></div>
                            <div><label for="reason" class="form-label">{{ __('payments.reason') }}</label><textarea id="reason" name="reason" rows="3" minlength="5" maxlength="2000" class="form-input" required></textarea></div>
                            <button class="w-full rounded-xl bg-amber-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-amber-800">{{ __('payments.submit_reversal') }}</button>
                        </form>
                    </section>
                    <script>
                        document.getElementById('reversal_type').addEventListener('change', (event) => {
                            const refund = event.target.value === 'refund';
                            document.getElementById('refund-method-field').classList.toggle('hidden', !refund);
                            document.getElementById('refund_method').required = refund;
                        });
                    </script>
                @endif
            @endcan
        </aside>
    </div>
@endsection
