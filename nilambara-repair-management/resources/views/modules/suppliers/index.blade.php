@extends('layouts.app')

@section('title','Suppliers')

@section('content')
<div class="mb-5 flex justify-between"><h1 class="text-2xl font-bold">Suppliers</h1>
@can('suppliers.create')<a class="rounded-xl bg-teal-700 px-4 py-2 text-white" href="{{ route('suppliers.create') }}">Register supplier</a>
@endcan</div>
@include('modules.inventory.feedback')
<form class="mb-5 flex gap-3"><input class="form-input" name="q" value="{{ request('q') }}" placeholder="Supplier name, code or mobile"><button class="rounded-xl bg-teal-700 px-4 text-white">Search</button></form>
<div class="overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Code</th><th>Name</th><th>Mobile</th><th>Credit terms</th><th>Active</th></tr></thead><tbody>
@forelse($suppliers as $supplier)<tr class="border-t"><td class="p-3"><a class="text-teal-700 underline" href="{{ route('suppliers.show',$supplier) }}">{{ $supplier->supplier_code }}</a></td><td>{{ $supplier->name }}</td><td>{{ $supplier->mobile_number }}</td><td>{{ $supplier->credit_terms }} · {{ $supplier->credit_days }} days</td><td>{{ $supplier->is_active ? 'Yes' : 'No' }}</td></tr>
@empty<tr><td class="p-4" colspan="5">No suppliers found.</td></tr>
@endforelse</tbody></table></div>{{ $suppliers->links() }}

@endsection
