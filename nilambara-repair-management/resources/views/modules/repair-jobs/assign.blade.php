@extends('layouts.app')

@section('title', __('ui.assign_technician'))

@section('content')
    <div class="mb-7">
        <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.repair_jobs') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.assign_technician') }} · {{ $job->automatic_number }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $job->item_name }} · {{ $job->customer->display_name }}</p>
    </div>
    <div class="grid items-start gap-6 xl:grid-cols-2">
    <div class="min-w-0 max-w-xl space-y-6">
    <form method="POST" action="{{ route('repair-jobs.assign', $job) }}" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        @csrf
        <div>
            <label class="form-label" for="technician_id">{{ __('ui.technician') }} <span class="text-red-600">*</span></label>
            <select id="technician_id" name="technician_id" class="form-input" required>
                <option value="">{{ __('ui.select_technician') }}</option>
                @foreach ($technicians as $technician)
                    <option value="{{ $technician->id }}" @selected((string) old('technician_id', $job->technician_id) === (string) $technician->id)>{{ $technician->name }} · {{ $technician->employee_code }}</option>
                @endforeach
            </select>
            @error('technician_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @if ($technicians->isEmpty())<p class="mt-2 text-xs text-amber-700">{{ __('ui.no_active_technicians') }}</p>@endif
        </div>
        <div class="flex justify-end gap-3"><a href="{{ route('repair-jobs.show', $job) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('ui.cancel') }}</a><button @disabled($technicians->isEmpty()) class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white disabled:opacity-50">{{ __('ui.save_assignment') }}</button></div>
    </form>

    <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7" x-data="{ adding: @js($errors->has('name') || $errors->has('mobile_number')), editing: null }">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-slate-900">{{ __('ui.manage_technicians') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('ui.add_technician_help') }}</p>
            </div>
            <button type="button" x-on:click="adding = ! adding" class="shrink-0 rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white hover:bg-teal-800">+ {{ __('ui.add_technician') }}</button>
        </div>

        <form x-show="adding" x-cloak method="POST" action="{{ route('repair-jobs.technicians.store', $job) }}" class="space-y-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="form-label" for="technician_name">{{ __('ui.technician_name') }} <span class="text-red-600">*</span></label>
                    <input id="technician_name" name="name" value="{{ old('name') }}" maxlength="255" required class="form-input">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label" for="technician_mobile">{{ __('ui.mobile_number') }}</label>
                    <input id="technician_mobile" name="mobile_number" value="{{ old('mobile_number') }}" maxlength="30" class="form-input">
                    @error('mobile_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" x-on:click="adding = false" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-semibold text-slate-600">{{ __('ui.cancel') }}</button>
                <button type="submit" class="rounded-xl bg-teal-700 px-4 py-2.5 text-xs font-semibold text-white">{{ __('ui.save') }}</button>
            </div>
        </form>

        <ul class="divide-y divide-slate-100">
            @forelse ($technicians as $technician)
                <li class="py-3">
                    <div class="flex items-center justify-between gap-3" x-show="editing !== {{ $technician->id }}">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $technician->name }}</p>
                            <p class="text-xs text-slate-500">{{ $technician->employee_code }}@if ($technician->mobile_number) · {{ $technician->mobile_number }}@endif</p>
                        </div>
                        @if ($technician->user_id === null)
                            <div class="flex shrink-0 gap-2">
                                <button type="button" x-on:click="editing = {{ $technician->id }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600">{{ __('ui.edit') }}</button>
                                <form method="POST" action="{{ route('repair-jobs.technicians.destroy', [$job, $technician]) }}" onsubmit="return confirm(@js(__('ui.confirm_remove_technician')))">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700">{{ __('ui.delete') }}</button>
                                </form>
                            </div>
                        @else
                            <span class="text-[10px] text-slate-400">{{ __('ui.managed_in_employees') }}</span>
                        @endif
                    </div>
                    @if ($technician->user_id === null)
                        <form x-show="editing === {{ $technician->id }}" x-cloak method="POST" action="{{ route('repair-jobs.technicians.update', [$job, $technician]) }}" class="space-y-3">
                            @csrf @method('PUT')
                            <div class="grid gap-3 sm:grid-cols-2">
                                <input name="name" value="{{ $technician->name }}" maxlength="255" required class="form-input" aria-label="{{ __('ui.technician_name') }}">
                                <input name="mobile_number" value="{{ $technician->mobile_number }}" maxlength="30" class="form-input" aria-label="{{ __('ui.mobile_number') }}">
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" x-on:click="editing = null" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600">{{ __('ui.cancel') }}</button>
                                <button type="submit" class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white">{{ __('ui.save') }}</button>
                            </div>
                        </form>
                    @endif
                </li>
            @empty
                <li class="py-3 text-xs text-slate-500">{{ __('ui.no_active_technicians') }}</li>
            @endforelse
        </ul>
    </section>
    </div>

    <aside class="min-w-0 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
        <div>
            <h2 class="text-sm font-bold text-slate-900">{{ __('ui.technician_workload') }}</h2>
            <p class="mt-1 text-xs text-slate-500">{{ __('ui.technician_workload_help') }}</p>
        </div>
        @forelse ($technicians as $technician)
            @php($openJobs = $technician->technicianRepairJobs)
            <div class="rounded-xl border border-slate-200">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3">
                    <p class="truncate text-sm font-semibold text-slate-800">{{ $technician->name }}</p>
                    <span class="shrink-0 rounded-full bg-teal-50 px-2.5 py-0.5 text-[10px] font-semibold text-teal-700">{{ trans_choice('ui.open_jobs_count', $openJobs->count(), ['count' => $openJobs->count()]) }}</span>
                </div>
                @if ($openJobs->isEmpty())
                    <p class="px-4 py-3 text-xs text-slate-400">{{ __('ui.no_open_jobs') }}</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($openJobs as $assigned)
                            <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs">
                                <div class="min-w-0">
                                    <a href="{{ route('repair-jobs.show', $assigned) }}" class="font-semibold text-teal-700 hover:text-teal-900">{{ $assigned->automatic_number ?: $assigned->job_number }}</a>
                                    <span class="text-slate-500"> · {{ $assigned->item_name }}</span>
                                    <p class="truncate text-[10px] text-slate-400">{{ $assigned->customer?->display_name }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">{{ $assigned->status->label() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <p class="text-xs text-slate-500">{{ __('ui.no_active_technicians') }}</p>
        @endforelse
    </aside>
    </div>
@endsection
