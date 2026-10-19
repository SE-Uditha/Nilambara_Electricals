@extends('layouts.app')

@section('title', __('ui.assign_technician'))

@section('content')
    <div class="mb-7">
        <p class="mb-2 text-[10px] font-semibold tracking-[0.15em] text-teal-700 uppercase">{{ __('ui.repair_jobs') }}</p>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ __('ui.assign_technician') }} · {{ $job->automatic_number }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $job->item_name }} · {{ $job->customer->display_name }}</p>
    </div>
    <form method="POST" action="{{ route('repair-jobs.assign', $job) }}" class="max-w-xl space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
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
@endsection
