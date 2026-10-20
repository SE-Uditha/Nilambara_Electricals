@extends('layouts.app')

@section('title','New purchase draft')

@section('content')
<h1 class="mb-5 text-2xl font-bold">New purchase draft</h1>
@include('modules.inventory.feedback')
<form method="POST" action="{{ route('purchases.store') }}" class="grid gap-4 rounded-2xl border bg-white p-6" x-data="{ rows: @js(array_values(old('items', [['spare_part_id'=>'','quantity'=>'1','unit_cost'=>'0']])) ) }">
@csrf<input type="hidden" name="idempotency_key" value="{{ old('idempotency_key',(string)Illuminate\Support\Str::uuid()) }}">
<div class="grid gap-4 md:grid-cols-2"><label>Supplier<select class="form-input w-full" name="supplier_id">
@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id')==$supplier->id)>{{ $supplier->name }}</option>
@endforeach</select></label><label>Supplier invoice number<input class="form-input w-full" name="supplier_invoice_number" value="{{ old('supplier_invoice_number') }}"></label><label>Purchase date<input class="form-input w-full" type="date" name="purchase_date" value="{{ old('purchase_date',now()->toDateString()) }}" required></label><label>Terms<select class="form-input w-full" name="payment_terms"><option value="cash" @selected(old('payment_terms')==='cash')>Cash</option><option value="credit" @selected(old('payment_terms')==='credit')>Credit</option></select></label><label>Due date<input class="form-input w-full" type="date" name="due_date" value="{{ old('due_date') }}"></label><label>Discount (LKR)<input class="form-input w-full" name="discount_amount" value="{{ old('discount_amount','0.00') }}" required></label><label>Other costs (LKR)<input class="form-input w-full" name="other_costs" value="{{ old('other_costs','0.00') }}" required></label><label>Notes<input class="form-input w-full" name="notes" value="{{ old('notes') }}"></label></div>
<h2 class="font-bold">Purchase items</h2><template x-for="(row,index) in rows" :key="index"><div class="grid gap-3 rounded-xl border p-3 md:grid-cols-4"><label>Part<select class="form-input w-full" :name="`items[${index}][spare_part_id]`" x-model="row.spare_part_id" required><option value="">Select part</option>
@foreach($parts as $part)<option value="{{ $part->id }}">{{ $part->sku }} · {{ $part->name }}</option>
@endforeach</select></label><label>Quantity<input class="form-input w-full" :name="`items[${index}][quantity]`" x-model="row.quantity" required></label><label>Unit cost (LKR)<input class="form-input w-full" :name="`items[${index}][unit_cost]`" x-model="row.unit_cost" required></label><button type="button" class="text-red-700" @click="if(rows.length>1) rows.splice(index,1)">Remove item</button></div></template>
<button type="button" class="text-left text-teal-700 underline" @click="rows.push({spare_part_id:'',quantity:'1',unit_cost:'0'})">Add another item</button><button class="rounded-xl bg-teal-700 p-3 text-white">Save draft</button><p class="text-sm text-slate-500">Saving a draft does not receive stock or post supplier credit.</p></form>

@endsection
