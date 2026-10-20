<?php

namespace App\Modules\POS\Services;

use App\Enums\DocumentStatus;
use App\Models\User;
use App\Modules\POS\Models\PosSale;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\PrintTemplates\Models\PrintTemplate;
use App\Support\BusinessAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class POSReceiptService
{
    public function prepare(PosSale $sale, bool $duplicate, bool $pdf, User $user, ?string $paper = null): array
    {
        Gate::forUser($user)->authorize('print', $sale);
        abort_unless($sale->status === DocumentStatus::Finalized, 404);
        $sale->load(['customer', 'cashierEmployee', 'items.sparePart', 'paymentAllocations.payment']);
        $duplicate = $duplicate || DocumentPrintHistory::query()->where('pos_sale_id', $sale->getKey())->exists();
        $template = PrintTemplate::query()->where('document_type', 'pos_receipt')
            ->where('is_active', true)->orderByDesc('version')->first();
        $paper ??= $template?->paper_size ?: 'A5';
        $printedAt = now(config('business.timezone', 'Asia/Colombo'));
        $duplicateLabel = $template?->duplicate_copy_label ?: __('pos.duplicate_copy');
        $snapshot = $sale->document_snapshot ?: [];
        $payment = $sale->paymentAllocations->first()?->payment;
        $thermalWidth = max(58, min(110, (float) ($template?->thermal_width_mm ?: 80)));
        $data = [
            'sale' => $sale,
            'snapshot' => $snapshot,
            'payment' => $payment,
            'template' => $template,
            'duplicate' => $duplicate,
            'duplicateLabel' => $duplicateLabel,
            'printedAt' => $printedAt,
            'businessName' => $template?->business_name ?: config('business.name'),
            'businessAddress' => $template?->business_address ?: config('business.address'),
            'businessTelephone' => $template?->telephone_number ?: config('business.contact_number'),
            'logoDataUri' => $this->logoDataUri($template?->logo_path ?: config('business.logo')),
            'footer' => $template?->footer ?: config('business.document_footer'),
            'paperSize' => $paper,
            'orientation' => in_array($template?->orientation, ['portrait', 'landscape'], true) ? $template->orientation : 'portrait',
            'documentTitle' => $template?->document_title ?: __('pos.receipt_title'),
            'margins' => [
                'top' => $this->safeMargin($template?->margin_top_mm, $paper === 'thermal' ? 3 : 8),
                'right' => $this->safeMargin($template?->margin_right_mm, $paper === 'thermal' ? 3 : 8),
                'bottom' => $this->safeMargin($template?->margin_bottom_mm, $paper === 'thermal' ? 3 : 8),
                'left' => $this->safeMargin($template?->margin_left_mm, $paper === 'thermal' ? 3 : 8),
            ],
            'thermalWidth' => $thermalWidth,
            'fontSize' => is_numeric($template?->font_size_pt) ? max(8, min(14, (float) $template->font_size_pt)) : 9,
        ];

        DB::transaction(function () use ($sale, $template, $duplicate, $duplicateLabel, $printedAt, $snapshot, $paper, $pdf, $user): void {
            DocumentPrintHistory::query()->create([
                'print_template_id' => $template?->getKey(),
                'pos_sale_id' => $sale->getKey(),
                'customer_id' => $sale->customer_id,
                'printed_at' => $printedAt,
                'printed_by_user_id' => $user->getKey(),
                'is_duplicate' => $duplicate,
                'duplicate_label' => $duplicate ? $duplicateLabel : null,
                'document_snapshot' => [
                    ...$snapshot,
                    'duplicate' => $duplicate,
                    'duplicate_label' => $duplicate ? $duplicateLabel : null,
                    'printed_at' => $printedAt->toIso8601String(),
                    'export_format' => $pdf ? 'pdf' : 'html',
                    'paper_size' => $paper,
                ],
                'output_reference' => $pdf ? $sale->sale_number.'-receipt.pdf' : null,
            ]);
            BusinessAudit::record($user, $pdf ? 'pos.receipt.pdf_exported' : ($duplicate ? 'pos.receipt.reprinted' : 'pos.receipt.printed'), PosSale::class, (int) $sale->getKey(), newValues: [
                'sale_number' => $sale->sale_number,
                'duplicate' => $duplicate,
                'paper_size' => $paper,
                'export_format' => $pdf ? 'pdf' : 'html',
            ]);
        });

        return $data;
    }

    public function render(array $data): View
    {
        return view('modules.pos.receipt', $data);
    }

    private function logoDataUri(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        $absolute = public_path(ltrim($path, '/\\'));
        if (! is_file($absolute)) {
            return null;
        }
        $contents = file_get_contents($absolute);
        if ($contents === false) {
            throw new \RuntimeException('Unable to read the POS receipt logo at '.$absolute);
        }
        $mime = mime_content_type($absolute);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
            throw new \RuntimeException('The configured POS receipt logo must be a PNG, JPEG, or GIF image.');
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function safeMargin(mixed $value, float $fallback): float
    {
        $number = is_numeric($value) ? (float) $value : $fallback;

        return max(0, min(30, $number));
    }
}
