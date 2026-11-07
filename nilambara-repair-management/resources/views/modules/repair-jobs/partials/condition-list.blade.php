@php
    $presets = array_values(array_filter(preg_split('/\R/u', (string) config('business.'.$config)), fn ($item) => trim($item) !== ''));
    $entries = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($value ?? ''))), fn ($item) => $item !== ''));
    $label = __('ui.'.$field);
    $maxLength = $maxLength ?? 5000;
    $limitMessage = $limitMessage ?? __('ui.condition_list_limit');
    $originalValue = ($preserveOriginal ?? false) ? (string) ($value ?? '') : implode("\n", $entries);
@endphp
<div class="condition-list space-y-2" x-data="{
    items: @js($entries), draft: '', error: '', changed: false,
    add(value) {
        const entry = value.replace(/\s+/g, ' ').trim();
        if (!entry) return;
        if (this.items.some(item => item.toLowerCase() === entry.toLowerCase())) { this.draft = ''; this.error = ''; return; }
        if (Array.from([...this.items, entry].join('\n')).length > {{ $maxLength }}) { this.error = @js($limitMessage); return; }
        this.items.push(entry); this.changed = true; this.draft = ''; this.error = '';
    },
    remove(index) { this.items.splice(index, 1); this.changed = true; this.error = ''; }
}">
    <input type="hidden" name="{{ $field }}" value="{{ $originalValue }}" x-bind:value="changed ? items.join('\n') : @js($originalValue)">
    <select id="{{ $field }}" class="form-input" aria-label="{{ $label }}" x-on:change="add($el.value); $el.value = ''" @disabled($presets === [])>
        <option value="">{{ __('ui.select_'.$field) }}</option>
        @foreach ($presets as $preset)
            <option value="{{ $preset }}" x-bind:disabled="items.some(item => item.toLowerCase() === @js(mb_strtolower($preset)))">{{ $preset }}</option>
        @endforeach
    </select>
    <div class="flex items-center gap-2">
        <input id="{{ $field }}_manual" type="text" x-model="draft" maxlength="{{ $maxLength }}" x-on:keydown.enter.prevent="add(draft)" class="form-input min-w-0" placeholder="{{ __('ui.condition_manual_placeholder') }}" aria-label="{{ __('ui.condition_manual_placeholder') }}: {{ $label }}">
        <button type="button" x-on:click="add(draft)" class="ui-create-action shrink-0 rounded-lg border border-teal-200 bg-teal-50 px-3 text-xs font-semibold text-teal-800" title="{{ __('settings.add_item') }}"><x-icon name="plus" :size="16" />{{ __('settings.add_item') }}</button>
    </div>
    <div class="overflow-hidden rounded-lg border border-slate-200">
        <table class="w-full table-fixed text-left text-xs">
            <caption class="sr-only">{{ $label }}</caption>
            <thead><tr><th scope="col" class="px-3 py-2">{{ $label }}</th><th scope="col" class="w-14 px-2 py-2"><span class="sr-only">{{ __('settings.remove_item') }}</span></th></tr></thead>
            <tbody>
                <template x-for="(item, index) in items" :key="item + index">
                    <tr><td class="break-words px-3 py-2" style="overflow-wrap:anywhere" x-text="item"></td><td class="px-2 py-2 text-center"><button type="button" x-on:click="remove(index)" class="condition-remove ui-icon-action ui-icon-action--danger" title="{{ __('settings.remove_item') }}" x-bind:aria-label="@js(__('settings.remove_item')) + ' ' + item"><x-icon name="trash" :size="15" /><span class="sr-only">{{ __('settings.remove_item') }}</span></button></td></tr>
                </template>
                <tr x-show="items.length === 0"><td colspan="2" class="px-3 py-3 text-slate-500">{{ __('ui.condition_list_empty') }}</td></tr>
            </tbody>
        </table>
    </div>
    <p x-show="error" x-text="error" role="alert" class="text-xs text-red-600"></p>
    @error($field)<p class="text-xs text-red-600">{{ $message }}</p>@enderror
</div>
