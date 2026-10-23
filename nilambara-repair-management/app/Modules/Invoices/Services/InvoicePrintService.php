<?php

namespace App\Modules\Invoices\Services;

use App\Models\User;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\PrintTemplates\Models\PrintTemplate;
use App\Support\BusinessAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvoicePrintService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly InvoiceBalanceService $balances,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function prepare(Invoice $invoice, bool $duplicate, bool $pdf, User $user): array
    {
        $invoice->load([
            'customer',
            'cashierEmployee',
            'invoiceJobs.repairJob',
            'invoiceJobs.invoiceItems',
            'invoiceItems',
        ]);
        $template = PrintTemplate::query()
            ->where('document_type', 'invoice')
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();
        $printedAt = now(config('business.timezone', 'Asia/Colombo'));
        $duplicateLabel = $template?->duplicate_copy_label ?: __('invoices.duplicate_copy');
        $paperSize = in_array($template?->paper_size, ['A4', 'A5', 'A6'], true) ? $template->paper_size : 'A5';
        $orientation = in_array($template?->orientation, ['portrait', 'landscape'], true) ? $template->orientation : 'portrait';
        $document = $invoice->document_snapshot ?: $this->invoices->snapshot($invoice);
        // Exclude internal job classification from current and historical exports.
        unset($document['job_type']);
        foreach ($document['jobs'] ?? [] as $index => $job) {
            unset($document['jobs'][$index]['job_type']);
        }
        $balance = $this->balances->summary($invoice);
        $data = [
            'invoice' => $invoice,
            'pdfOutput' => $pdf,
            'document' => $document,
            'balance' => $balance,
            'template' => $template,
            'duplicate' => $duplicate,
            'duplicateLabel' => $duplicateLabel,
            'printedAt' => $printedAt,
            'paperSize' => $paperSize,
            'orientation' => $orientation,
            'footer' => $template?->footer ?: config('business.document_footer'),
            'businessName' => $template?->business_name ?: config('business.name'),
            'businessAddress' => $template?->business_address ?: config('business.address'),
            'businessTelephone' => $template?->telephone_number ?: config('business.contact_number'),
            'logoDataUri' => $this->logoDataUri($template?->logo_path ?: config('business.logo')),
            'fontRegularPath' => $pdf
                ? str_replace('\\', '/', public_path('fonts/NotoSansSinhala-Regular.ttf'))
                : asset('fonts/NotoSansSinhala-Regular.ttf'),
            'fontBoldPath' => $pdf
                ? str_replace('\\', '/', public_path('fonts/NotoSansSinhala-Bold.ttf'))
                : asset('fonts/NotoSansSinhala-Bold.ttf'),
            'documentTitle' => $template?->document_title ?: __('invoices.invoice'),
            'margins' => [
                'top' => $this->safeMargin($template?->margin_top_mm, 7),
                'right' => $this->safeMargin($template?->margin_right_mm, 7),
                'bottom' => $this->safeMargin($template?->margin_bottom_mm, 7),
                'left' => $this->safeMargin($template?->margin_left_mm, 7),
            ],
            'fontSize' => is_numeric($template?->font_size_pt) ? max(8, min(14, (float) $template->font_size_pt)) : 9,
        ];

        DB::transaction(function () use ($invoice, $template, $duplicate, $duplicateLabel, $printedAt, $document, $balance, $pdf, $user, $paperSize, $orientation): void {
            DocumentPrintHistory::query()->create([
                'print_template_id' => $template?->getKey(),
                'invoice_id' => $invoice->getKey(),
                'printed_at' => $printedAt,
                'printed_by_user_id' => $user->getKey(),
                'is_duplicate' => $duplicate,
                'duplicate_label' => $duplicate ? $duplicateLabel : null,
                'document_snapshot' => [
                    ...$document,
                    'payment_summary' => $balance,
                    'duplicate' => $duplicate,
                    'duplicate_label' => $duplicate ? $duplicateLabel : null,
                    'printed_at' => $printedAt->toIso8601String(),
                    'export_format' => $pdf ? 'pdf' : 'html',
                    'paper_size' => $paperSize,
                    'orientation' => $orientation,
                ],
                'output_reference' => $pdf ? $invoice->invoice_number.'.pdf' : null,
            ]);
            BusinessAudit::record(
                $user,
                $pdf ? 'invoice.pdf_exported' : ($duplicate ? 'invoice.reprinted' : 'invoice.printed'),
                Invoice::class,
                (int) $invoice->getKey(),
                newValues: [
                    'invoice_number' => $invoice->invoice_number,
                    'duplicate' => $duplicate,
                    'status' => $invoice->status->value,
                    'paper_size' => $paperSize,
                    'orientation' => $orientation,
                    'export_format' => $pdf ? 'pdf' : 'html',
                ],
            );
        });

        return $data;
    }

    public function render(array $data): View
    {
        return view('modules.invoices.print', $data);
    }

    private function safeMargin(mixed $value, float $fallback): float
    {
        return is_numeric($value) ? max(0, min(30, (float) $value)) : $fallback;
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
            throw new \RuntimeException('Unable to read the invoice logo at '.$absolute);
        }
        $mime = mime_content_type($absolute);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
            throw new \RuntimeException('The configured invoice logo must be a PNG, JPEG, or GIF image.');
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
