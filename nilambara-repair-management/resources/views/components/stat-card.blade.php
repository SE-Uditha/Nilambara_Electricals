@props(['label', 'icon', 'tone' => 'teal'])

<article class="stat-card rounded-2xl border border-slate-200/80 bg-white p-5 xl:p-6">
    <div class="flex items-start justify-between gap-3">
        <h3 class="pt-1 text-[13px] font-semibold text-slate-600">{{ $label }}</h3>
        <span @class(['stat-icon flex size-10 shrink-0 items-center justify-center rounded-xl', 'tone-'.$tone])>
            <x-icon :name="$icon" :size="20" />
        </span>
    </div>
    <p class="mt-3 text-[34px] leading-none font-semibold tracking-tight text-slate-800"><span aria-hidden="true">—</span><span class="sr-only">{{ __('ui.not_available') }}</span></p>
    <div class="mt-4 flex items-center gap-2 text-[11px] text-slate-400">
        <span class="inline-block size-1.5 rounded-full bg-slate-300" aria-hidden="true"></span>
        {{ __('ui.stat_placeholder') }}
    </div>
</article>
