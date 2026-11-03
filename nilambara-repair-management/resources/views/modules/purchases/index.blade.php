@extends('layouts.app')

@section('title','Purchases')

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-bold">Purchases</h1>
@can('purchases.create')<a class="ui-create-action rounded-xl bg-teal-700 px-4 py-2 text-white" href="{{ route('purchases.create') }}"><x-icon name="plus" :size="18" />New purchase draft</a>
@endcan</div>
@include('modules.inventory.feedback')
<form class="mb-5 flex gap-3"><input class="form-input" name="q" value="{{ request('q') }}" placeholder="Purchase, supplier invoice or supplier"><button class="rounded-xl bg-teal-700 px-4 text-white">Search</button></form>
<div class="overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">Purchase</th><th>Supplier</th><th>Date</th><th>Total (LKR)</th><th>Status</th></tr></thead><tbody>
@forelse($purchases as $purchase)<tr class="border-t"><td class="p-3"><a class="text-teal-700 underline" href="{{ route('purchases.show',$purchase) }}">{{ $purchase->purchase_number }}</a></td><td>{{ $purchase->supplier->name }}</td><td>{{ $purchase->purchase_date->format('Y-m-d') }}</td><td>{{ $purchase->total }}</td><td>{{ $purchase->status->value }}</td></tr>
@empty<tr><td class="p-4" colspan="5">No purchases found.</td></tr>
@endforelse</tbody></table></div>{{ $purchases->links() }}

@endsection
