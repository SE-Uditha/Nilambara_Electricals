@props(['icon', 'label'])
<x-icon :name="$icon" :size="15" />
<span class="sr-only">{{ $label }}</span>
