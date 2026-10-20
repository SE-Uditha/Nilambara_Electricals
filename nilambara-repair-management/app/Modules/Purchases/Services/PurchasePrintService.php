<?php

namespace App\Modules\Purchases\Services;

use App\Models\User;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\PrintTemplates\Models\PrintTemplate;
use App\Modules\Purchases\Models\GoodsReceivedNote;
use App\Modules\Purchases\Models\Purchase;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PurchasePrintService
{
    public function prepare(Purchase $purchase, ?GoodsReceivedNote $grn, bool $duplicate, bool $pdf, User $actor): array
    {
        Gate::forUser($actor)->authorize('purchases.view');
        $purchase->load(['supplier', 'items']);
        $template = PrintTemplate::where('document_type', 'purchase')->where('is_active', true)->orderByDesc('version')->first();
        if ($grn) {
            abort_unless((int) $grn->purchase_id === (int) $purchase->id, 404);
            $grn->load('items.purchaseItem');
            $lines = $grn->items->map(fn ($line) => ['description' => $line->purchaseItem->description, 'quantity' => $line->quantity, 'unit_cost' => $line->unit_cost, 'line_total' => (string) BigDecimal::of($line->quantity)->multipliedBy($line->unit_cost)->toScale(2, RoundingMode::HalfUp)])->all();
        } else {
            $lines = $purchase->items->map(fn ($line) => $line->only(['description', 'quantity', 'unit_cost', 'line_total']))->all();
        }
        $paperSize = in_array($template?->paper_size, ['A4', 'A5', 'A6'], true) ? $template->paper_size : 'A4';
        $orientation = in_array($template?->orientation, ['portrait', 'landscape'], true) ? $template->orientation : 'portrait';
        $number = $grn?->grn_number ?: $purchase->purchase_number;
        $documentTitle = $grn ? 'Goods received note' : ($template?->document_title ?: 'Supplier purchase');
        $duplicateLabel = $template?->duplicate_copy_label ?: 'DUPLICATE COPY';
        $data = compact('purchase', 'grn', 'template', 'lines', 'paperSize', 'orientation', 'number', 'documentTitle', 'duplicate', 'duplicateLabel');
        $data += ['printedAt' => now(), 'businessName' => $template?->business_name ?: config('business.name'), 'businessAddress' => $template?->business_address ?: config('business.address'), 'businessTelephone' => $template?->telephone_number ?: config('business.contact_number'), 'logoUrl' => asset($template?->logo_path ?: config('business.logo')), 'footerText' => $template?->footer ?: config('business.document_footer'), 'fontSize' => is_numeric($template?->font_size_pt) ? max(7, min(14, (float) $template->font_size_pt)) : 9, 'margins' => []];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $data['margins'][$side] = max(0, min(25, (float) ($template?->{'margin_'.$side.'_mm'} ?? 8)));
        }
        DB::transaction(function () use ($data, $purchase, $actor, $duplicate, $pdf, $number, $grn, $lines, $template, $paperSize, $orientation, $duplicateLabel) {
            DocumentPrintHistory::create(['print_template_id' => $template?->id, 'purchase_id' => $purchase->id, 'printed_at' => $data['printedAt'], 'printed_by_user_id' => $actor->id, 'is_duplicate' => $duplicate, 'duplicate_label' => $duplicate ? $duplicateLabel : null, 'output_reference' => $pdf ? $number.'.pdf' : null, 'document_snapshot' => ['document_number' => $number, 'document_type' => $grn ? 'grn' : 'purchase', 'purchase_number' => $purchase->purchase_number, 'supplier' => $purchase->supplier->only(['supplier_code', 'name', 'address', 'mobile_number']), 'lines' => $lines, 'total' => $purchase->total, 'paper_size' => $paperSize, 'orientation' => $orientation, 'export_format' => $pdf ? 'pdf' : 'html']]);
            BusinessAudit::record($actor, $pdf ? 'purchase.document.pdf_exported' : 'purchase.document.print_prepared', Purchase::class, $purchase->id, newValues: ['document_number' => $number, 'duplicate' => $duplicate]);
        });

        return $data;
    }
}
