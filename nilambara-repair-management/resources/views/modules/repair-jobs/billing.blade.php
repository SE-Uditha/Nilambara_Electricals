@extends('layouts.app')
@section('title', 'Prepare Final Bill')
@section('content')
<div class="mb-6 flex flex-wrap justify-between gap-3"><div><h1 class="text-2xl font-bold">Prepare Final Bill</h1><p class="mt-1 text-sm text-slate-500">{{ $job->automatic_number ?: $job->job_number }} / {{ $job->customer->display_name }} / {{ $job->item_name }}</p></div><a class="text-teal-700" href="{{ route('repair-jobs.show', $job) }}">Back to job</a></div>
@if(session('status'))<p role="status" class="mb-4 rounded-xl bg-teal-50 p-4 text-teal-900">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert" class="mb-4 rounded-xl bg-red-50 p-4 text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<p class="mb-5 text-sm text-slate-600">The receiving receipt acknowledges the item only. This final bill is for completed work. Save your changes, review the total, then create the separate invoice.</p>
@if($quoted)
<div class="rounded-xl border border-slate-200 bg-white p-5"><p>This job uses an issued quotation. Revise the quotation and obtain customer approval to change its prices.</p><p class="mt-3 font-bold">Agreed job total: {{ config('business.currency') }} {{ $job->total_job_amount }}</p></div>
@else
<form id="bill-form" method="POST" action="{{ route('repair-jobs.billing.update', $job) }}" class="space-y-5" x-data="jobBillEditor()">
@csrf<input type="hidden" name="idempotency_key" value="{{ $submissionKey }}">
<section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Labour / repair charge</h2><label class="form-label mt-3" for="bill-labour">Base labour amount</label><input id="bill-labour" name="labour_charge" type="number" min="0" max="999999999999.99" step="0.01" class="form-input" x-model="labour" required></section>
@if($job->status !== \App\Enums\JobStatus::Cancelled)
<section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Spare parts already used</h2><div class="mt-3 space-y-2">@forelse($job->jobPartUsages->where('status','issued') as $usage)<p>{{ $usage->sparePart?->name }} / {{ $usage->quantity }} x {{ $usage->unit_price }}</p>@empty<p class="text-sm text-slate-500">No parts issued yet.</p>@endforelse</div><p class="mt-3 font-semibold">Existing parts total: {{ config('business.currency') }} {{ $job->parts_charge }}</p></section>
<section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Add inventory spare parts</h2><p class="mt-2 text-sm text-slate-500">Saving issues these parts from stock and adds their selling prices to the bill. Already issued parts are included above; do not add them again.</p>
@can('inventory.issue')
<template x-for="(row, index) in parts" :key="row.key"><div class="mt-4 grid gap-3 rounded-xl bg-slate-50 p-3 md:grid-cols-4">
<label>Spare part<select class="form-input" :name="`parts[${index}][spare_part_id]`" x-model="row.spare_part_id" x-on:change="choosePart(row)" required><option value="">Select part</option><template x-for="part in catalogue" :key="part.id"><option :value="part.id" x-text="`${part.name} / available ${part.available} / ${part.price}`"></option></template></select></label>
<label>Stock location<select class="form-input" :name="`parts[${index}][location_id]`" x-model="row.location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
<label>Quantity<input class="form-input" type="number" min="0.001" max="999999999.999" step="0.001" :name="`parts[${index}][quantity]`" x-model="row.quantity" required></label>
<div><span class="form-label">Line amount</span><p x-text="money(line(row.quantity, row.price))"></p><button type="button" class="mt-2 text-sm text-red-700" x-on:click="parts.splice(index,1)">Remove</button></div>
</div></template><button type="button" class="mt-4 rounded-xl border px-4 py-2 text-teal-800" x-on:click="parts.push({key:++nextKey,spare_part_id:'',location_id:'',quantity:'1',price:'0'})">Add Spare Part</button>
@endcan
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-bold">Custom charges</h2><p class="mt-2 text-sm text-slate-500">Type a description and amount for additional work or services. For a single amount, leave quantity as 1. Use the inventory section for stocked spare parts.</p>
<template x-for="(row,index) in charges" :key="row.key"><div class="mt-4 grid gap-3 rounded-xl bg-slate-50 p-3 md:grid-cols-4">
<label>Description<input class="form-input" :name="`charges[${index}][description]`" x-model="row.description" maxlength="500" required></label>
<label>Quantity<input class="form-input" type="number" min="0.001" max="999999999.999" step="0.001" :name="`charges[${index}][quantity]`" x-model="row.quantity" required></label>
<label>Unit amount<input class="form-input" type="number" min="0" max="999999999999.9999" step="0.0001" :name="`charges[${index}][unit_price]`" x-model="row.unit_price" required></label>
<div><span class="form-label">Line amount</span><p x-text="money(line(row.quantity,row.unit_price))"></p><button type="button" class="mt-2 text-sm text-red-700" x-on:click="charges.splice(index,1)">Remove</button></div>
</div></template><button type="button" class="mt-4 rounded-xl border px-4 py-2 text-teal-800" x-on:click="charges.push({key:++nextKey,description:'',quantity:'1',unit_price:'0.00'})">Add Custom Charge</button>
</section>
@endif
<section class="rounded-2xl bg-teal-50 p-5"><span>Final bill total</span><p class="mt-2 text-2xl font-bold text-teal-900" x-text="money(total())"></p><p class="mt-2 text-xs text-slate-600">Includes base labour, custom charges and spare parts. The saved total is recalculated on the server. Previously received advances are applied on the invoice.</p></section>
<button class="rounded-xl bg-teal-700 px-5 py-3 font-semibold text-white">Save Bill Changes</button>
</form>
@endif
<div class="mt-6 rounded-xl border border-slate-200 bg-white p-5"><p class="font-semibold">Saved final bill: {{ config('business.currency') }} {{ $job->total_job_amount }}</p><a href="{{ route('invoices.create',['customer_id'=>$job->customer_id,'repair_job_ids'=>[$job->id]]) }}" onclick="if(window.billHasChanges){event.preventDefault();alert('Save bill changes before creating the invoice.');}" class="mt-3 inline-block rounded-xl bg-teal-700 px-5 py-3 text-white">Create Invoice from Saved Bill</a><p class="mt-2 text-xs text-slate-500">Save any changes above before opening the invoice.</p></div>
<script>
const billForm=document.getElementById('bill-form');
if(billForm){billForm.addEventListener('input',()=>window.billHasChanges=true);billForm.addEventListener('change',()=>window.billHasChanges=true);billForm.addEventListener('click',e=>{if(e.target.closest('button[type="button"]'))window.billHasChanges=true;});}

