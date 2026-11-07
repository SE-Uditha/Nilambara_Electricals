@extends('layouts.app')

@section('title', __('ui.edit_repair_job'))

@section('content')
    <div class="mb-7">
        <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.repair_jobs') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.edit_repair_job') }} · {{ $job->automatic_number }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('ui.immutable_identifiers_notice') }}</p>
    </div>
    <form method="POST" action="{{ ($requestMode ?? false) ? route('repair-jobs.request-edit.store', $job) : route('repair-jobs.update', $job) }}" class="max-w-4xl space-y-5">
        @csrf
        @unless ($requestMode ?? false)
            @method('PUT')
        @endunless
        <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
            <div class="grid gap-4 sm:grid-cols-2">
                <div><label class="form-label" for="automatic_number">{{ __('ui.job_reference') }}</label><input id="automatic_number" value="{{ $job->automatic_number }}" class="form-input bg-slate-50" readonly></div>
                <div><label class="form-label" for="job_number">{{ __('ui.manual_job_number') }}</label><input id="job_number" value="{{ $job->job_number }}" class="form-input bg-slate-50" readonly></div>
                <div><label class="form-label" for="job_type">{{ __('ui.job_type') }}</label><select id="job_type" name="job_type" class="form-input" required>@foreach (['normal', 'special', 'urgent'] as $type)<option value="{{ $type }}" @selected(old('job_type', $job->job_type) === $type)>{{ __('ui.job_type_'.$type) }}</option>@endforeach</select>@error('job_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="form-label">{{ __('ui.customer') }}</label><p class="form-input bg-slate-50">{{ $job->customer->display_name }}</p></div>
                <div>
                    <label class="form-label" for="item_name">{{ __('ui.item_name') }}</label>
                    @include('modules.repair-jobs.partials.item-name-input', ['value' => old('item_name', $job->item_name)])
                    @error('item_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div><label class="form-label" for="brand">{{ __('ui.brand') }}</label><input id="brand" name="brand" value="{{ old('brand', $job->brand) }}" class="form-input" maxlength="150">@error('brand')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <div><label class="form-label" for="model">{{ __('ui.model') }}</label><input id="model" name="model" value="{{ old('model', $job->model) }}" class="form-input" maxlength="150">@error('model')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror</div>
                <p class="sm:col-span-2 text-[10px] text-slate-400">{{ __('ui.brand_or_model_required') }}</p>
                <div class="sm:col-span-2"><label class="form-label" for="item_description">{{ __('ui.item_description') }}</label><textarea id="item_description" name="item_description" rows="3" class="form-input">{{ old('item_description', $job->item_description) }}</textarea></div>
                <div class="sm:col-span-2"><label class="form-label" for="reported_fault">{{ __('ui.reported_fault') }}</label>@include('modules.repair-jobs.partials.condition-list', ['config' => 'common_faults', 'field' => 'reported_fault', 'value' => old('reported_fault', $job->reported_fault), 'maxLength' => 10000, 'limitMessage' => __('ui.reported_fault_list_limit'), 'preserveOriginal' => true])</div>
                <div class="sm:col-span-2"><label class="form-label" for="accessories_received">{{ __('ui.accessories_received') }}</label><textarea id="accessories_received" name="accessories_received" rows="3" class="form-input">{{ old('accessories_received', $job->accessories_received) }}</textarea></div>
                <div><label class="form-label" for="physical_condition">{{ __('ui.physical_condition') }}</label>@include('modules.repair-jobs.partials.condition-list', ['config' => 'common_physical_conditions', 'field' => 'physical_condition', 'value' => old('physical_condition', $job->physical_condition)])</div>
                <div><label class="form-label" for="visible_damage">{{ __('ui.visible_damage') }}</label>@include('modules.repair-jobs.partials.condition-list', ['config' => 'common_visible_damages', 'field' => 'visible_damage', 'value' => old('visible_damage', $job->visible_damage)])</div>
                <div class="sm:col-span-2"><label class="form-label" for="special_notes">{{ __('ui.special_notes') }}</label><textarea id="special_notes" name="special_notes" rows="3" class="form-input">{{ old('special_notes', $job->special_notes) }}</textarea></div>
                    <div class="sm:col-span-2 rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                        <label class="form-label" for="private_notes">{{ __('ui.private_notes') }}</label>
                        <textarea id="private_notes" name="private_notes" rows="3" maxlength="10000" class="form-input" aria-describedby="private_notes_help">{{ old('private_notes', $job->private_notes) }}</textarea>
                        <p id="private_notes_help" class="mt-2 text-xs text-slate-500">{{ __('ui.private_notes_help') }}</p>
                        @error('private_notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

            </div>
        </section>
        @if ($requestMode ?? false)
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-7">
                <p class="text-xs text-amber-900">{{ __('ui.edit_request_notice') }}</p>
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <label class="form-label mt-4" for="reason">{{ __('ui.edit_request_reason') }} <span class="text-red-600">*</span></label>
                <textarea id="reason" name="reason" rows="3" required minlength="5" maxlength="2000" class="form-input">{{ old('reason') }}</textarea>
                @error('reason')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </section>
        @endif
        <div class="flex justify-end gap-3"><a href="{{ route('repair-jobs.show', $job) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('ui.cancel') }}</a><button class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white">{{ ($requestMode ?? false) ? __('ui.submit_edit_request') : __('ui.save_changes') }}</button></div>
    </form>
@endsection
