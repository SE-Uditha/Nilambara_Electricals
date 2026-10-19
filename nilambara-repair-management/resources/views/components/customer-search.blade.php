@props([
    'name' => 'customer_id',
    'label' => __('ui.select_customer'),
    'required' => false,
    'customer' => null,
])

<div
    x-data="{
        required: @js($required),
        term: @js($customer['name'] ?? ''),
        selected: @js($customer),
        results: [],
        loading: false,
        error: '',
        timer: null,
        searchRequest: 0,
        async search() {
            clearTimeout(this.timer);
            this.searchRequest++;
            const requestId = this.searchRequest;
            this.selected = null;
            this.$dispatch('customer-selected', null);
            if (this.term.trim().length < 2) { this.results = []; this.error = ''; return; }
            this.loading = true;
            const term = this.term.trim();
            this.timer = setTimeout(async () => {
                try {
                    const response = await fetch(@js(route('customers.search')) + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' } });
                    if (!response.ok) throw new Error(@js(__('ui.customer_search_failed')));
                    const result = (await response.json()).data;
                    if (requestId !== this.searchRequest) return;
                    this.results = result;
                    this.error = '';
                } catch (error) {
                    if (requestId !== this.searchRequest) return;
                    this.results = [];
                    this.error = error.message;
                } finally {
                    if (requestId === this.searchRequest) this.loading = false;
                }
            }, 250);
        }
    }"
    class="relative"
>
    <label class="mb-2 block text-xs font-semibold text-slate-700">{{ $label }} @if ($required)<span class="text-red-600">*</span>@endif</label>
    <input type="hidden" name="{{ $name }}" x-bind:value="selected?.id ?? ''">
    <input type="search" x-model="term" x-on:input="search()" x-bind:required="required && !selected" autocomplete="off" class="form-input" placeholder="{{ __('ui.customer_search_placeholder') }}" role="combobox" aria-autocomplete="list" aria-controls="{{ $name }}-results" x-bind:aria-expanded="results.length > 0">
    <p x-show="selected" x-text="selected ? selected.customer_code + ' · ' + selected.name : ''" class="mt-2 text-xs font-semibold text-teal-800"></p>
    <p x-show="loading" class="mt-2 text-xs text-slate-500">{{ __('ui.searching') }}</p>
    <p x-show="error" x-text="error" role="alert" class="mt-2 text-xs text-red-600"></p>
    <div x-show="results.length > 0" x-cloak id="{{ $name }}-results" role="listbox" class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg">
        <template x-for="customer in results" :key="customer.id">
            <button type="button" x-on:click="selected = customer; term = customer.name; results = []; $dispatch('customer-selected', customer)" role="option" class="block w-full border-b border-slate-100 px-4 py-3 text-left last:border-0 hover:bg-teal-50">
                <span class="block text-xs font-semibold text-slate-800" x-text="customer.name"></span>
                <span class="mt-1 block text-[10px] text-slate-500" x-text="customer.customer_code + ' · ' + customer.type + ' · ' + customer.mobile_number"></span>
            </button>
        </template>
    </div>
</div>
