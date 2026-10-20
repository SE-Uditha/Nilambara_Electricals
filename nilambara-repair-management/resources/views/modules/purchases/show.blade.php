@extends('layouts.app')

@section('title',$purchase->purchase_number)

@section('content')
<h1 class="mb-5 text-2xl font-bold">{{ $purchase->purchase_number }}</h1>
<a class="mb-4 inline-block text-teal-700 underline" href="{{ route('purchases.document',['purchase'=>$purchase,'format'=>'html']) }}">Print / PDF purchase document</a>
@include('modules.inventory.feedback')
<div class="mb-5 rounded-2xl border bg-white p-5"><p>{{ $purchase->supplier->name }} · {{ $purchase->supplier_invoice_number }} · {{ $purchase->purchase_date->format('Y-m-d') }} · {{ $purchase->status->value }}</p><p>Subtotal LKR {{ $purchase->subtotal }} − discount {{ $purchase->discount_amount }} + other costs {{ $purchase->other_costs }}</p><p class="mt-3 text-lg font-bold">Total LKR {{ $purchase->total }} · Outstanding LKR {{ $outstanding }}</p><p>{{ $purchase->notes }}</p></div>

@if($purchase->status->value==='draft')
@can('purchases.approve')<form method="POST" action="{{ route('purchases.finalize',$purchase) }}" class="mb-5">
@csrf<button class="rounded-xl bg-teal-700 p-3 text-white" onclick="return confirm('Finalize purchase and post the supplier payable?')">Finalize purchase</button></form>
@endcan
@endif
<div class="overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Part</th><th>Ordered</th><th>Cost (LKR)</th><th>Total (LKR)</th><th>Receive</th></tr></thead><tbody>
@foreach($purchase->items as $item)<tr class="border-t"><td class="p-3">{{ $item->description }}</td><td>{{ $item->quantity }}</td><td>{{ $item->unit_cost }}</td><td>{{ $item->line_total }}</td><td>
@if($purchase->status->value==='finalized')
@can('purchases.receive')<form method="POST" action="{{ route('purchases.receive',$purchase) }}" class="grid gap-2 p-3">
@csrf<input type="hidden" name="idempotency_key" value="{{ (string)Illuminate\Support\Str::uuid() }}"><input type="hidden" name="purchase_item_id" value="{{ $item->id }}"><label>Quantity received now<input class="form-input w-full" name="quantity" required></label><label>Location<select class="form-input w-full" name="location_id">
@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>
@endforeach</select></label><button class="rounded-lg bg-teal-700 p-2 text-white" onclick="return confirm('Receive these goods and increase stock?')">Receive goods</button></form>
@endcan
@endif</td></tr>
@endforeach</tbody></table></div>
<h2 class="mt-6 mb-3 text-lg font-bold">Goods received notes</h2>
@forelse($purchase->goodsReceivedNotes as $grn)<div class="mb-2 rounded-xl border bg-white p-3"><a class="text-teal-700 underline" href="{{ route('grns.document',['grn'=>$grn,'format'=>'html']) }}">{{ $grn->grn_number }}</a> · {{ $grn->received_at }}
@foreach($grn->items as $line)<p>Purchase line {{ $line->purchase_item_id }} · Quantity {{ $line->quantity }}</p>
@endforeach</div>
@empty<p>No goods received.</p>
@endforelse

@can('purchases.approve')

@if($purchase->status->value==='finalized')<form method="POST" action="{{ route('purchases.pay',$purchase) }}" class="mt-6 grid gap-3 rounded-2xl border bg-white p-5 md:grid-cols-2">
@csrf<input type="hidden" name="idempotency_key" value="{{ (string)Illuminate\Support\Str::uuid() }}"><h2 class="font-bold md:col-span-2">Supplier payment</h2><label>Amount (LKR)<input class="form-input w-full" name="amount" required></label><label>Method<select class="form-input w-full" name="method"><option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="cheque">Cheque</option></select></label><label>Bank / cheque reference<input class="form-input w-full" name="reference"></label><label>Cheque date<input class="form-input w-full" type="date" name="cheque_date"></label><button class="rounded-xl bg-teal-700 p-3 text-white" onclick="return confirm('Record this supplier payment?')">Record payment</button></form>
@endif
<h2 class="mt-6 mb-3 text-lg font-bold">Payment history</h2>
@foreach($purchase->supplierPaymentAllocations as $allocation)
@php($payment=$allocation->supplierPayment)<div class="mb-3 rounded-2xl border bg-white p-4"><p>{{ $payment->payment_number }} · LKR {{ $payment->amount }} · {{ $payment->method->value }} · {{ $payment->paid_at }}</p>
@if($payment->supplierPaymentReversals->isEmpty())<form method="POST" action="{{ route('supplier-payments.reverse',$payment) }}" class="mt-3 flex gap-3">
@csrf<input type="hidden" name="idempotency_key" value="{{ (string)Illuminate\Support\Str::uuid() }}"><label>Reversal reason<input class="form-input w-full" name="reason" minlength="5" required></label><button class="text-red-700" onclick="return confirm('Reverse this payment? The original will be retained.')">Reverse payment</button></form>
@else<p class="text-red-700">Reversed</p>
@endif</div>
@endforeach

@endcan

@if($purchase->status->value==='finalized')
@can('purchases.receive')
@can('inventory.adjust')
<details class="mt-6 rounded-2xl border bg-white p-5"><summary>Return stock to supplier</summary><form method="POST" action="{{ route('purchases.return',$purchase) }}" class="mt-4 grid gap-3 md:grid-cols-2">
@csrf
<input type="hidden" name="idempotency_key" value="{{ (string)Illuminate\Support\Str::uuid() }}"><label>Purchase item<select class="form-input w-full" name="purchase_item_id">
@foreach($purchase->items as $item)<option value="{{ $item->id }}">{{ $item->description }}</option>
@endforeach
</select></label><label>Location<select class="form-input w-full" name="location_id">
@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>
@endforeach
</select></label><label>Quantity returned<input class="form-input w-full" name="quantity" required></label><label>Reason<input class="form-input w-full" name="reason" required minlength="5"></label>
@can('purchases.approve')<label>Agreed supplier credit note amount (LKR)<input class="form-input w-full" name="credit_amount" value="0.00"></label><p class="text-sm text-slate-500">Enter only credit already agreed with the supplier. This records credit, not cash received.</p>
@endcan
<button class="rounded-xl bg-teal-700 p-3 text-white" onclick="return confirm('Confirm stock was returned to the supplier?')">Post supplier return</button></form></details>
@endcan
@endcan
@endif
@endsection
