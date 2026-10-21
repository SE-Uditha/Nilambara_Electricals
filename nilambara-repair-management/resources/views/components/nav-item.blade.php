@props(['item'])

@if (isset($item['route']))
    @php($isActive = $item['active'] ?? false)
    <a href="{{ route($item['route'], $item['route_parameters'] ?? []) }}" @class(['sidebar-link', 'is-active' => $isActive]) @if($isActive) aria-current="page" @endif>
        <x-icon :name="$item['icon']" :size="17" class="shrink-0" />
        <span class="flex-1">{{ __('ui.'.$item['label']) }}</span>
        <span class="size-1.5 rounded-full bg-teal-300" aria-hidden="true"></span>
    </a>
@else
    <button type="button" class="sidebar-link sidebar-link-disabled w-full text-left" disabled aria-disabled="true">
        <x-icon :name="$item['icon']" :size="17" class="shrink-0 opacity-70" />
        <span class="flex-1">{{ __('ui.'.$item['label']) }}</span>
        <span class="soon-label">{{ __('ui.coming_soon') }}</span>
    </button>
@endif
