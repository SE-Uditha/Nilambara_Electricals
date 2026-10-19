@extends('layouts.app')

@section('title', __('reports.title'))

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('reports.title') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('reports.subtitle') }}</p>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as $report)
            <a href="{{ route('reports.show', $report) }}" class="rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-teal-300">
                <h2 class="font-semibold text-slate-900">{{ __('reports.'.$report.'_title') }}</h2>
                @if ($report === 'profit-estimate')
                    <p class="mt-2 text-xs text-amber-700">{{ __('reports.profit_estimate_only') }}</p>
                @elseif ($report === 'valuation')
                    <p class="mt-2 text-xs text-amber-700">{{ __('reports.valuation_policy_missing') }}</p>
                @endif
            </a>
        @endforeach
    </div>
@endsection
