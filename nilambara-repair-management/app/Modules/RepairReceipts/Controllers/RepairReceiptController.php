<?php

namespace App\Modules\RepairReceipts\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Employees\Models\Employee;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\PrintTemplates\Models\PrintTemplate;
use App\Modules\PrintTemplates\Services\IntakePdfService;
use App\Modules\RepairReceipts\Models\RepairReceipt;
use App\Modules\RepairReceipts\Requests\StoreRepairReceiptRequest;
use App\Modules\RepairReceipts\Services\RepairReceiptService;
use App\Support\BusinessAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RepairReceiptController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('repair-jobs.index', $request->only(['q', 'date_from', 'date_to', 'status']));
    }

    public function create(): View
    {
        $selectedCustomer = null;
        if (is_numeric(old('customer_id'))) {
            $selectedCustomer = Customer::query()
                ->whereKey(old('customer_id'))
                ->where('is_active', true)
                ->first();
        }

        return view('modules.repair-jobs.create', [
            'employees' => Employee::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'selectedCustomer' => $selectedCustomer === null ? null : [
                'id' => $selectedCustomer->getKey(),
                'customer_code' => $selectedCustomer->customer_code,
                'name' => $selectedCustomer->display_name,
                'type' => $selectedCustomer->customer_type->label(),
                'contact_person' => $selectedCustomer->contact_person,
                'address' => $selectedCustomer->address,
                'mobile_number' => $selectedCustomer->mobile_number,
            ],
            'defaultReceivedById' => auth()->user()->employee?->is_active
                ? auth()->user()->employee->getKey()
                : old('received_by_employee_id'),
            'receivedAt' => now(config('business.timezone', 'Asia/Colombo')),
        ]);
    }

    public function store(StoreRepairReceiptRequest $request, RepairReceiptService $receipts): RedirectResponse
    {
        $receipt = $receipts->register($request->validated(), $request->user());

        if ($request->validated('intent') === 'print' && $request->user()->can('receipts.print')) {
            return redirect()->route('repair-jobs.receipt.print', $receipt->repair_job_id)
                ->with('status', __('ui.repair_job_accepted'));
        }

        return redirect()->route('repair-jobs.show', $receipt->repair_job_id)
            ->with('status', __('ui.repair_job_accepted'));
    }

    public function show(RepairReceipt $receipt): RedirectResponse
    {
        Gate::authorize('view', $receipt->repairJob);

        return redirect()->route('repair-jobs.show', $receipt->repair_job_id);
    }

    public function print(RepairReceipt $receipt): View
    {
        return $this->renderPrint($receipt, false);
    }

    public function duplicate(RepairReceipt $receipt): View
    {
        return $this->renderPrint($receipt, true);
    }

    public function pdf(RepairReceipt $receipt)
    {
        $duplicate = request()->boolean('duplicate');
        if ($duplicate) {
            Gate::authorize('receipts.reprint');
        }

        return app(IntakePdfService::class)->download($this->renderPrint($receipt, $duplicate, true), $receipt->receipt_number.'.pdf');
    }

    private function renderPrint(RepairReceipt $receipt, bool $duplicate, bool $pdf = false): View
    {
        $receipt->load(['repairJob.customer', 'repairJob.receivedByEmployee']);
        Gate::authorize('view', $receipt->repairJob);

        $template = PrintTemplate::query()
            ->where('document_type', 'repair_receipt')
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();
        $settings = $this->printSettings($template, 'A5');
        $snapshot = $receipt->document_snapshot ?? [];
        // Job type is internal and must not enter customer print/export data.
        unset($snapshot['job_type']);
        $label = $template?->duplicate_copy_label ?: __('ui.duplicate_copy');
        $printTime = now(config('business.timezone', 'Asia/Colombo'));

        DB::transaction(function () use ($receipt, $duplicate, $template, $snapshot, $label, $printTime, $settings, $pdf): void {
            DocumentPrintHistory::query()->create([
                'print_template_id' => $template?->getKey(),
                'repair_receipt_id' => $receipt->getKey(),
                'printed_at' => $printTime,
                'printed_by_user_id' => auth()->id(),
                'is_duplicate' => $duplicate,
                'duplicate_label' => $duplicate ? $label : null,
                'output_reference' => $pdf ? $receipt->receipt_number.'.pdf' : null,
                'document_snapshot' => [
                    ...$snapshot,
                    'duplicate' => $duplicate,
                    'duplicate_label' => $duplicate ? $label : null,
                    'printed_at' => $printTime->toIso8601String(),
                    'paper_size' => $settings['paperSize'],
                    'orientation' => $settings['orientation'],
                    'export_format' => $pdf ? 'pdf' : 'html',
                ],
            ]);
            BusinessAudit::record(
                auth()->user(),
                $pdf ? 'repair_receipt.pdf_exported' : ($duplicate ? 'repair_receipt.reprinted' : 'repair_receipt.print_prepared'),
                RepairReceipt::class,
                (int) $receipt->getKey(),
                newValues: [
                    'receipt_number' => $receipt->receipt_number,
                    'repair_job_id' => $receipt->repair_job_id,
                    'duplicate' => $duplicate,
                    'paper_size' => $settings['paperSize'],
                    'orientation' => $settings['orientation'],
                ],
            );
        });

        return view('modules.repair-receipts.print', [
            'nativeReceiptLayout' => true,
            'receipt' => $receipt,
            'snapshot' => $snapshot,
            'duplicate' => $duplicate,
            'duplicateLabel' => $label,
            'printTime' => $printTime,
            ...$settings,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function printSettings(?PrintTemplate $template, string $defaultPaper): array
    {
        $paper = $template?->paper_size ?? config('business.printing.paper', $defaultPaper);

        return [
            'paperSize' => in_array($paper, ['A5', 'A4', 'A6'], true) ? $paper : $defaultPaper,
            'orientation' => in_array($template?->orientation, ['portrait', 'landscape'], true)
                ? $template->orientation
                : 'portrait',
            'margins' => [
                'top' => $this->safeMillimetres($template?->margin_top_mm, 8),
                'right' => $this->safeMillimetres($template?->margin_right_mm, 8),
                'bottom' => $this->safeMillimetres($template?->margin_bottom_mm, 8),
                'left' => $this->safeMillimetres($template?->margin_left_mm, 8),
            ],
            'fontSize' => max(7, min(16, $this->safeMillimetres($template?->font_size_pt, 9))),
            'businessName' => $template?->business_name ?: config('business.name'),
            'businessAddress' => $template?->business_address ?: config('business.address'),
            'businessTelephone' => $template?->telephone_number ?: config('business.contact_number'),
            'logoUrl' => $template?->logo_path ? asset($template->logo_path) : asset(config('business.logo')),
            'documentTitle' => $template?->document_title ?: __('ui.repair_receipt'),
            'template' => $template,
            'footerText' => $template?->footer ?: config('business.document_footer'),
        ];
    }

    private function safeMillimetres(mixed $value, float $fallback): float
    {
        $number = is_numeric($value) ? (float) $value : $fallback;

        return max(0, min(30, $number));
    }
}
