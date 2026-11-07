@php
    $previewTotals = $partLineTotals->mapWithKeys(fn ($amount, $id) => ['part'.$id => $amount]);
    $pendingKeys = $job->jobPartUsages->whereIn('status', ['draft', 'approved'])->map(fn ($usage) => 'part'.$usage->id)->values();
@endphp
<section id="job-spare-parts" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7"
    x-data="{ totals: @js($previewTotals), pending: @js($pendingKeys), base: @js($job->total_job_amount), fixedPrice: @js($usesQuotedPricing || $job->invoiceJobs()->exists()), sum() { return Object.values(this.totals).reduce((sum, value) => sum + Number(value || 0), 0); }, jobTotal() { return Number(this.base) + (this.fixedPrice ? 0 : this.pending.reduce((sum, key) => sum + Number(this.totals[key] || 0), 0)); }, money(value) { return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); } }"
    x-on:part-preview="totals[$event.detail.key] = $event.detail.amount">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div><h3 class="text-sm font-bold text-slate-900">{{ __('Use Spare Parts') }}</h3><p class="mt-1 text-xs text-slate-500">{{ __('ui.stock_deducted_on_completion') }}</p></div>
    </div>
    <dl class="mb-5 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl bg-slate-50 p-4"><dt class="form-label">{{ __('ui.advance_paid') }}</dt><dd class="font-semibold text-slate-900">{{ config('business.currency') }} {{ number_format((float) $advancePaid, 2) }}</dd></div>
        <div class="rounded-xl bg-teal-50 p-4"><dt class="form-label">{{ __('ui.total_spare_parts_cost') }}</dt><dd class="font-semibold text-teal-900">{{ config('business.currency') }} <span x-text="money(sum())">{{ number_format((float) $selectedPartsTotal, 2) }}</span></dd><p class="mt-1 text-[11px] text-teal-800">{{ __('Selected quantities × selling prices; removed parts excluded.') }}</p></div>
        <div class="rounded-xl bg-blue-50 p-4"><dt class="form-label">{{ __('Total Job Amount') }}</dt><dd class="font-semibold text-blue-900">{{ config('business.currency') }} <span x-text="money(jobTotal())">{{ number_format((float) $estimatedJobTotal, 2) }}</span></dd><p class="mt-1 text-[11px] text-blue-800">{{ $usesQuotedPricing ? __('Approved quotation pricing is preserved.') : __('Includes labour and other recorded charges. Unissued parts are estimated until completion.') }}</p></div>
    </dl>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-xs">
            <thead class="border-y border-slate-200 bg-slate-50 text-slate-600"><tr><th class="px-3 py-3">{{ __('ui.part') }}</th><th class="px-3 py-3">{{ __('ui.quantity') }}</th><th class="px-3 py-3">{{ __('Price') }}</th><th class="px-3 py-3">{{ __('ui.line_total') }}</th><th class="px-3 py-3">{{ __('ui.actions') }}</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($job->jobPartUsages->sortBy('id') as $usage)
                    <tr @class(['text-slate-500' => $usage->status === 'cancelled'])>
                        <td class="px-3 py-3"><span @class(['font-semibold text-slate-800', 'line-through' => $usage->status === 'cancelled'])>{{ $usage->sparePart->name }}</span><span class="mt-1 block text-[10px] text-slate-500">{{ $usage->location->name }} · {{ $usage->status }}</span>@if ($usage->notes)<p class="mt-1 whitespace-pre-line text-slate-500">{{ $usage->notes }}</p>@endif</td>
                        <td class="px-3 py-3">{{ $usage->quantity }}</td>
                        <td class="px-3 py-3">{{ config('business.currency') }} {{ $usage->unit_price }}</td>
                        <td class="px-3 py-3 font-semibold">{{ config('business.currency') }} {{ \Brick\Math\BigDecimal::of($usage->quantity)->multipliedBy($usage->unit_price)->toScale(2, \Brick\Math\RoundingMode::HalfUp) }}</td>
                        <td class="px-3 py-3">
                            @if ($usage->status === 'draft' && auth()->user()->can('repair', $job))
                                <form method="POST" action="{{ route('stock-issues.cancel', $usage) }}">@csrf<input name="reason" type="hidden" value="Part request removed from job details"><button class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 font-semibold text-red-700">{{ __('ui.remove') }}</button></form>
                            @else
                                <span class="text-slate-500">{{ $usage->status === 'cancelled' ? __('Removed; history retained') : __('Issued stock; use Stock Return') }}</span>
                            @endif
                        </td>
                    </tr>
                    @if ($usage->status === 'draft' && auth()->user()->can('repair', $job))
                        <tr><td colspan="5" class="px-3 pb-4">
                            <details class="rounded-xl border border-slate-200 p-3">
                                <summary class="cursor-pointer font-semibold text-blue-700">{{ __('ui.update_part') }} · {{ $usage->sparePart->name }}</summary>
                                <form method="POST" action="{{ route('stock-issues.update', $usage) }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                                    x-data="{ quantity: @js((string) $usage->quantity), unitPrice: @js((string) $usage->unit_price), refreshPart() { this.unitPrice = this.$refs.partSelect.selectedOptions[0]?.dataset.price || 0; }, amount() { return (Math.round(Number(this.quantity || 0) * Number(this.unitPrice || 0) * 100) / 100).toFixed(2); }, preview() { this.$dispatch('part-preview', { key: 'part{{ $usage->id }}', amount: this.amount() }); } }">
                                    @csrf @method('PUT')
                                    <label class="form-label">{{ __('ui.part') }}<select x-ref="partSelect" name="spare_part_id" required class="form-input" data-searchable data-search-min-length="2" data-search-placeholder="{{ __('ui.search_part_hint') }}" data-search-min-message="{{ __('ui.search_type_minimum', ['count' => 2]) }}" data-search-no-results="{{ __('ui.search_no_results') }}" x-on:change="refreshPart(); preview()">@foreach ($parts as $part)<option value="{{ $part->id }}" data-price="{{ $part->selling_price }}" @selected((int) $usage->spare_part_id === $part->id)>{{ $part->sku }} · {{ $part->name }}</option>@endforeach</select></label>
                                    <label class="form-label">{{ __('ui.location') }}<select name="location_id" required class="form-input">@foreach ($locations as $location)<option value="{{ $location->id }}" @selected((int) $usage->location_id === $location->id)>{{ $location->name }}</option>@endforeach</select></label>
                                    <label class="form-label">{{ __('ui.quantity') }}<input name="quantity" type="number" min="0.001" max="999999999999.999" step="0.001" required value="{{ $usage->quantity }}" x-model="quantity" x-on:input="refreshPart(); preview()" class="form-input"></label>
                                    <label class="form-label">{{ __('ui.line_total') }}<output class="form-input block" x-text="amount()">{{ $partLineTotals[$usage->id] }}</output></label>
                                    <label class="form-label sm:col-span-2">{{ __('ui.notes') }}<input name="notes" type="text" maxlength="2000" value="{{ $usage->notes }}" class="form-input"></label>
                                    <div class="flex items-end"><button class="rounded-lg bg-blue-700 px-3 py-2.5 font-semibold text-white">{{ __('ui.update_part') }}</button></div>
                                </form>
                            </details>
                        </td></tr>
                    @endif
                @empty
                    <tr><td colspan="5" class="px-3 py-5 text-sm text-slate-500">{{ __('ui.no_parts_for_job') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @error('quantity')<p role="alert" class="mt-3 text-xs text-red-600">{{ $message }}</p>@enderror
    @error('usage')<p role="alert" class="mt-3 text-xs text-red-600">{{ $message }}</p>@enderror
    @can('repair', $job)
        @if ($parts->isNotEmpty() && $locations->isNotEmpty())
            <form method="POST" action="{{ route('stock-issues.store') }}" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2 lg:grid-cols-4"
                x-data="{ quantity: @js(old('quantity', '1.000')), unitPrice: 0, refreshPart() { this.unitPrice = this.$refs.partSelect.selectedOptions[0]?.dataset.price || 0; }, amount() { return (Math.round(Number(this.quantity || 0) * Number(this.unitPrice || 0) * 100) / 100).toFixed(2); } }" x-init="refreshPart()">
                @csrf
                <input type="hidden" name="repair_job_id" value="{{ $job->id }}">
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <label class="form-label sm:col-span-2">{{ __('ui.part') }}<select x-ref="partSelect" name="spare_part_id" required class="form-input" data-searchable data-search-min-length="2" data-search-placeholder="{{ __('ui.search_part_hint') }}" data-search-min-message="{{ __('ui.search_type_minimum', ['count' => 2]) }}" data-search-no-results="{{ __('ui.search_no_results') }}" x-on:change="refreshPart()"><option value="">{{ __('Search and select a spare part') }}</option>@foreach ($parts as $part)<option value="{{ $part->id }}" data-price="{{ $part->selling_price }}" @selected((int) old('spare_part_id') === $part->id)>{{ $part->sku }} · {{ $part->name }}</option>@endforeach</select></label>
                <label class="form-label">{{ __('ui.location') }}<select name="location_id" required class="form-input">@foreach ($locations as $location)<option value="{{ $location->id }}" @selected((int) old('location_id', $locations->first()?->id) === $location->id)>{{ $location->name }}</option>@endforeach</select></label>
                <label class="form-label">{{ __('ui.quantity') }}<input name="quantity" type="number" min="0.001" max="999999999999.999" step="0.001" required value="{{ old('quantity', '1.000') }}" x-model="quantity" class="form-input"></label>
                <label class="form-label">{{ __('Selling price') }}<output class="form-input block" x-text="Number(unitPrice).toFixed(4)">0.0000</output></label>
                <label class="form-label">{{ __('ui.line_total') }}<output class="form-input block" x-text="amount()">0.00</output></label>
                <label class="form-label sm:col-span-2">{{ __('ui.notes') }}<input name="notes" type="text" maxlength="2000" value="{{ old('notes') }}" class="form-input"></label>
                <div class="sm:col-span-2 lg:col-span-4"><button class="rounded-xl bg-emerald-700 px-4 py-2.5 text-xs font-semibold text-white">{{ __('ui.add_part_to_job') }}</button></div>
            </form>
        @endif
    @endcan
</section>
