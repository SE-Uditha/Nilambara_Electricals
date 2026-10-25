@php($items = array_values(array_filter(preg_split('/\R/u', (string) old($field, config('business.'.$field))))))
<div class="space-y-2" x-data="{ items: @js($items), draft: '', add() { const v = this.draft.replace(/\s+/g, ' ').trim(); if (v !== '' && v.length <= 150 && this.items.length < 100 && !this.items.some(i => i.toLowerCase() === v.toLowerCase())) { this.items.push(v); } this.draft = ''; } }">
    <label class="form-label" for="{{ $field }}_draft">{{ __('settings.'.$field) }}</label>
    <input type="hidden" name="{{ $field }}" x-bind:value="items.join('\n')" value="{{ implode("\n", $items) }}">
    <div class="flex gap-2">
        <input id="{{ $field }}_draft" type="text" maxlength="150" x-model="draft" x-on:keydown.enter.prevent="add()" class="form-input" placeholder="{{ __('settings.'.$field.'_placeholder') }}">
        <button type="button" x-on:click="add()" class="shrink-0 rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white">{{ __('settings.add_item') }}</button>
    </div>
    <ul class="flex flex-wrap gap-1.5">
        <template x-for="(item, index) in items" :key="item">
            <li class="inline-flex items-center gap-1 rounded-full border border-teal-200 bg-teal-50 px-2.5 py-1 text-xs font-medium text-teal-800">
                <span x-text="item"></span>
                <button type="button" x-on:click="items.splice(index, 1)" class="text-teal-700 hover:text-red-700" aria-label="{{ __('settings.remove_item') }}">&times;</button>
            </li>
        </template>
    </ul>
    <p class="text-xs text-slate-500">{{ __('settings.'.$field.'_help') }}</p>
    @error($field)<p class="text-xs text-red-700">{{ $message }}</p>@enderror
</div>
