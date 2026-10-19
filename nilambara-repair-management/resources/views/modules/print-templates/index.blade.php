@extends('layouts.app')

@section('title', __('print_templates.title'))

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('print_templates.title') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('print_templates.description') }}</p>
    </div>
    @if (session('status'))<p class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>@endif
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 text-slate-600"><tr><th class="px-4 py-3">{{ __('print_templates.document') }}</th><th class="px-4 py-3">{{ __('print_templates.name') }}</th><th class="px-4 py-3">{{ __('print_templates.version') }}</th><th class="px-4 py-3">{{ __('print_templates.paper') }}</th><th class="px-4 py-3">{{ __('print_templates.state') }}</th><th class="px-4 py-3">{{ __('print_templates.actions') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($templates as $template)
                    <tr><td class="px-4 py-3">{{ $template->document_type }}</td><td class="px-4 py-3">{{ $template->name }}</td><td class="px-4 py-3">{{ $template->version }}</td><td class="px-4 py-3">{{ $template->paper_size }} · {{ $template->orientation }}</td><td class="px-4 py-3">{{ $template->is_active ? __('print_templates.active') : __('print_templates.archived') }}</td><td class="whitespace-nowrap px-4 py-3"><a class="text-teal-700 hover:underline" href="{{ route('print-templates.preview', $template) }}">{{ __('print_templates.preview') }}</a>@if ($template->is_active && auth()->user()->can('print-templates.update'))<a class="ml-3 text-teal-700 hover:underline" href="{{ route('print-templates.preview', $template) }}#edit">{{ __('print_templates.edit') }}</a>@endif</td></tr>
                @empty
                    <tr><td class="px-4 py-10 text-center text-slate-500" colspan="6">{{ __('print_templates.none') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $templates->links() }}</div>
@endsection
