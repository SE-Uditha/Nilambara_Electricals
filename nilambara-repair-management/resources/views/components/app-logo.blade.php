@props(['compact' => false])

<div {{ $attributes->class(['flex items-center gap-3']) }}>
    <span class="brand-mark flex size-10 shrink-0 items-center justify-center rounded-xl" aria-hidden="true">
        @if (config('business.logo'))
            <img src="{{ asset(config('business.logo')) }}" alt="" width="32" height="32" class="size-8 object-contain">
        @else
            <svg viewBox="0 0 32 32" fill="none" class="size-7">
                <path d="M9 5.5a12 12 0 1 0 14 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                <path d="m17 2-7 14h6l-1 14 8-17h-7l1-11Z" fill="currentColor" />
            </svg>
        @endif
    </span>
    @unless ($compact)
        <span class="min-w-0">
            <span class="block text-sm font-bold tracking-wide">{{ config('business.name', 'Nilambara Electricals') }}</span>
            <span class="mt-0.5 block text-[10px] font-medium tracking-[0.12em] opacity-55">{{ __('ui.repair_management') }}</span>
        </span>
    @endunless
</div>
