@php($presets = array_values(array_filter(preg_split('/\R/u', (string) config('business.'.$config)))))
@if ($presets !== [])
    <select class="form-input mb-2" aria-label="{{ __('ui.common_faults_hint') }}" x-on:change="
        const box = document.getElementById('{{ $target }}');
        const value = $el.value;
        if (value !== '') {
            const lines = box.value.split(/\r?\n/).map(l => l.trim());
            if (!lines.includes(value)) {
                box.value = box.value.trim() === '' ? value : box.value.replace(/\s+$/, '') + '\n' + value;
            }
            box.focus();
        }
        $el.value = '';
    ">
        <option value="">{{ __('ui.common_faults_hint') }}</option>
        @foreach ($presets as $preset)
            <option value="{{ $preset }}">{{ $preset }}</option>
        @endforeach
    </select>
@endif
