<div>
    <section class="foundation-banner mb-7 overflow-hidden rounded-2xl px-6 py-6 sm:px-7" aria-labelledby="foundation-title">
        <div class="relative z-1 flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-4">
                <span class="mt-0.5 hidden size-11 shrink-0 items-center justify-center rounded-xl border border-white/15 bg-white/8 text-teal-200 sm:flex"><x-icon name="tool" :size="23" /></span>
                <div>
                    <p class="text-[9px] font-semibold tracking-[0.16em] text-teal-300 uppercase">{{ config('business.name', 'Nilambara Electricals') }}</p>
                    <h2 id="foundation-title" class="mt-1.5 text-lg font-semibold tracking-tight text-white">{{ __('ui.workshop_at_glance') }}</h2>
                    <p class="mt-1.5 max-w-xl text-xs leading-5 text-slate-300">{{ __('ui.workshop_overview_description') }}</p>
                </div>
            </div>
        </div>
    </section>

    <section aria-labelledby="business-snapshot-title">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 id="business-snapshot-title" class="text-sm font-semibold text-slate-800">{{ __('ui.business_snapshot') }}</h2>
            <span class="flex items-center gap-1.5 text-[10px] text-slate-400"><x-icon name="info" :size="13" />{{ __('ui.placeholder_metrics') }}</span>
        </div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($statistics as $statistic)
                <x-stat-card :label="__('ui.'.$statistic['label'])" :icon="$statistic['icon']" :tone="$statistic['tone']" />
            @endforeach
        </div>
    </section>

    <div class="mt-7 grid gap-5 xl:grid-cols-[1.55fr_1fr]">
        <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white" aria-labelledby="repair-activity-title">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-6 py-5">
                <div class="flex items-center gap-2.5"><x-icon name="activity" :size="17" class="text-slate-400" /><h2 id="repair-activity-title" class="text-sm font-semibold text-slate-800">{{ __('ui.repair_activity') }}</h2></div>
                <span class="rounded-md bg-slate-100 px-2 py-1 text-[9px] font-medium text-slate-500">{{ __('ui.coming_soon') }}</span>
            </div>
            <div class="flex min-h-[260px] flex-col items-center justify-center px-6 py-9 text-center">
                <div class="empty-state-icon relative mb-5 flex size-16 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-400"><x-icon name="clipboard" :size="29" /><span class="absolute -right-1 -bottom-1 flex size-6 items-center justify-center rounded-lg border-2 border-white bg-teal-50 text-teal-600"><x-icon name="tool" :size="12" /></span></div>
                <h3 class="text-[13px] font-semibold text-slate-700">{{ __('ui.activity_empty_title') }}</h3>
                <p class="mt-2 max-w-sm text-xs leading-5 text-slate-400">{{ __('ui.activity_empty_description') }}</p>
                <span class="mt-5 rounded-full border border-dashed border-slate-200 px-3 py-1.5 text-[9px] font-medium tracking-wide text-slate-400">{{ __('ui.no_business_records') }}</span>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white" aria-labelledby="workspace-details-title">
            <div class="flex items-center gap-2.5 border-b border-slate-100 px-6 py-5"><x-icon name="settings" :size="17" class="text-slate-400" /><h2 id="workspace-details-title" class="text-sm font-semibold text-slate-800">{{ __('ui.workspace_details') }}</h2></div>
            <dl class="space-y-5 px-6 py-6">
                @foreach ([['wallet', 'currency', config('business.currency', 'LKR')], ['clock', 'timezone', config('business.timezone', 'Asia/Colombo')], ['globe', 'language', __('ui.language_'.app()->getLocale())], ['badge', 'signed_in_as', auth()->user()->name]] as [$icon, $heading, $detail])
                    <div class="flex items-start gap-3">
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-500"><x-icon :name="$icon" :size="15" /></span>
                        <div class="min-w-0 flex-1"><dt class="text-[10px] text-slate-400">{{ __('ui.'.$heading) }}</dt><dd class="mt-1 truncate text-[11px] font-semibold text-slate-700">{{ $detail }}</dd></div>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>
</div>
