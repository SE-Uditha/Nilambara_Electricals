@extends('layouts.app')
@section('title', 'Review approval')
@section('content')
<a href="{{ route('approvals.index') }}" class="text-sm text-teal-700 underline">Back to approvals</a>
<h1 class="my-5 text-2xl font-bold">{{ \App\Modules\Approvals\Services\ApprovalAccess::LABELS[$kind] }}</h1>
@include('modules.inventory.feedback')
<div class="space-y-3 rounded-xl border bg-white p-5"><p class="font-bold">{{ $kind==='purchase' ? $document->purchase_number : $document->sale_number }}</p><p>Requested by: {{ $kind==='purchase' ? ($document->createdByUser?->name ?? 'Legacy record') : $document->cashierEmployee?->name }}</p><p>Date: {{ $kind==='purchase' ? $document->purchase_date->format('Y-m-d') : $document->sale_at->format('Y-m-d H:i') }}</p><p class="text-lg font-bold">Total: LKR {{ $document->total }}</p>
@if($kind==='purchase')<p>Supplier: {{ $document->supplier->name }} &middot; {{ $document->supplier_invoice_number }}</p><p>Terms: {{ $document->payment_terms->value }} ? Due: {{ $document->due_date?->format('Y-m-d') ?? '-' }}</p>@else<p>Payment: {{ str_replace('_',' ',$document->payment_method) }} &middot; Reference: {{ $document->payment_reference }}</p><p>Bank: {{ $document->bank_name ?? '-' }} &middot; Cheque date: {{ $document->cheque_date?->format('Y-m-d') ?? '-' }}</p>@endif
<p>Notes: {{ $document->notes ?? '-' }}</p>
<a class="inline-block text-teal-700 underline" href="{{ $kind==='purchase' ? route('purchases.show',$document) : route('pos.sales.show',$document) }}">Open full document</a>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Item</th><th>Quantity</th><th>Unit price / cost (LKR)</th><th>Total (LKR)</th></tr></thead><tbody>@foreach($document->items as $line)<tr class="border-t"><td class="p-2">{{ $line->description }}</td><td>{{ $line->quantity }}</td><td>{{ $kind==='purchase' ? $line->unit_cost : $line->unit_price }}</td><td>{{ $line->line_total }}</td></tr>@endforeach</tbody></table></div>
@if($kind==='pos_payment')<p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Confirm the bank transfer or cheque before approving. Approval rechecks stock availability, posts the payment and deducts stock. Rejection cancels the pending sale.</p>@else<p class="text-sm text-slate-500">Approval finalizes this purchase and records the supplier payable. Goods receiving remains a separate action.</p>@endif</div>
@include('modules.approvals.decision',['decisionAction'=>route('approvals.document.decide',['kind'=>$kind,'id'=>$document->id])])
@endsection
