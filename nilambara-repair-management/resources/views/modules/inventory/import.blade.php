@extends('layouts.app')

@section('title', __('inventory.bulk_upload'))

@section('content')
<a href="{{ route('inventory.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900">← {{ __('inventory.back') }}</a>
<h1 class="mb-4 mt-2 text-2xl font-bold">{{ __('inventory.bulk_upload') }}</h1>

@include('modules.inventory.feedback')
@if ($errors->any())
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
@endif
@if (session('import_errors'))
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <p class="mb-2 font-semibold">{{ __('inventory.import_failed') }}</p>
        <ul class="list-inside list-disc space-y-1">
            @foreach (session('import_errors') as $row => $messages)
                <li>{{ $row > 0 ? __('inventory.import_row', ['row' => $row]) : '' }} {{ implode(' ', $messages) }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid gap-5 lg:grid-cols-2">
    <section class="space-y-3 rounded-2xl border bg-white p-5">
        <h2 class="text-sm font-bold">{{ __('inventory.import_steps') }}</h2>
        <ol class="list-inside list-decimal space-y-1 text-sm text-slate-700">
            <li>{{ __('inventory.import_step_1') }}</li>
            <li>{{ __('inventory.import_step_2') }}</li>
            <li>{{ __('inventory.import_step_3') }}</li>
        </ol>
        <a href="{{ route('inventory.import.template') }}" class="inline-block rounded-xl border border-teal-700 px-4 py-2 text-sm font-semibold text-teal-800">{{ __('inventory.download_template') }}</a>
        <form method="POST" action="{{ route('inventory.import.store') }}" enctype="multipart/form-data" class="space-y-3 border-t pt-4">
            @csrf
            <label for="file" class="form-label">{{ __('inventory.excel_file') }}</label>
            <input id="file" name="file" type="file" accept=".xlsx" required class="form-input">
            <button class="rounded-xl bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">{{ __('inventory.upload_import') }}</button>
        </form>
    </section>
    <section class="space-y-2 rounded-2xl border bg-white p-5 text-sm text-slate-700">
        <h2 class="text-sm font-bold">{{ __('inventory.import_rules') }}</h2>
        <p>{{ __('inventory.import_required_columns') }}</p>
        <p>{{ __('inventory.import_rule_new_only') }}</p>
        <p>{{ __('inventory.import_rule_all_or_nothing') }}</p>
        <p class="font-semibold">{{ __('inventory.unit_codes') }}: <span class="font-normal">{{ $units->pluck('code')->implode(', ') }}</span></p>
        <p class="font-semibold">{{ __('inventory.location_codes') }}: <span class="font-normal">{{ $locations->pluck('code')->implode(', ') }}</span></p>
    </section>
</div>
@endsection
