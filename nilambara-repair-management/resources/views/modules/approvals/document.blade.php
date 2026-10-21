@extends('layouts.app')
@section('title', 'Review purchase approval')
@section('content')
<a href="{{ route('approvals.index') }}" class="text-sm text-teal-700 underline">Back to approvals</a>
<h1 class="my-5 text-2xl font-bold">Purchase approval</h1>
@include('modules.inventory.feedback')
<div class="space-y-3 rounded-xl border bg-white p-5"><p class="font-bold">{{ $document->purchase_number }}</p><p>Requested by: {{ $document->createdByUser?->name ?? 'Legacy record' }}</p><p>Date: {{ $document->purchase_date->format('Y-m-d') }}</p><p class="text-lg font-bold">Total: LKR {{ $document->total }}</p>
<p>Supplier: {{ $document->supplier->name }} &middot; {{ $document->supplier_invoice_number }}</p><p>Terms: {{ $document->payment_terms->value }} &middot; Due: {{ $document->due_date?->format('Y-m-d') ?? '-' }}</p>
<p>Notes: {{ $document->notes ?? '-' }}</p>
<a class="inline-block text-teal-700 underline" href="{{ route('purchases.show',$document) }}">Open full document</a>
<div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Item</th><th>Quantity</th><th>Unit cost (LKR)</th><th>Total (LKR)</th></tr></thead><tbody>@foreach($document->items as $line)<tr class="border-t"><td class="p-2">{{ $line->description }}</td><td>{{ $line->quantity }}</td><td>{{ $line->unit_cost }}</td><td>{{ $line->line_total }}</td></tr>@endforeach</tbody></table></div>
<p class="text-sm text-slate-500">Approval finalizes this purchase and records the supplier payable. Goods receiving remains a separate action.</p></div>
@include('modules.approvals.decision',['decisionAction'=>route('approvals.document.decide',['kind'=>'purchase','id'=>$document->id])])
@endsection
