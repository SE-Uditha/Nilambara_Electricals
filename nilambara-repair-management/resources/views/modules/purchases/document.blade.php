<!doctype html><html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><title>{{ $documentTitle }} · {{ $number }}</title><style>
@page { size:{{ $paperSize }} {{ $orientation }};margin:{{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm; }
body{font:{{ $fontSize }}pt Arial,sans-serif;color:#172033}.document{max-width:190mm;margin:auto}.business{border-bottom:2px solid #0f766e;padding-bottom:12px}.business img{max-width:22mm;max-height:18mm}.business h1{font-size:16pt;margin:0}.business p{margin:3px 0}table{width:100%;border-collapse:collapse;margin-top:16px}th,td{padding:7px 4px;border-bottom:1px solid #d9e0e5;text-align:left}.number{text-align:right}.signatures{margin-top:35px}.signatures span{display:inline-block;width:45%;border-top:1px solid #64748b;text-align:center;padding-top:5px}.screen-actions{margin:12px;text-align:right}.screen-actions a,.screen-actions button{margin-left:12px}tr{page-break-inside:avoid}@media print{.screen-actions{display:none}}
</style></head><body><nav class="screen-actions"><button onclick="window.print()">Print</button>
@php($routeName=$grn ? 'grns.document' : 'purchases.document')
@php($parameters=$grn ? ['grn'=>$grn] : ['purchase'=>$purchase])
<a href="{{ route($routeName,$parameters+['format'=>'pdf','duplicate'=>$duplicate ? 1 : 0]) }}">PDF</a><a href="{{ route($routeName,$parameters+['format'=>'html','duplicate'=>1]) }}">Duplicate copy</a></nav>
<main class="document"><header class="business"><img src="{{ $logoUrl }}" alt=""><h1>{{ $businessName }}</h1><p>{{ $businessAddress }}</p><p>{{ $businessTelephone }}</p></header>
@if($duplicate)<p style="text-align:center;font-weight:bold">{{ $duplicateLabel }}</p>@endif
<h2>{{ $documentTitle }}</h2><p><strong>{{ $number }}</strong> · {{ $grn ? $grn->received_at->format('d M Y H:i') : $purchase->purchase_date->format('d M Y') }} · {{ $purchase->status->value }}</p><p>Supplier: {{ $purchase->supplier->name }} · {{ $purchase->supplier->supplier_code }}</p><p>{{ $purchase->supplier->address }} · {{ $purchase->supplier->mobile_number }}</p><p>Supplier invoice: {{ $purchase->supplier_invoice_number }} · Purchase: {{ $purchase->purchase_number }}</p>
<table><thead><tr><th>Description</th><th class="number">Quantity</th><th class="number">Unit cost (LKR)</th><th class="number">Line total (LKR)</th></tr></thead><tbody>
@foreach($lines as $line)<tr><td>{{ $line['description'] }}</td><td class="number">{{ $line['quantity'] }}</td><td class="number">{{ $line['unit_cost'] }}</td><td class="number">{{ $line['line_total'] }}</td></tr>@endforeach
</tbody></table>
@unless($grn)<p>Subtotal: LKR {{ $purchase->subtotal }} · Discount: LKR {{ $purchase->discount_amount }} · Other costs: LKR {{ $purchase->other_costs }}</p><p><strong>Total: LKR {{ $purchase->total }}</strong> · Terms: {{ $purchase->payment_terms->value }} · Due: {{ $purchase->due_date?->format('d M Y') ?: '—' }}</p>@endunless
<p>{{ $grn?->notes ?: $purchase->notes }}</p><div class="signatures"><span>Prepared / received by</span> <span>Authorized by</span></div><p>{{ $footerText }}</p><p>Printed: {{ $printedAt->format('d M Y H:i') }} · {{ config('business.timezone') }}</p></main>
@include('components.document-layout-settings')
</body></html>
