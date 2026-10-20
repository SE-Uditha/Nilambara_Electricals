@if (isset($template) && $template)
<style>
    @foreach (($template->table_widths ?? []) as $index => $width)
        @if (is_numeric($index) && is_numeric($width) && $width >= 1 && $width <= 100)
        table:not(.totals) th:nth-child({{ (int)$index + 1 }}), table:not(.totals) td:nth-child({{ (int)$index + 1 }}) { width: {{ $width }}%; }
        @endif
    @endforeach
</style>
@if (is_array($template->signature_areas) && count($template->signature_areas))
<div style="margin-top:24px;page-break-inside:avoid">@foreach ($template->signature_areas as $label)<div style="display:inline-block;width:30%;margin:12px 1%;border-top:1px solid #64748b;padding-top:5px;text-align:center">{{ is_string($label) ? $label : '' }}</div>@endforeach</div>
@endif
@endif
