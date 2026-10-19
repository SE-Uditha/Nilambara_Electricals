@extends('layouts.app')

@section('title', __('print_templates.preview_title', ['name' => $template->name]))

@section('content')
    @php
        $paperSizes = match ($template->document_type) {
            'quotation' => ['A4', 'A5'],
            'pos_receipt' => ['A4', 'A5', 'thermal'],
            'repair_receipt' => ['A4', 'A5', 'A6'],
            default => ['A4', 'A5', 'A6'],
        };
    @endphp
    <div class="mb-6 flex items-start justify-between gap-3">
        <div><a class="text-xs text-teal-700 hover:underline" href="{{ route('print-templates.index') }}">{{ __('print_templates.title') }}</a><h1 class="mt-2 text-2xl font-bold">{{ $template->name }} · v{{ $template->version }}</h1><p class="mt-1 text-sm text-slate-500">{{ __('print_templates.preview_note') }}</p></div>
    </div>
    <div class="mb-6 overflow-auto rounded-xl border border-slate-200 bg-slate-100 p-6">
        <article class="mx-auto bg-white p-8 shadow" style="max-width: 900px; font-size: {{ $template->font_size_pt }}pt; padding: {{ $template->margin_top_mm }}mm {{ $template->margin_right_mm }}mm {{ $template->margin_bottom_mm }}mm {{ $template->margin_left_mm }}mm">
            <header class="border-b border-slate-300 pb-4 text-center">
                <p class="text-xl font-bold">{{ $template->business_name ?: config('business.name') }}</p>
                @if ($template->business_address ?: config('business.address'))<p>{{ $template->business_address ?: config('business.address') }}</p>@endif
                @if ($template->telephone_number ?: config('business.contact_number'))<p>{{ $template->telephone_number ?: config('business.contact_number') }}</p>@endif
                <h2 class="mt-5 text-lg font-semibold">{{ $template->document_title ?: $template->name }}</h2>
            </header>
            <p class="my-5">{{ __('print_templates.sample_preview') }}</p>
            <table class="w-full border-collapse"><thead><tr><th class="border p-2">{{ __('print_templates.sample_item') }}</th><th class="border p-2">{{ __('print_templates.sample_quantity') }}</th><th class="border p-2">{{ __('print_templates.sample_amount') }}</th></tr></thead><tbody><tr><td class="border p-2">{{ __('print_templates.sample_service') }}</td><td class="border p-2">1</td><td class="border p-2">{{ config('business.currency') }} 0.00</td></tr></tbody></table>
            <footer class="mt-6 border-t border-slate-300 pt-3">{{ $template->footer }}</footer>
        </article>
    </div>
    @can('print-templates.update')
        @if ($template->is_active)
            <form id="edit" method="POST" action="{{ route('print-templates.update', $template) }}" class="grid max-w-5xl gap-4 rounded-2xl border border-slate-200 bg-white p-6 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <label class="form-label">{{ __('print_templates.name') }}<input class="form-input mt-1" name="name" required maxlength="150" value="{{ old('name', $template->name) }}"></label>
                <label class="form-label">{{ __('print_templates.document_title') }}<input class="form-input mt-1" name="document_title" maxlength="255" value="{{ old('document_title', $template->document_title) }}"></label>
                <label class="form-label">{{ __('print_templates.business_name') }}<input class="form-input mt-1" name="business_name" maxlength="255" value="{{ old('business_name', $template->business_name) }}"></label>
                <label class="form-label">{{ __('print_templates.telephone') }}<input class="form-input mt-1" name="telephone_number" maxlength="40" value="{{ old('telephone_number', $template->telephone_number) }}"></label>
                <label class="form-label sm:col-span-2">{{ __('print_templates.address') }}<textarea class="form-input mt-1" name="business_address" rows="2" maxlength="2000">{{ old('business_address', $template->business_address) }}</textarea></label>
                <label class="form-label">{{ __('print_templates.paper') }}<select class="form-input mt-1" name="paper_size">@foreach ($paperSizes as $size)<option value="{{ $size }}" @selected(old('paper_size', $template->paper_size) === $size)>{{ $size }}</option>@endforeach</select></label>
                <label class="form-label">{{ __('print_templates.orientation') }}<select class="form-input mt-1" name="orientation">@foreach (['portrait', 'landscape'] as $orientation)<option value="{{ $orientation }}" @selected(old('orientation', $template->orientation) === $orientation)>{{ __('print_templates.'.$orientation) }}</option>@endforeach</select></label>
                <label class="form-label">{{ __('print_templates.thermal_width') }}<input class="form-input mt-1" type="number" min="40" max="120" step="0.01" name="thermal_width_mm" value="{{ old('thermal_width_mm', $template->thermal_width_mm) }}"></label>
                <label class="form-label">{{ __('print_templates.font_size') }}<input class="form-input mt-1" type="number" min="7" max="16" step="0.1" name="font_size_pt" required value="{{ old('font_size_pt', $template->font_size_pt) }}"></label>
                @foreach (['top', 'right', 'bottom', 'left'] as $edge)
                    <label class="form-label">{{ __('print_templates.margin_'.$edge) }}<input class="form-input mt-1" type="number" min="0" max="30" step="0.1" name="margin_{{ $edge }}_mm" required value="{{ old('margin_'.$edge.'_mm', $template->{'margin_'.$edge.'_mm'}) }}"></label>
                @endforeach
                <label class="form-label sm:col-span-2">{{ __('print_templates.footer') }}<textarea class="form-input mt-1" name="footer" rows="3" maxlength="2000">{{ old('footer', $template->footer) }}</textarea></label>
                <label class="form-label">{{ __('print_templates.duplicate_label') }}<input class="form-input mt-1" name="duplicate_copy_label" required maxlength="100" value="{{ old('duplicate_copy_label', $template->duplicate_copy_label) }}"></label>
                <div class="flex items-end gap-3 sm:col-span-2">
                    <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white">{{ __('print_templates.save_version') }}</button>
                    <span class="text-xs text-slate-500">{{ __('print_templates.version_note') }}</span>
                </div>
            </form>
        @endif
    @endcan
    @can('print-templates.reset')
        <form method="POST" action="{{ route('print-templates.reset', $template) }}" class="mt-4" onsubmit="return confirm('{{ __('print_templates.confirm_reset') }}')">
            @csrf
            <button class="rounded-lg border border-amber-300 px-4 py-2 text-xs font-semibold text-amber-800">{{ __('print_templates.reset') }}</button>
        </form>
    @endcan
@endsection
