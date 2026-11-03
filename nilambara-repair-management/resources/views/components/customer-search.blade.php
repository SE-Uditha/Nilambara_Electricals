@props(['name' => 'customer_id', 'label' => __('ui.select_customer'), 'required' => false, 'customer' => null])
<div class="relative" x-on:click.outside="close()" x-data="{
    required: @js($required), term: @js($customer['name'] ?? ''), selected: @js($customer),
    results: [], loading: false, error: '', timer: null, searchRequest: 0,
    open: false, activeIndex: -1, page: 0, hasMore: false, query: '', browsing: false,
    close() { clearTimeout(this.timer); this.searchRequest++; this.loading = false; this.open = false; this.activeIndex = -1; },
    choose(customer) {
        this.selected = customer; this.term = customer.name; this.close();
        this.$dispatch('customer-selected', customer); this.$refs.search.focus();
    },
    search() {
        clearTimeout(this.timer); this.searchRequest++; this.loading = false;
        this.selected = null; this.$dispatch('customer-selected', null);
        this.results = []; this.activeIndex = -1; this.error = ''; this.hasMore = false;
        if (this.term.trim().length === 0) { this.showAll(); return; }
        if (this.term.trim().length &lt; 2) { this.open = false; return; }
        this.open = true; this.loading = true;
        this.timer = setTimeout(() => { this.query = this.term.trim(); this.browsing = false; this.fetchPage(1); }, 250);
    },
    async showAll() {
        clearTimeout(this.timer); this.query = ''; this.browsing = true;
        this.results = []; this.activeIndex = -1; this.open = true; this.error = '';
        await this.fetchPage(1);
    },
    async fetchPage(page) {
        const requestId = ++this.searchRequest; this.loading = true;
        try {
            const params = new URLSearchParams({ q: this.query, page: String(page) });
            if (this.browsing) params.set('browse', '1');
            const response = await fetch(@js(route('customers.search')) + '?' + params, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(@js(__('ui.customer_search_failed')));
            const payload = await response.json();
            if (requestId !== this.searchRequest) return;
            this.results = page === 1 ? payload.data : [...this.results, ...payload.data];
            this.page = page; this.hasMore = payload.meta?.has_more ?? false; this.error = '';
            if (page === 1) this.activeIndex = this.results.length ? 0 : -1;
        } catch (error) {
            if (requestId !== this.searchRequest) return;
            this.error = error.message; this.hasMore = false;
        } finally { if (requestId === this.searchRequest) this.loading = false; }
    },
    async loadNext() { if (!this.loading &amp;&amp; this.hasMore) await this.fetchPage(this.page + 1); },
    async move(direction) {
        if (!this.open) { await this.showAll(); return; }
        if (this.loading) return;
        let next = this.activeIndex + direction;
        if (next >= this.results.length &amp;&amp; this.hasMore) await this.loadNext();
        this.activeIndex = Math.max(0, Math.min(next, this.results.length - 1));
        this.$nextTick(() => document.getElementById(@js($name.'-option-') + this.activeIndex)?.scrollIntoView({ block: 'nearest' }));
    }
}">
    <label for="{{ $name }}-search" class="mb-2 block text-xs font-semibold text-slate-700">{{ $label }} @if ($required)<span class="text-red-600">*</span>@endif</label>
    <input type="hidden" name="{{ $name }}" x-bind:value="selected?.id ?? ''">
    <div class="relative">
        <input id="{{ $name }}-search" x-ref="search" type="search" x-model="term" x-on:input="search()"
            x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)"
            x-on:keydown.enter="if (open) { $event.preventDefault(); if (activeIndex >= 0 &amp;&amp; results[activeIndex]) choose(results[activeIndex]); }"
            x-on:keydown.escape.prevent.stop="close()" x-on:keydown.tab="close()"
            x-bind:required="required &amp;&amp; !selected" autocomplete="off" class="form-input" style="padding-right:48px"
            placeholder="{{ __('ui.customer_search_placeholder') }}" role="combobox" aria-autocomplete="list" aria-haspopup="listbox"
            aria-controls="{{ $name }}-results" x-bind:aria-expanded="open" x-bind:aria-activedescendant="open &amp;&amp; activeIndex >= 0 ? @js($name.'-option-') + activeIndex : null">
        <button type="button" x-on:click="if (open) close(); else { showAll(); $refs.search.focus(); }" class="absolute right-0 top-0 flex h-full w-11 items-center justify-center rounded-r-lg text-teal-700 hover:bg-teal-50" aria-label="{{ __('ui.browse_customers') }}" aria-controls="{{ $name }}-results" x-bind:aria-expanded="open">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6" /></svg>
        </button>
    </div>
    <div x-show="open" x-cloak class="absolute z-30 mt-1 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
        <div id="{{ $name }}-results" role="listbox" aria-label="{{ $label }}" class="max-h-64 overflow-y-auto" x-on:scroll="if ($el.scrollTop + $el.clientHeight >= $el.scrollHeight - 40) loadNext()">
            <template x-for="(customer, index) in results" :key="customer.id">
                <button type="button" tabindex="-1" x-bind:id="@js($name.'-option-') + index" x-on:mousedown.prevent x-on:click="choose(customer)" x-on:mouseenter="activeIndex = index" role="option" x-bind:aria-selected="activeIndex === index" x-bind:class="{ 'bg-teal-50': activeIndex === index }" class="block w-full border-b border-slate-100 px-4 py-3 text-left last:border-0 hover:bg-teal-50">
                    <span class="block text-xs font-semibold text-slate-800" x-text="customer.name"></span>
                    <span class="mt-1 block text-[10px] text-slate-500" x-text="customer.customer_code + ' · ' + customer.type + ' · ' + customer.mobile_number"></span>
                </button>
            </template>
        </div>
        <p x-show="loading" role="status" class="px-4 py-3 text-xs text-slate-500">{{ __('ui.searching') }}</p>
        <p x-show="!loading &amp;&amp; !error &amp;&amp; results.length === 0" class="px-4 py-3 text-xs text-slate-500">{{ __('ui.no_customers_found') }}</p>
        <button type="button" x-show="hasMore &amp;&amp; !loading" x-on:click="loadNext()" class="w-full border-t border-slate-100 px-4 py-3 text-xs font-semibold text-teal-700 hover:bg-teal-50">{{ __('ui.customer_load_more') }}</button>
        <p x-show="error" x-text="error" role="alert" class="px-4 py-3 text-xs text-red-600"></p>
    </div>
    <p x-show="selected" x-text="selected ? selected.customer_code + ' · ' + selected.name : ''" class="mt-2 text-xs font-semibold text-teal-800"></p>
</div>
