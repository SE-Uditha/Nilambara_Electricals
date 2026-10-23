@if (($billingJobs ?? collect())->isNotEmpty())
    <section class="mb-6 overflow-hidden rounded-2xl border border-amber-200 bg-white">
        <div class="border-b border-amber-100 bg-amber-50 px-5 py-4">
            <h2 class="text-sm font-bold text-amber-900">{{ $billingQueueTitle ?? 'Jobs awaiting billing' }}</h2>
            <p class="mt-1 text-xs text-amber-800">{{ $billingQueueHelp ?? 'Completed, ready, unreachable, unrepairable and cancelled jobs without an invoice. Add parts used, labour and custom charges, then save the bill.' }}</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                    <tr><th class="px-4 py-3">Job</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Item</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Bill total</th><th class="px-4 py-3">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($billingJobs as $billingJob)
                        <tr class="text-slate-700">
                            <td class="px-4 py-3 font-mono font-semibold"><a href="{{ route('repair-jobs.show', $billingJob) }}" class="text-teal-800 hover:underline">{{ $billingJob->automatic_number ?: $billingJob->job_number }}</a></td>
                            <td class="px-4 py-3">{{ $billingJob->customer?->display_name }}</td>
                            <td class="px-4 py-3">{{ $billingJob->item_name }}</td>
                            <td class="px-4 py-3">{{ $billingJob->status->value }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ config('business.currency') }} {{ $billingJob->total_job_amount }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('repair-jobs.billing', $billingJob) }}" class="font-semibold text-teal-800 hover:underline">Prepare bill</a>
                                @if ((float) $billingJob->total_job_amount > 0)
                                    <a href="{{ route('invoices.create', ['customer_id' => $billingJob->customer_id, 'repair_job_ids' => [$billingJob->id]]) }}" class="ml-3 text-slate-600 hover:underline">Create invoice</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
