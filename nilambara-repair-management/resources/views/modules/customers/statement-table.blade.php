<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[1000px] text-left text-xs">
            <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                <tr><th class="px-4 py-3">{{ __('ui.date') }}</th><th class="px-4 py-3">{{ __('ui.type') }}</th><th class="px-4 py-3">{{ __('ui.reference') }}</th><th class="px-4 py-3">{{ __('ui.description') }}</th><th class="px-4 py-3 text-right">{{ __('payments.advance_applied') }}</th><th class="px-4 py-3 text-right">{{ __('ui.amount') }}</th><th class="px-4 py-3 text-right">{{ __('payments.running_balance') }}</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($entries as $entry)
                    <tr class="text-slate-700">
                        <td class="whitespace-nowrap px-4 py-3">{{ $entry->occurred_at->timezone(config('business.timezone'))->format(config('business.date_format')) }}</td>
                        <td class="px-4 py-3">{{ __('payments.entry_type_'.$entry->entry_type) }}</td>
                        <td class="px-4 py-3 font-mono">{{ $entry->reference_number ?: '—' }}</td>
                        <td class="max-w-sm px-4 py-3">{{ $entry->description ?: '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ $entry->advance_applied !== '0.00' ? config('business.currency').' '.$entry->advance_applied : '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold">{{ config('business.currency') }} {{ $entry->amount }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">{{ config('business.currency') }} {{ $entry->running_balance }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-14 text-center text-sm text-slate-500">{{ __('ui.no_statement_entries') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($entries instanceof \Illuminate\Pagination\LengthAwarePaginator && $entries->hasPages())<div class="border-t border-slate-100 px-4 py-4">{{ $entries->links() }}</div>@endif
</div>
