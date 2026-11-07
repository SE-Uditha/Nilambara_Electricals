@extends('layouts.app')

@section('title','Inventory')

@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3"><h1 class="text-2xl font-bold">Inventory</h1>
<div class="flex flex-wrap gap-2">
    @can('inventory.adjust')<a class="rounded-xl bg-orange-600 px-4 py-2 text-xs font-semibold text-white hover:bg-orange-700" href="{{ route('stock-returns.create') }}">{{ __('inventory.stock_return') }}</a>@endcan
    @can('inventory.update')
        @can('inventory.adjust')
            <a class="rounded-xl bg-blue-700 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-800" href="{{ route('inventory.stock-update') }}">{{ __('inventory.stock_update') }}</a>
        @endcan
    @endcan
    @can('inventory.create')<a class="rounded-xl border border-teal-700 px-4 py-2 text-xs font-semibold text-teal-800" href="{{ route('inventory.import') }}">{{ __('inventory.bulk_upload') }}</a><a class="ui-create-action rounded-xl bg-emerald-700 px-4 py-2 text-xs text-white hover:bg-emerald-800" href="{{ route('inventory.create') }}"><x-icon name="plus" :size="18" />{{ __('ui.register_part') }}</a>@endcan
</div></div>

@include('modules.inventory.feedback')
<form class="mb-5 flex gap-3"><input class="form-input" name="q" value="{{ request('q') }}" placeholder="Name, SKU or barcode"><select class="form-input" name="state"><option value="">All stock</option><option value="low" @selected(request('state')==='low')>Low stock</option><option value="out" @selected(request('state')==='out')>Out of stock</option></select><button class="rounded-xl bg-teal-700 px-4 text-white">Search</button></form>
<div class="overflow-x-auto rounded-2xl border bg-white"><table class="w-full text-left text-sm"><thead><tr><th class="p-3">SKU</th><th>Part</th><th>Barcode</th><th>Current stock</th><th>Selling price (LKR)</th><th>Active</th><th>{{ __('ui.actions') }}</th></tr></thead><tbody>
@forelse($parts as $part)<tr class="border-t"><td class="p-3"><a class="text-teal-700 underline" href="{{ route('inventory.show',$part->id) }}">{{ $part->sku }}</a></td><td>{{ $part->name }}</td><td>{{ $part->barcode }}</td><td>{{ $part->available_quantity }}</td><td>{{ $part->selling_price }}</td><td>{{ $part->is_active ? 'Yes' : 'No' }}</td><td><div class="flex gap-2"><a class="ui-icon-action ui-icon-action--teal" href="{{ route('inventory.show',$part->id) }}" title="{{ __('ui.open_record') }}" aria-label="{{ __('ui.open_record') }}"><x-action-symbol icon="folder-open" :label="__('ui.open_record')" /></a>@can('inventory.update')<a class="ui-icon-action ui-icon-action--blue" href="{{ route('inventory.edit',$part->id) }}" title="{{ __('ui.edit') }}" aria-label="{{ __('ui.edit') }}"><x-action-symbol icon="edit" :label="__('ui.edit')" /></a>@endcan</div></td></tr>
@empty<tr><td class="p-4" colspan="7">No parts found.</td></tr>
@endforelse</tbody></table></div><div class="mt-4">{{ $parts->links() }}</div>

@endsection
