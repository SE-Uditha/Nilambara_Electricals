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

@foreach(['sku'=>'SKU','barcode'=>'Barcode','name'=>'Part name','description'=>'Description','model_compatibility'=>'Model compatibility','rack'=>'Rack / shelf','selling_price'=>'Selling price (LKR)','minimum_stock_level'=>__('inventory.legacy_reorder_threshold'),'reorder_level'=>__('inventory.reorder_level')] as $field=>$label)

@if($field!=='sku' || !$part->exists)<label>{{ $label }}<input class="form-input mt-1 w-full" name="{{ $field }}" value="{{ $field === 'name' && $errors->inventoryReference->any() ? ($part->name ?? '') : old($field,$part->$field ?? (in_array($field,['selling_price','minimum_stock_level','reorder_level']) ? '0' : '')) }}" @if(in_array($field, ['selling_price','minimum_stock_level','reorder_level'])) type="number" min="0" max="{{ $field === 'selling_price' ? '999999999999.9999' : '999999999999.999' }}" step="{{ $field === 'selling_price' ? '0.0001' : '0.001' }}" @else maxlength="{{ ['sku'=>80, 'barcode'=>100, 'name'=>255, 'description'=>2000, 'model_compatibility'=>2000, 'rack'=>100][$field] }}" @endif @required(in_array($field,['sku','name','selling_price','minimum_stock_level','reorder_level']))></label>
@endif

@endforeach

@foreach(['unit_id'=>['Unit',$units],'default_location_id'=>['Stock location',$locations],'brand_id'=>['Brand',$brands],'category_id'=>['Category',$categories]] as $field=>[$label,$options])<label>{{ $label }}<select class="form-input mt-1 w-full" name="{{ $field }}" @required(in_array($field, ['unit_id', 'default_location_id']))><option value="">Select</option>
@foreach($options as $option)<option value="{{ $option->id }}" @selected((string)old($field,$part->$field)===(string)$option->id)>{{ $option->name }}</option>
@endforeach</select></label>
@endforeach
<label>Active<select class="form-input w-full" name="is_active"><option value="1" @selected(old('is_active',$part->is_active ?? true))>Yes</option><option value="0" @selected(!old('is_active',$part->is_active ?? true))>No</option></select></label>

@unless($part->exists)<label>Opening quantity<input class="form-input w-full" name="opening_quantity" type="number" min="0" max="999999999999.999" step="0.001" value="{{ old('opening_quantity','0') }}"></label>
@endunless
<label>Preferred supplier<select class="form-input mt-1 w-full" name="supplier_id"><option value="">None</option>
@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string)old('supplier_id',$part->supplier_id)===(string)$supplier->id)>{{ $supplier->name }}</option>
@endforeach
</select></label>
@unless($part->exists)<label>Opening unit cost (LKR, optional)<input class="form-input w-full" name="opening_unit_cost" type="number" min="0" max="999999999999.9999" step="0.0001" value="{{ old('opening_unit_cost') }}"></label>
@endunless
<button class="rounded-xl bg-teal-700 px-4 py-3 text-white">Save part</button></form>

@can('inventory.create')
<details class="mt-6 rounded-2xl border bg-white p-5" @if($errors->inventoryReference->any()) open @endif>
    <summary>Create unit, location, brand or category</summary>
    @if($errors->inventoryReference->any())
        <div role="alert" class="mt-4 rounded-xl bg-red-50 p-4 text-red-800"><ul>
            @foreach($errors->inventoryReference->all() as $message)<li>{{ $message }}</li>@endforeach
        </ul></div>
    @endif
    <form method="POST" action="{{ route('inventory.references') }}" class="mt-4 grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-5">
        @csrf
        <label class="block text-xs font-semibold text-slate-700">{{ __('inventory.reference_type') }}
            <select class="form-input mt-1 w-full" name="kind">
                @foreach(['unit' => 'Unit', 'location' => 'Location', 'brand' => 'Brand', 'category' => 'Category'] as $kind => $label)
                    <option value="{{ $kind }}" @selected(($errors->inventoryReference->any() ? old('kind', 'unit') : 'unit') === $kind)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="block text-xs font-semibold text-slate-700">{{ __('inventory.reference_code') }}
            <input class="form-input mt-1 w-full @error('code', 'inventoryReference') has-error @enderror" name="code" value="{{ $errors->inventoryReference->any() ? old('code') : '' }}" required maxlength="30" pattern="[A-Za-z0-9_\-]+" title="{{ __('inventory.reference_code_help') }}" aria-describedby="reference-code-help @error('code', 'inventoryReference') reference-code-error @enderror" @error('code', 'inventoryReference') aria-invalid="true" @enderror>
            <span id="reference-code-help" class="mt-1 block font-normal text-slate-500">{{ __('inventory.reference_code_help') }}</span>
            @error('code', 'inventoryReference')<span id="reference-code-error" class="mt-1 block text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-xs font-semibold text-slate-700">{{ __('inventory.reference_name') }}
            <input class="form-input mt-1 w-full @error('name', 'inventoryReference') has-error @enderror" name="name" value="{{ $errors->inventoryReference->any() ? old('name') : '' }}" required maxlength="100" @error('name', 'inventoryReference') aria-invalid="true" aria-describedby="reference-name-error" @enderror>
            @error('name', 'inventoryReference')<span id="reference-name-error" class="mt-1 block text-red-600">{{ $message }}</span>@enderror
        </label>
        <label class="block text-xs font-semibold text-slate-700">{{ __('inventory.quantity_decimal_places') }}
            <select class="form-input mt-1 w-full" name="quantity_scale">
                @foreach(range(0,3) as $scale)<option value="{{ $scale }}" @selected((string) ($errors->inventoryReference->any() ? old('quantity_scale', '0') : '0') === (string) $scale)>{{ $scale }}</option>@endforeach
            </select>
        </label>
        <button class="h-10 w-full rounded-xl bg-emerald-700 px-4 font-semibold text-white hover:bg-emerald-800">{{ __('inventory.save_reference') }}</button>
    </form>
</details>
@endcan

@endsection
