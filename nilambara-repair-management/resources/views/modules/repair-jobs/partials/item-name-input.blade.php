@php($itemNames = array_values(array_filter(preg_split('/\R/u', (string) config('business.common_item_names')), fn ($item) => trim($item) !== '')))
<div class="space-y-2" x-data="{
    selected: @js((string) ($value ?? '')),
    add() {
        const name = this.$refs.itemName.value.trim();
        if (name) { this.$refs.itemName.value = name; this.selected = name; }
    },
    remove() { this.selected = ''; this.$refs.itemName.value = ''; this.$refs.itemName.focus(); }
}">
    <select id="item_name_preset" class="form-input" aria-label="{{ __('settings.select_item_name') }}" @disabled($itemNames === []) x-on:change="
        if ($el.value !== '') {
            $refs.itemName.value = $el.value;
            selected = $el.value;
        }
        $el.value = '';
    ">
        <option value="">{{ __('settings.select_item_name') }}</option>
        @foreach ($itemNames as $itemName)
            <option value="{{ $itemName }}">{{ $itemName }}</option>
        @endforeach
    </select>
    <div class="flex items-center gap-2">
        <input id="item_name" name="item_name" x-ref="itemName" value="{{ $value ?? '' }}" class="form-input min-w-0" required maxlength="255" placeholder="{{ __('settings.item_name_placeholder') }}" aria-describedby="item_name_help" x-on:input="selected = ''" x-on:keydown.enter.prevent="add()">
        <button type="button" x-on:click="add()" class="ui-create-action shrink-0 rounded-lg border border-teal-200 bg-teal-50 px-3 text-xs font-semibold text-teal-800" title="{{ __('settings.add_item') }}"><x-icon name="plus" :size="16" />{{ __('settings.add_item') }}</button>
    </div>
    <div class="overflow-hidden rounded-lg border border-slate-200">
        <table class="w-full table-fixed text-left text-xs">
            <caption class="sr-only">{{ __('ui.item_name') }}</caption>
            <thead><tr><th scope="col" class="px-3 py-2">{{ __('ui.item_name') }}</th><th scope="col" class="w-14 px-2 py-2"><span class="sr-only">{{ __('settings.remove_item') }}</span></th></tr></thead>
            <tbody>
                <tr x-show="selected !== ''" x-cloak><td class="break-words px-3 py-2" style="overflow-wrap:anywhere" x-text="selected"></td><td class="px-2 py-2 text-center"><button type="button" x-on:click="remove()" class="condition-remove ui-icon-action ui-icon-action--danger" title="{{ __('settings.remove_item') }}" x-bind:aria-label="@js(__('settings.remove_item')) + ' ' + selected"><x-icon name="trash" :size="15" /><span class="sr-only">{{ __('settings.remove_item') }}</span></button></td></tr>
                <tr x-show="selected === ''"><td colspan="2" class="px-3 py-3 text-slate-500">{{ __('ui.condition_list_empty') }}</td></tr>
            </tbody>
        </table>
    </div>
    <p id="item_name_help" class="mt-1 text-xs text-slate-500">{{ $itemNames === [] ? __('settings.item_name_empty_help') : __('settings.item_name_help') }}</p>
</div>
