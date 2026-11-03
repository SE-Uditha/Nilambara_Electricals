@props(['status', 'label' => null])
@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $tone = match (strtolower($value)) {
        'repair completed', 'ready for collection', 'delivered', 'approved', 'paid', 'completed', 'issued', 'active' => 'green',
        'cancelled', 'rejected', 'unable to repair', 'failed', 'reversed', 'customer contact failed', 'customer contact failed after ready' => 'red',
        'received', 'under inspection', 'under repair', 'partially_paid', 'partially paid' => 'blue',
        'draft', 'inactive' => 'slate',
        default => 'amber',
    };
@endphp
<span {{ $attributes->class(['status-badge', 'status-badge--'.$tone]) }}><span class="status-badge-dot" aria-hidden="true"></span>{{ $label ?? $value }}</span>
