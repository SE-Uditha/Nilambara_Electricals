@extends('layouts.app')

@section('title',$supplier->name)

@section('content')
<div class="mb-5 flex justify-between"><h1 class="text-2xl font-bold">{{ $supplier->name }}</h1>
@can('suppliers.update')<a class="text-teal-700 underline" href="{{ route('suppliers.edit',$supplier) }}">Edit supplier</a>
@endcan</div>
@include('modules.inventory.feedback')
<div class="mb-5 rounded-2xl border bg-white p-5"><p>{{ $supplier->supplier_code }} · {{ $supplier->address }}</p><p>{{ $supplier->contact_person }} · {{ $supplier->mobile_number }} · {{ $supplier->email }}</p><p>Credit: {{ $supplier->credit_terms }} · {{ $supplier->credit_days }} days</p><p class="mt-3 text-lg font-bold">Account balance: LKR {{ $balance }}</p></div>
<h2 class="mb-3 text-lg font-bold">Account statement</h2><div class="overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Date</th><th>Type</th><th>Reference</th><th>Amount (LKR)</th><th>Description</th></tr></thead><tbody>
@forelse($entries as $entry)<tr class="border-t"><td class="p-3">{{ $entry->occurred_at }}</td><td>{{ $entry->entry_type }}</td><td>{{ $entry->reference_number }}</td><td>{{ $entry->amount }}</td><td>{{ $entry->description }}</td></tr>
@empty<tr><td class="p-4" colspan="5">No account entries.</td></tr>
@endforelse</tbody></table></div>{{ $entries->links() }}

@can('reports.financial')<a class="my-4 inline-block text-teal-700 underline" href="{{ route('reports.show',['report'=>'supplier-statement','supplier_id'=>$supplier->id]) }}">Filter, print or export statement</a>
@endcan
<h2 class="mt-5 mb-3 text-lg font-bold">Recent purchases</h2>
@foreach($purchases as $purchase)<p class="mb-2">
@can('purchases.view')<a class="text-teal-700 underline" href="{{ route('purchases.show',$purchase) }}">{{ $purchase->purchase_number }}</a>
@else{{ $purchase->purchase_number }}
@endcan · {{ $purchase->purchase_date->format('Y-m-d') }} · LKR {{ $purchase->total }} · {{ $purchase->status->value }}</p>
@endforeach

@can('purchases.approve')
<details class="mt-6 rounded-2xl border bg-white p-5"><summary>Settle opening balance · LKR {{ $openingOutstanding }} outstanding</summary><form method="POST" action="{{ route('suppliers.pay-opening',$supplier) }}" class="mt-4 grid gap-3 md:grid-cols-2">
@csrf
<input type="hidden" name="idempotency_key" value="{{ (string)Illuminate\Support\Str::uuid() }}"><label>Amount (LKR)<input class="form-input w-full" name="amount" required></label><label>Method<select class="form-input w-full" name="method"><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="cheque">Cheque</option></select></label><label>Bank / cheque reference<input class="form-input w-full" name="reference"></label><label>Cheque date<input class="form-input w-full" type="date" name="cheque_date"></label><button class="rounded-xl bg-teal-700 p-3 text-white" onclick="return confirm('Record this opening balance payment?')">Record payment</button></form></details>
<h2 class="mt-6 mb-3 text-lg font-bold">Recent supplier payments</h2>
@foreach($payments as $payment)
<div class="mb-3 rounded-xl border bg-white p-4"><p>{{ $payment->payment_number }} · LKR {{ $payment->amount }} · {{ $payment->method->value }}</p>
@if($payment->supplierPaymentReversals->isEmpty())
<form method="POST" action="{{ route('supplier-payments.reverse',$payment) }}" class="mt-3 flex gap-3">
@csrf
<input type="hidden" name="idempotency_key" value="{{ (string)Illuminate\Support\Str::uuid() }}"><label>Reversal reason<input class="form-input w-full" name="reason" minlength="5" required></label><button class="text-red-700" onclick="return confirm('Reverse this supplier payment and retain its history?')">Reverse payment</button></form>
@else<p class="text-red-700">Reversed</p>
@endif
</div>
@endforeach
@endcan
@endsection
