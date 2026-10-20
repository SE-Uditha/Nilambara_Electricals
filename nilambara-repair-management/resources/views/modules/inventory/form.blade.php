@extends('layouts.app')

@section('title',$part->exists ? 'Edit part' : 'Register part')

@section('content')
<h1 class="mb-5 text-2xl font-bold">{{ $part->exists ? 'Edit part '.$part->sku : 'Register part' }}</h1>
@include('modules.inventory.feedback')
<form method="POST" action="{{ $part->exists ? route('inventory.update',$part) : route('inventory.store') }}" class="grid gap-4 rounded-2xl border bg-white p-6 md:grid-cols-2">
@csrf 
@if($part->exists) 
@method('PUT') 
@endif

@foreach(['sku'=>'SKU','barcode'=>'Barcode','name'=>'Part name','description'=>'Description','model_compatibility'=>'Model compatibility','rack'=>'Rack / shelf','selling_price'=>'Selling price (LKR)','minimum_stock_level'=>'Minimum stock','reorder_level'=>'Reorder level'] as $field=>$label)

@if($field!=='sku' || !$part->exists)<label>{{ $label }}<input class="form-input mt-1 w-full" name="{{ $field }}" value="{{ old($field,$part->$field ?? (in_array($field,['selling_price','minimum_stock_level','reorder_level']) ? '0' : '')) }}" @required(in_array($field,['sku','name','selling_price','minimum_stock_level','reorder_level']))></label>
@endif

@endforeach

@foreach(['unit_id'=>['Unit',$units],'default_location_id'=>['Stock location',$locations],'brand_id'=>['Brand',$brands],'category_id'=>['Category',$categories]] as $field=>[$label,$options])<label>{{ $label }}<select class="form-input mt-1 w-full" name="{{ $field }}"><option value="">Select</option>
@foreach($options as $option)<option value="{{ $option->id }}" @selected((string)old($field,$part->$field)===(string)$option->id)>{{ $option->name }}</option>
@endforeach</select></label>
@endforeach
<label>Active<select class="form-input w-full" name="is_active"><option value="1" @selected(old('is_active',$part->is_active ?? true))>Yes</option><option value="0" @selected(!old('is_active',$part->is_active ?? true))>No</option></select></label>

@unless($part->exists)<label>Opening quantity<input class="form-input w-full" name="opening_quantity" value="{{ old('opening_quantity','0') }}"></label>
@endunless
<label>Preferred supplier<select class="form-input mt-1 w-full" name="supplier_id"><option value="">None</option>
@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string)old('supplier_id',$part->supplier_id)===(string)$supplier->id)>{{ $supplier->name }}</option>
@endforeach
</select></label>
@unless($part->exists)<label>Opening unit cost (LKR, optional)<input class="form-input w-full" name="opening_unit_cost" value="{{ old('opening_unit_cost') }}"></label>
@endunless
<button class="rounded-xl bg-teal-700 px-4 py-3 text-white">Save part</button></form>

@can('inventory.create')<details class="mt-6 rounded-2xl border bg-white p-5"><summary>Create unit, location, brand or category</summary><form method="POST" action="{{ route('inventory.references') }}" class="mt-4 grid gap-3 md:grid-cols-2">
@csrf<select class="form-input" name="kind"><option value="unit">Unit</option><option value="location">Location</option><option value="brand">Brand</option><option value="category">Category</option></select><label>Code (units / locations)<input class="form-input w-full" name="code" required></label><label>Name<input class="form-input w-full" name="name" required></label><label>Quantity decimal places (units)<select class="form-input w-full" name="quantity_scale">
@foreach(range(0,3) as $scale)<option>{{ $scale }}</option>
@endforeach</select></label><button class="rounded-xl bg-teal-700 p-3 text-white">Save reference</button></form></details>
@endcan

@endsection
