@extends('layouts.app')

@section('title', __('settings.title'))

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">{{ __('settings.title') }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ __('settings.description') }}</p>
    </div>
    @if (session('status'))
        <p class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>
    @endif
    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="max-w-3xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
        @csrf
        @method('PUT')
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="form-label">Default language<select class="form-input" name="default_language">@foreach (config('business.locales') as $code => $label)<option value="{{ $code }}" @selected(config('business.default_language', 'en') === $code)>{{ $label }}</option>@endforeach</select></label>
            <label class="form-label">Default document paper<select class="form-input" name="print_paper">@foreach (['A4','A5'] as $paper)<option @selected(config('business.printing.paper') === $paper)>{{ $paper }}</option>@endforeach</select></label>
            <label class="form-label sm:col-span-2">Default document footer<textarea class="form-input" name="document_footer" maxlength="2000">{{ old('document_footer', config('business.document_footer')) }}</textarea></label>
            <label class="form-label">{{ __('settings.business_name') }}<input class="form-input mt-1" name="name" value="{{ old('name', config('business.name')) }}" required maxlength="255"></label>
            <label class="form-label">{{ __('settings.contact_number') }}<input class="form-input mt-1" name="contact_number" value="{{ old('contact_number', config('business.contact_number')) }}" maxlength="40"></label>
            <label class="form-label sm:col-span-2">{{ __('settings.address') }}<textarea class="form-input mt-1" name="address" rows="3" maxlength="2000">{{ old('address', config('business.address')) }}</textarea></label>
            <label class="form-label">{{ __('settings.currency') }}<input class="form-input mt-1 uppercase" name="currency" value="{{ old('currency', config('business.currency')) }}" required maxlength="3"></label>
            <label class="form-label">{{ __('settings.timezone') }}<select class="form-input mt-1" name="timezone">@foreach (timezone_identifiers_list() as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', config('business.timezone')) === $timezone)>{{ $timezone }}</option>@endforeach</select></label>
            <label class="form-label">{{ __('settings.date_format') }}<select class="form-input mt-1" name="date_format">@foreach (['d M Y', 'Y-m-d', 'd/m/Y', 'm/d/Y'] as $format)<option value="{{ $format }}" @selected(old('date_format', config('business.date_format')) === $format)>{{ $format }}</option>@endforeach</select></label>
            <label class="form-label">{{ __('settings.time_format') }}<select class="form-input mt-1" name="time_format">@foreach (['h:i A', 'H:i'] as $format)<option value="{{ $format }}" @selected(old('time_format', config('business.time_format')) === $format)>{{ $format }}</option>@endforeach</select></label>
        </div>
        <label class="form-label block">{{ __('settings.logo') }}<input class="form-input mt-1" type="file" name="logo" accept="image/png,image/jpeg,image/webp"><span class="mt-1 block text-xs text-slate-500">{{ __('settings.logo_help') }}</span></label>
        @error('logo')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
        @foreach (['common_faults', 'common_physical_conditions', 'common_visible_damages'] as $field)
            @include('modules.settings.partials.preset-list', ['field' => $field])
        @endforeach
        @can('settings.update')
            <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white">{{ __('settings.save') }}</button>
        @endcan
    </form>
@endsection
