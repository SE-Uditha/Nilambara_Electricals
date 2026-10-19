@extends('layouts.app')

@section('title', __('ui.edit_repair_job'))

@section('content')
    <div class="mb-7">
        <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.repair_jobs') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.edit_repair_job') }} · {{ $job->automatic_number }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('ui.immutable_identifiers_notice') }}</p>
    </div>
    <form method="POST" action="{{ route('repair-jobs.update', $job) }}" class="max-w-4xl space-y-5">
        @csrf
        @method('PUT')
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="form-label" for="automatic_number">{{ __('ui.job_reference') }}</label><input id="automatic_number" value="{{ $job->automatic_number }}" class="form-input bg-slate-50" readonly></div>
                <div><label class="form-label" for="job_number">{{ __('ui.manual_job_number') }}</label><input id="job_number" value="{{ $job->job_number }}" class="form-input bg-slate-50" readonly></div>
                <div><label class="form-label" for="job_type">{{ __('ui.job_type') }}</label><select id="job_type" name="job_type" class="form-input" required>@foreach (['normal', 'special', 'urgent'] as $type)<option value="{{ $type }}" @selected(old('job_type', $job->job_type) === $type)>{{ __('ui.job_type_'.$type) }}</option>@endforeach</select>@error('job_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="form-label">{{ __('ui.customer') }}</label><p class="form-input bg-slate-50">{{ $job->customer->display_name }}</p></div>
                <div><label class="form-label" for="item_name">{{ __('ui.item_name') }}</label><input id="item_name" name="item_name" value="{{ old('item_name', $job->item_name) }}" class="form-input" required maxlength="255">@error('item_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="form-label" for="brand">{{ __('ui.brand') }}</label><input id="brand" name="brand" value="{{ old('brand', $job->brand) }}" class="form-input" maxlength="150">@error('brand')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="form-label" for="model">{{ __('ui.model') }}</label><input id="model" name="model" value="{{ old('model', $job->model) }}" class="form-input" maxlength="150">@error('model')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <p class="sm:col-span-2 text-[10px] text-slate-400">{{ __('ui.brand_or_model_required') }}</p>
                <div class="sm:col-span-2"><label class="form-label" for="item_description">{{ __('ui.item_description') }}</label><textarea id="item_description" name="item_description" rows="3" class="form-input">{{ old('item_description', $job->item_description) }}</textarea></div>
                <div class="sm:col-span-2"><label class="form-label" for="reported_fault">{{ __('ui.reported_fault') }}</label><textarea id="reported_fault" name="reported_fault" rows="4" class="form-input">{{ old('reported_fault', $job->reported_fault) }}</textarea></div>
                <div><label class="form-label" for="accessories_received">{{ __('ui.accessories_received') }}</label><textarea id="accessories_received" name="accessories_received" rows="3" class="form-input">{{ old('accessories_received', $job->accessories_received) }}</textarea></div>
                <div><label class="form-label" for="physical_condition">{{ __('ui.physical_condition') }}</label><textarea id="physical_condition" name="physical_condition" rows="3" class="form-input">{{ old('physical_condition', $job->physical_condition) }}</textarea></div>
                <div class="sm:col-span-2"><label class="form-label" for="visible_damage">{{ __('ui.visible_damage') }}</label><textarea id="visible_damage" name="visible_damage" rows="3" class="form-input">{{ old('visible_damage', $job->visible_damage) }}</textarea></div>
                <div class="sm:col-span-2"><label class="form-label" for="special_notes">{{ __('ui.special_notes') }}</label><textarea id="special_notes" name="special_notes" rows="3" class="form-input">{{ old('special_notes', $job->special_notes) }}</textarea></div>
            </div>
        </section>
        <div class="flex justify-end gap-3"><a href="{{ route('repair-jobs.show', $job) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('ui.cancel') }}</a><button class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white">{{ __('ui.save_changes') }}</button></div>
    </form>
@endsection
