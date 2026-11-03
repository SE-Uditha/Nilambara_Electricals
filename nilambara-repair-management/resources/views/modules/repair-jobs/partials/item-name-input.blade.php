@php($itemNames = array_values(array_filter(preg_split('/\R/u', (string) config('business.common_item_names')), fn ($item) => trim($item) !== '')))
<div x-data="{}">
    @if ($itemNames !== [])
        <select id="item_name_preset" class="form-input mb-2" aria-label="{{ __('settings.select_item_name') }}" x-on:change="
            if ($el.value !== '') {
                $refs.itemName.value = $el.value;
                $refs.itemName.dispatchEvent(new Event('input', { bubbles: true }));
                $refs.itemName.focus();
            }
            $el.value = '';
        ">
            <option value="">{{ __('settings.select_item_name') }}</option>
            @foreach ($itemNames as $itemName)
                <option value="{{ $itemName }}">{{ $itemName }}</option>
            @endforeach
        </select>
    @endif
    <input id="item_name" name="item_name" x-ref="itemName" value="{{ $value ?? '' }}" class="form-input" required maxlength="255" placeholder="{{ __('settings.item_name_placeholder') }}" @if ($itemNames !== []) aria-describedby="item_name_help" @endif>
    @if ($itemNames !== [])
        <p id="item_name_help" class="mt-1 text-xs text-slate-500">{{ __('settings.item_name_help') }}</p>
    @endif
</div>
