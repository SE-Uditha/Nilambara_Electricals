@extends('layouts.app')

@section('title',$supplier->exists ? 'Edit supplier' : 'Register supplier')

@section('content')
<h1 class="mb-5 text-2xl font-bold">{{ $supplier->exists ? 'Edit supplier '.$supplier->supplier_code : 'Register supplier' }}</h1>
@include('modules.inventory.feedback')
<form method="POST" action="{{ $supplier->exists ? route('suppliers.update',$supplier) : route('suppliers.store') }}" class="grid gap-4 rounded-2xl border bg-white p-6 md:grid-cols-2">
@csrf 
@if($supplier->exists) 
@method('PUT') 
@endif

@foreach(['name'=>'Supplier name','contact_person'=>'Contact person','address'=>'Address','mobile_number'=>'Mobile','telephone_number'=>'Telephone','email'=>'Email','credit_terms'=>'Credit terms','credit_days'=>'Credit days','notes'=>'Notes'] as $field=>$label)<label>{{ $label }}<input class="form-input mt-1 w-full" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : ($field === 'credit_days' ? 'number' : (in_array($field, ['mobile_number', 'telephone_number']) ? 'tel' : 'text')) }}" maxlength="{{ in_array($field, ['address', 'notes']) ? 2000 : (in_array($field, ['mobile_number', 'telephone_number']) ? 30 : 255) }}" @if($field === 'credit_days') min="0" max="3650" step="1" @endif value="{{ old($field,$supplier->$field ?? ($field==='credit_days' ? '0' : '')) }}" @required(in_array($field,['name','address','mobile_number','credit_days']))></label>
@endforeach

@unless($supplier->exists)<label>Opening balance (LKR)<input class="form-input w-full" name="opening_balance" type="number" step="0.01" min="-999999999999.99" max="999999999999.99" value="{{ old('opening_balance','0.00') }}" required></label>
@endunless
<label>Active<select class="form-input w-full" name="is_active"><option value="1" @selected(old('is_active',$supplier->is_active ?? true))>Yes</option><option value="0" @selected(!old('is_active',$supplier->is_active ?? true))>No</option></select></label><button class="rounded-xl bg-teal-700 p-3 text-white">Save supplier</button></form>

@endsection
