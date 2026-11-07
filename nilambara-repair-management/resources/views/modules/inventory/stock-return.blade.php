@extends('layouts.app')

@section('title', __('inventory.stock_return'))

@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-bold">{{ __('inventory.stock_return') }}</h1>
    <a class="text-sm font-semibold text-teal-700 underline" href="{{ route('inventory.index') }}">{{ __('inventory.back') }}</a>
</div>
@include('modules.inventory.feedback')
<form method="POST" action="{{ route('stock-returns.store') }}" class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 sm:grid-cols-2 lg:grid-cols-4">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
    <label class="form-label">{{ __('inventory.part_name') }}
        <select name="spare_part_id" required class="form-input" data-searchable data-search-min-length="2" data-search-placeholder="{{ __('ui.search_part_hint') }}" data-search-min-message="{{ __('ui.search_type_minimum', ['count' => 2]) }}" data-search-no-results="{{ __('ui.search_no_results') }}">
            <option value="">{{ __('ui.search_part_hint') }}</option>
            @foreach ($parts as $part)<option value="{{ $part->id }}" @selected((string) old('spare_part_id') === (string) $part->id)>{{ $part->sku }} · {{ $part->name }}</option>@endforeach
        </select>
    </label>
    <label class="form-label">{{ __('inventory.stock_location') }}
        <select name="location_id" required class="form-input">
            @foreach ($locations as $location)<option value="{{ $location->id }}" @selected((string) old('location_id', $locations->first()?->id) === (string) $location->id)>{{ $location->name }}</option>@endforeach
        </select>
    </label>
    <label class="form-label">{{ __('ui.quantity') }}
        <input name="quantity" type="number" min="0.001" step="0.001" required value="{{ old('quantity') }}" class="form-input">
    </label>
    <label class="form-label sm:col-span-2 lg:col-span-4">{{ __('inventory.return_reason') }}
        <textarea name="reason" minlength="5" maxlength="2000" required rows="3" class="form-input h-auto min-h-24 py-3">{{ old('reason') }}</textarea>
    </label>
    <div class="sm:col-span-2 lg:col-span-4">
        <button class="rounded-xl bg-orange-600 px-5 py-3 text-sm font-semibold text-white hover:bg-orange-700">{{ __('inventory.record_stock_return') }}</button>
    </div>
</form>
@endsection
