@props(['item'])

@php($panelId = 'sidebar-'.str_replace('_', '-', $item['label']))
<details class="sidebar-group" data-sidebar-group="{{ $item['label'] }}" data-active="{{ $item['active'] ? 'true' : 'false' }}" @if($item['active']) open @endif>
    <summary class="sidebar-link sidebar-toggle {{ $item['active'] ? 'has-active-child' : '' }}" aria-controls="{{ $panelId }}" aria-expanded="{{ $item['active'] ? 'true' : 'false' }}">
        <x-icon :name="$item['icon']" :size="17" class="shrink-0" />
        <span class="flex-1">{{ __('ui.'.$item['label']) }}</span>
        <svg class="sidebar-chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m7 4 6 6-6 6" /></svg>
    </summary>
    <div id="{{ $panelId }}" class="sidebar-submenu">
        @foreach ($item['children'] as $child)
            <x-nav-item :item="$child" />
        @endforeach
    </div>
</details>
