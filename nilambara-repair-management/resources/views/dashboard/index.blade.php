@extends('layouts.app')

@section('title', __('ui.dashboard'))

@section('content')
    <div class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <p class="mb-2 text-[10px] font-semibold tracking-[0.16em] text-teal-700 uppercase">{{ __('ui.workspace_overview') }}</p>
            <h1 class="text-[28px] leading-tight font-bold tracking-tight text-slate-900 lg:text-[32px]">{{ __('ui.dashboard') }}</h1>
            <p class="mt-2 max-w-2xl text-[13px] leading-6 text-slate-500">{{ __('ui.welcome') }}</p>
        </div>
        <span class="inline-flex w-fit shrink-0 items-center gap-2 rounded-full border border-teal-200/80 bg-teal-50 px-3 py-1.5 text-[10px] font-semibold text-teal-800"><span class="size-1.5 rounded-full bg-teal-500" aria-hidden="true"></span>{{ __('ui.staff_workspace') }}</span>
    </div>

    <livewire:dashboard-overview />
@endsection
