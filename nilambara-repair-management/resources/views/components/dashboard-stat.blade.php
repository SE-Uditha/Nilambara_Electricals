@props(['label', 'value', 'icon' => 'chart', 'tone' => 'blue', 'href' => null, 'soft' => false])
<div {{ $attributes->class(['dashboard-stat', 'dashboard-stat--'.$tone, 'dashboard-stat--soft' => $soft]) }}>
    <div class="dashboard-stat-icon"><x-icon :name="$icon" :size="24" /></div>
    <div class="min-w-0">
        <p class="dashboard-stat-label">@if ($href)<a href="{{ $href }}" class="dashboard-stat-link">{{ $label }}</a>@else{{ $label }}@endif</p>
        <p class="dashboard-stat-value">{{ $value }}</p>
    </div>
</div>