function jobBillEditor() {
    const catalogue = @js($parts);
    let key=0;
    return {
        catalogue, nextKey:1000,
        labour: @js(old('labour_charge',$baseLabour)),
        existingParts: @js($job->parts_charge),
        charges: @js(old('charges',$charges->map(fn($c)=>$c->only(['description','quantity','unit_price']))->all())).map(r=>({...r,key:++key})),
        parts: @js(old('parts',[])).map(r=>({...r,key:++key,price:catalogue.find(p=>String(p.id)===String(r.spare_part_id))?.price || '0'})),
        scaled(value,scale) { const m=String(value||'0').match(/^(\d+)(?:\.(\d*))?$/); if(!m)return 0n; return BigInt(m[1]+(m[2]||'').padEnd(scale,'0').slice(0,scale)); },
        line(q,p) {return (this.scaled(q,3)*this.scaled(p,4)+50000n)/100000n;},
        money(cents) {return @js(config('business.currency'))+' '+(cents/100n).toString()+'.'+(cents%100n).toString().padStart(2,'0');},
        choosePart(row){const part=this.catalogue.find(p=>String(p.id)===String(row.spare_part_id));row.price=part?.price||'0';row.location_id=part?.location_id||'';},
        total(){return this.scaled(this.labour,2)+this.scaled(this.existingParts,2)+this.charges.reduce((s,r)=>s+this.line(r.quantity,r.unit_price),0n)+this.parts.reduce((s,r)=>s+this.line(r.quantity,r.price),0n);}
    };
}
</script>
@endsection
