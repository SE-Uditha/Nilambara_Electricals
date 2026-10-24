@extends('layouts.app')

@section('title','Inventory')

@section('content')
<div class="mb-6 flex justify-between"><h1 class="text-2xl font-bold">Inventory</h1>
@can('inventory.create')<div class="flex gap-2"><a class="rounded-xl border border-teal-700 px-4 py-2 text-teal-800" href="{{ route('inventory.import') }}">{{ __('inventory.bulk_upload') }}</a><a class="rounded-xl bg-teal-700 px-4 py-2 text-white" href="{{ route('inventory.create') }}">Register part</a></div>
@endcan</div>

@include('modules.inventory.feedback')
<form class="mb-5 flex gap-3"><input class="form-input" name="q" value="{{ request('q') }}" placeholder="Name, SKU or barcode"><select class="form-input" name="state"><option value="">All stock</option><option value="low" @selected(request('state')==='low')>Low stock</option><option value="out" @selected(request('state')==='out')>Out of stock</option></select><button class="rounded-xl bg-teal-700 px-4 text-white">Search</button></form>
<div class="overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">SKU</th><th>Part</th><th>Barcode</th><th>Current stock</th><th>Selling price (LKR)</th><th>Active</th></tr></thead><tbody>
@forelse($parts as $part)<tr class="border-t"><td class="p-3"><a class="text-teal-700 underline" href="{{ route('inventory.show',$part->id) }}">{{ $part->sku }}</a></td><td>{{ $part->name }}</td><td>{{ $part->barcode }}</td><td>{{ $part->available_quantity }}</td><td>{{ $part->selling_price }}</td><td>{{ $part->is_active ? 'Yes' : 'No' }}</td></tr>
@empty<tr><td class="p-4" colspan="6">No parts found.</td></tr>
@endforelse</tbody></table></div><div class="mt-4">{{ $parts->links() }}</div>

@endsection
