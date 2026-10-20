<?php

namespace App\Modules\Payments\Services;

use App\Models\User;
use App\Modules\Invoices\Services\InvoiceBalanceService;
use App\Modules\Payments\Models\Payment;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\PrintTemplates\Models\PrintTemplate;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaymentPrintService
{
    public function __construct(
        private readonly PaymentBalanceService $paymentBalances,
        private readonly InvoiceBalanceService $invoiceBalances,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function prepare(Payment $payment, bool $duplicate, bool $pdf, User $actor): array
    {
        $payment->load([
            'customer',
            'receivedByEmployee',
            'paymentAllocations.invoice',
            'paymentAllocations.repairJob',
            'paymentAllocations.posSale',
            'paymentAllocations.releases',
            'paymentReversals',
        ]);
        $template = PrintTemplate::query()
            ->where('document_type', 'payment')
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();
        $invoiceAllocation = $payment->paymentAllocations->first(fn ($allocation): bool => $allocation->target_type === 'invoice');
        $invoice = $invoiceAllocation?->invoice;
        $posSale = $payment->paymentAllocations->first(fn ($allocation): bool => $allocation->target_type === 'pos_sale')?->posSale;
        $invoiceBalance = $invoice ? $this->invoiceBalances->summary($invoice) : null;
        if ($invoice && $invoiceBalance) {
            $jobIds = $invoice->invoiceJobs()->pluck('repair_job_id');
            $appliedFromThisPayment = $payment->paymentAllocations
                ->filter(fn ($allocation): bool => ($allocation->target_type === 'invoice' && (int) $allocation->invoice_id === (int) $invoice->getKey())
                    || ($allocation->target_type === 'job_advance' && $jobIds->contains((int) $allocation->repair_job_id)))
                ->reduce(function (BigDecimal $sum, $allocation): BigDecimal {
                    $released = $allocation->releases->reduce(
                        fn (BigDecimal $value, $release): BigDecimal => $value->plus((string) $release->amount),
                        BigDecimal::zero()->toScale(2),
                    );

                    return $sum->plus(BigDecimal::of((string) $allocation->amount)->minus($released)->toScale(2));
                }, BigDecimal::zero()->toScale(2));
            $invoiceBalance['previous_paid'] = (string) BigDecimal::of($invoiceBalance['total_paid'])->minus($appliedFromThisPayment)->toScale(2);
        }
        $paymentBalance = $this->paymentBalances->summary($payment);
        $printedAt = now(config('business.timezone', 'Asia/Colombo'));
        $duplicateLabel = $template?->duplicate_copy_label ?: __('payments.duplicate_copy');
        $paperSize = in_array($template?->paper_size, ['A4', 'A5', 'A6'], true) ? $template->paper_size : 'A5';
        $orientation = in_array($template?->orientation, ['portrait', 'landscape'], true) ? $template->orientation : 'portrait';
        $reference = $payment->cheque_number ?: $payment->bank_reference;
        $data = [
            'payment' => $payment,
            'invoice' => $invoice,
            'posSale' => $posSale,
            'invoiceBalance' => $invoiceBalance,
            'paymentBalance' => $paymentBalance,
            'template' => $template,
            'duplicate' => $duplicate,
            'duplicateLabel' => $duplicateLabel,
            'printedAt' => $printedAt,
            'paperSize' => $paperSize,
            'orientation' => $orientation,
            'footer' => $template?->footer ?: config('business.document_footer'),
            'documentTitle' => $template?->document_title ?: __('payments.receipt_title'),
            'fontSize' => is_numeric($template?->font_size_pt) ? max(7, min(16, (float) $template->font_size_pt)) : 10,
            'margins' => [
                'top' => $this->safeMargin($template?->margin_top_mm, 8),
                'right' => $this->safeMargin($template?->margin_right_mm, 8),
                'bottom' => $this->safeMargin($template?->margin_bottom_mm, 8),
                'left' => $this->safeMargin($template?->margin_left_mm, 8),
            ],
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
            'reference' => $reference,
        ];

        DB::transaction(function () use ($payment, $template, $duplicate, $duplicateLabel, $printedAt, $paymentBalance, $invoiceBalance, $pdf, $actor, $paperSize, $orientation): void {
            DocumentPrintHistory::query()->create([
                'print_template_id' => $template?->getKey(),
                'payment_id' => $payment->getKey(),
                'printed_at' => $printedAt,
                'printed_by_user_id' => $actor->getKey(),
                'is_duplicate' => $duplicate,
                'duplicate_label' => $duplicate ? $duplicateLabel : null,
                'document_snapshot' => [
                    'payment_number' => $payment->payment_number,
                    'payment_amount' => (string) $payment->amount,
                    'payment_status' => $paymentBalance['status'],
                    'invoice_balance' => $invoiceBalance,
                    'duplicate' => $duplicate,
                    'duplicate_label' => $duplicate ? $duplicateLabel : null,
                    'printed_at' => $printedAt->toIso8601String(),
                    'export_format' => $pdf ? 'pdf' : 'html',
                    'paper_size' => $paperSize,
                    'orientation' => $orientation,
                ],
                'output_reference' => $pdf ? $payment->payment_number.'.pdf' : null,
            ]);
            BusinessAudit::record($actor, $pdf ? 'payment.receipt_pdf_exported' : ($duplicate ? 'payment.receipt_reprinted' : 'payment.receipt_printed'), Payment::class, (int) $payment->getKey(), newValues: [
                'payment_number' => $payment->payment_number,
                'duplicate' => $duplicate,
                'export_format' => $pdf ? 'pdf' : 'html',
                'paper_size' => $paperSize,
                'orientation' => $orientation,
            ]);
        });

        return $data;
    }

    public function render(array $data): View
    {
        return view('modules.payments.print', $data);
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
            throw new \RuntimeException('Unable to read the payment receipt logo at '.$absolute);
        }
        $mime = mime_content_type($absolute);
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
            throw new \RuntimeException('The configured payment receipt logo must be a PNG, JPEG, or GIF image.');
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function safeMargin(mixed $value, float $fallback): float
    {
        $number = is_numeric($value) ? (float) $value : $fallback;

        return max(0, min(30, $number));
    }
}
