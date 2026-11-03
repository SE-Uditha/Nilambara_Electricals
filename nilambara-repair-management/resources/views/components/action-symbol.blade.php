@props(['icon', 'label'])
<x-icon :name="$icon" :size="18" />
<span class="sr-only">{{ $label }}</span>
