<?php

namespace App\Modules\Quotations\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\QuotationPreparationStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\PrintTemplates\Models\PrintTemplate;
use App\Modules\Quotations\Models\Quotation;
use App\Modules\Quotations\Requests\RecordQuotationDecisionRequest;
use App\Modules\Quotations\Requests\StoreQuotationRequest;
use App\Modules\Quotations\Requests\UpdateQuotationRequest;
use App\Modules\Quotations\Services\QuotationApprovalService;
use App\Modules\Quotations\Services\QuotationService;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Support\BusinessAudit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Quotation::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'approval_status' => ['nullable', Rule::enum(ApprovalStatus::class)],
            'preparation_status' => ['nullable', Rule::enum(QuotationPreparationStatus::class)],
        ]);
        $query = Quotation::query()->with(['repairJob.customer', 'createdByUser'])
            ->orderByDesc('quotation_date')->orderByDesc('id');

        if (($search = trim((string) ($filters['q'] ?? ''))) !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('quotation_number', 'like', $like)
                    ->orWhereHas('repairJob', function ($job) use ($like): void {
                        $job->where('job_number', 'like', $like)
                            ->orWhere('automatic_number', 'like', $like)
                            ->orWhere('item_name', 'like', $like)
                            ->orWhereHas('customer', function ($customer) use ($like): void {
                                $customer->where('customer_name', 'like', $like)
                                    ->orWhere('company_name', 'like', $like)
                                    ->orWhere('mobile_number', 'like', $like);
                            });
                    });
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('quotation_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('quotation_date', '<=', $filters['date_to']);
        }
        if (! empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }
        if (! empty($filters['preparation_status'])) {
            $query->where('preparation_status', $filters['preparation_status']);
        }
        $this->restrictToVisibleJobs($query);
        if (auth()->user()->hasRole('cashier') && ! auth()->user()->hasAnyRole(['administrator', 'manager'])) {
            $query->where('approval_status', ApprovalStatus::Approved->value)
                ->where('preparation_status', QuotationPreparationStatus::Issued->value);
        }

        return view('modules.quotations.index', [
            'quotations' => $query->paginate(20)->withQueryString(),
            'approvalStatuses' => ApprovalStatus::cases(),
            'preparationStatuses' => QuotationPreparationStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Quotation::class);
        $job = null;
        if ($request->filled('repair_job_id')) {
            $job = RepairJob::query()->with(['customer', 'jobInspections'])
                ->findOrFail($request->integer('repair_job_id'));
            Gate::authorize('view', $job);
        }

        return view('modules.quotations.create', [
            'job' => $job,
            'quotation' => null,
            'items' => [['description' => '', 'quantity' => '1', 'unit_price' => '0.00']],
            'quotationDate' => now(config('business.timezone', 'Asia/Colombo'))->toDateString(),
            'validUntil' => now(config('business.timezone', 'Asia/Colombo'))->addDays(14)->toDateString(),
        ]);
    }

    public function store(StoreQuotationRequest $request, QuotationService $quotations): RedirectResponse
    {
        if ($request->validated('intent') === 'issue' && ! $request->user()->can('quotations.issue')) {
            abort(403);
        }
        if ($request->validated('intent') === 'preview' && ! $request->user()->can('quotations.print')) {
            abort(403);
        }
        $job = RepairJob::query()->findOrFail($request->validated('repair_job_id'));
        Gate::authorize('view', $job);
        $quotation = $quotations->create($request->validated(), $request->user());

        if ($request->validated('intent') === 'preview') {
            return redirect()->route('quotations.print', $quotation);
        }

        return redirect()->route('quotations.show', $quotation)
            ->with('status', __('ui.quotation_saved'));
    }

    public function show(Quotation $quotation): View
    {
        Gate::authorize('view', $quotation);
        $quotation->load([
            'customer',
            'repairJob.customer',
            'repairJob.jobInspections',
            'createdByUser',
            'updatedByUser',
            'items' => fn ($query) => $query->whereNull('removed_at')->orderBy('display_order')->orderBy('line_number'),
            'approvals.recordedByUser',
            'history.actor',
            'revisesQuotation',
            'revisions',
        ]);

        return view('modules.quotations.show', ['quotation' => $quotation]);
    }

    public function edit(Quotation $quotation): View
    {
        Gate::authorize('update', $quotation);
        $quotation->load([
            'customer',
            'repairJob.customer',
            'repairJob.jobInspections',
            'items' => fn ($query) => $query->whereNull('removed_at')->orderBy('display_order')->orderBy('line_number'),
        ]);

        return view('modules.quotations.create', [
            'job' => $quotation->repairJob,
            'quotation' => $quotation,
            'items' => $quotation->items->map(fn ($item): array => [
                'spare_part_id' => $item->spare_part_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])->all(),
            'quotationDate' => $quotation->quotation_date->toDateString(),
            'validUntil' => $quotation->valid_until->toDateString(),
        ]);
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation, QuotationService $quotations): RedirectResponse
    {
        if ($request->validated('intent') === 'preview' && ! $request->user()->can('quotations.print')) {
            abort(403);
        }
        $updated = $quotations->updateDraft($quotation, $request->validated(), $request->user());

        if ($request->validated('intent') === 'preview') {
            return redirect()->route('quotations.print', $updated);
        }

        return redirect()->route('quotations.show', $updated)->with('status', __('ui.quotation_saved'));
    }

    public function issue(Request $request, Quotation $quotation, QuotationService $quotations): RedirectResponse
    {
        Gate::authorize('issue', $quotation);
        $issued = $quotations->issue($quotation, $request->user());

        return redirect()->route('quotations.show', $issued)->with('status', __('ui.quotation_issued'));
    }

    public function decision(
        RecordQuotationDecisionRequest $request,
        Quotation $quotation,
        QuotationApprovalService $approvals,
    ): RedirectResponse {
        $updated = $approvals->recordDecision($quotation, $request->validated(), $request->user());

        return redirect()->route('quotations.show', $updated)->with('status', __('ui.quotation_decision_saved'));
    }

    public function revise(Request $request, Quotation $quotation, QuotationService $quotations): RedirectResponse
    {
        Gate::authorize('revise', $quotation);
        $revision = $quotations->createRevision($quotation, $request->user());

        return redirect()->route('quotations.edit', $revision)->with('status', __('ui.quotation_revision_created'));
    }

    public function cancel(Request $request, Quotation $quotation, QuotationService $quotations): RedirectResponse
    {
        Gate::authorize('cancel', $quotation);
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        $cancelled = $quotations->cancel($quotation, $request->user(), $data['notes'] ?? null);

        return redirect()->route('quotations.show', $cancelled)->with('status', __('ui.quotation_cancelled'));
    }

    public function print(Request $request, Quotation $quotation)
    {
        $view = $this->preparePrint($request, $quotation, false, false);
        $response = response($view->render());
        $this->recordPrint($quotation, $view->getData(), false, false, $request->user());

        return $response;
    }

    public function duplicate(Request $request, Quotation $quotation)
    {
        $view = $this->preparePrint($request, $quotation, true, false);
        $response = response($view->render());
        $this->recordPrint($quotation, $view->getData(), true, false, $request->user());

        return $response;
    }

    public function pdf(Request $request, Quotation $quotation)
    {
        $viewData = $this->preparePrint($request, $quotation, false, true)->getData();
        $paper = $viewData['paperSize'];
        $response = Pdf::loadView('modules.quotations.print', $viewData)
            ->setPaper($paper, $viewData['orientation'])
            ->download($quotation->quotation_number.'.pdf');
        $this->recordPrint($quotation, $viewData, false, true, $request->user());

        return $response;
    }

    public function repairJobs(Request $request)
    {
        Gate::authorize('create', Quotation::class);
        $term = trim((string) $request->query('q', ''));
        $jobs = RepairJob::query()->with('customer')
            ->where(function ($query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where('automatic_number', 'like', $like)
                    ->orWhere('job_number', 'like', $like)
                    ->orWhere('item_name', 'like', $like)
                    ->orWhereHas('customer', function ($customer) use ($like): void {
                        $customer->where('customer_name', 'like', $like)
                            ->orWhere('company_name', 'like', $like)
                            ->orWhere('mobile_number', 'like', $like);
                    });
            })
            ->whereNull('delivered_at')
            ->where('status', '!=', 'Cancelled')
            ->orderByDesc('received_at')
            ->limit(10)
            ->get()
            ->filter(fn (RepairJob $job): bool => Gate::allows('view', $job))
            ->map(fn (RepairJob $job): array => [
                'id' => $job->getKey(),
                'automatic_number' => $job->automatic_number,
                'job_number' => $job->job_number,
                'customer' => $job->customer->display_name,
                'customer_code' => $job->customer->customer_code,
                'address' => $job->customer->address,
                'mobile_number' => $job->customer->mobile_number,
                'item_name' => $job->item_name,
                'item_description' => $job->item_description,
                'reported_fault' => $job->reported_fault,
                'brand' => $job->brand,
                'model' => $job->model,
                'inspection_summary' => $job->jobInspections()->latest('inspected_at')->value('inspection_result'),
            ])->values();

        return response()->json($jobs);
    }

    public function spareParts(Request $request)
    {
        Gate::authorize('create', Quotation::class);
        $term = trim((string) $request->query('q', ''));
        $like = '%'.$term.'%';

        return response()->json(SparePart::query()->where('is_active', true)
            ->where(function ($query) use ($like): void {
                $query->where('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('name', 'like', $like);
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'sku', 'barcode', 'name', 'selling_price'])
            ->map(fn (SparePart $part): array => [
                'id' => $part->getKey(),
                'sku' => $part->sku,
                'barcode' => $part->barcode,
                'name' => $part->name,
                'selling_price' => (string) $part->selling_price,
            ]));
    }

    private function preparePrint(Request $request, Quotation $quotation, bool $duplicate, bool $pdf): View
    {
        Gate::authorize('print', $quotation);
        $quotation->load([
            'repairJob.customer',
            'customer',
            'createdByUser',
            'items' => fn ($query) => $query->whereNull('removed_at')->orderBy('display_order')->orderBy('line_number'),
        ]);
        $template = PrintTemplate::query()
            ->where('document_type', 'quotation')
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();
        $paper = $request->query('paper', $template?->paper_size ?: 'A4');
        if (! in_array($paper, ['A4', 'A5'], true)) {
            $paper = 'A4';
        }
        $orientation = in_array($template?->orientation, ['portrait', 'landscape'], true) ? $template->orientation : 'portrait';
        $label = $template?->duplicate_copy_label ?: __('ui.duplicate_copy');
        $printedAt = now(config('business.timezone', 'Asia/Colombo'));
        $snapshot = $quotation->preparation_status === QuotationPreparationStatus::Draft
            ? $this->draftSnapshot($quotation)
            : ($quotation->document_snapshot ?? []);

        return view('modules.quotations.print', [
            'quotation' => $quotation,
            'snapshot' => $snapshot,
            'template' => $template,
            'paperSize' => $paper,
            'orientation' => $orientation,
            'footerText' => $template?->footer ?: config('business.document_footer'),
            'duplicate' => $duplicate,
            'duplicateLabel' => $label,
            'printedAt' => $printedAt,
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
            'margins' => [
                'top' => $this->safeMargin($template?->margin_top_mm, 8),
                'right' => $this->safeMargin($template?->margin_right_mm, 8),
                'bottom' => $this->safeMargin($template?->margin_bottom_mm, 8),
                'left' => $this->safeMargin($template?->margin_left_mm, 8),
            ],
            'fontSize' => is_numeric($template?->font_size_pt) ? max(8, min(14, (float) $template->font_size_pt)) : 10,
            'documentTitle' => $template?->document_title ?: __('ui.repair_quotation'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $viewData
     */
    private function recordPrint(
        Quotation $quotation,
        array $viewData,
        bool $duplicate,
        bool $pdf,
        User $user,
    ): void {
        $template = $viewData['template'];
        $snapshot = $viewData['snapshot'];
        $printedAt = $viewData['printedAt'];
        $label = $viewData['duplicateLabel'];
        $paper = $viewData['paperSize'];
        $orientation = $viewData['orientation'];

        DB::transaction(function () use ($quotation, $template, $duplicate, $label, $printedAt, $snapshot, $pdf, $paper, $orientation, $user): void {
            DocumentPrintHistory::query()->create([
                'print_template_id' => $template?->getKey(),
                'quotation_id' => $quotation->getKey(),
                'printed_at' => $printedAt,
                'printed_by_user_id' => $user->getKey(),
                'is_duplicate' => $duplicate,
                'duplicate_label' => $duplicate ? $label : null,
                'document_snapshot' => [
                    ...$snapshot,
                    'duplicate' => $duplicate,
                    'duplicate_label' => $duplicate ? $label : null,
                    'printed_at' => $printedAt->toIso8601String(),
                    'export_format' => $pdf ? 'pdf' : 'html',
                    'paper_size' => $paper,
                    'orientation' => $orientation,
                ],
                'output_reference' => $pdf ? $quotation->quotation_number.'.pdf' : null,
            ]);
            BusinessAudit::record(
                $user,
                $pdf ? 'quotation.pdf_exported' : ($duplicate ? 'quotation.reprinted' : 'quotation.printed'),
                Quotation::class,
                (int) $quotation->getKey(),
                newValues: [
                    'quotation_number' => $quotation->quotation_number,
                    'revision' => $quotation->revision,
                    'duplicate' => $duplicate,
                    'preparation_status' => $quotation->preparation_status->value,
                    'paper_size' => $paper,
                    'orientation' => $orientation,
                    'export_format' => $pdf ? 'pdf' : 'html',
                ],
            );
        });
    }

    private function draftSnapshot(Quotation $quotation): array
    {
        return [
            'quotation_number' => $quotation->quotation_number,
            'quotation_date' => $quotation->quotation_date?->toDateString(),
            'valid_until' => $quotation->valid_until?->toDateString(),
            'job_number' => $quotation->repairJob->job_number,
            'customer' => [
                'customer_code' => $quotation->customer->customer_code,
                'name' => $quotation->customer->display_name,
                'address' => $quotation->customer->address,
                'mobile_number' => $quotation->customer->mobile_number,
            ],
            'item' => [
                'name' => $quotation->repairJob->item_name,
                'brand' => $quotation->repairJob->brand,
                'model' => $quotation->repairJob->model,
                'description' => $quotation->repairJob->item_description,
                'reported_fault' => $quotation->repairJob->reported_fault,
            ],
            'proposed_repair_description' => $quotation->proposed_repair_description,
            'items' => $quotation->items->map(fn ($item): array => [
                'description' => $item->description,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'line_total' => (string) $item->line_total,
            ])->all(),
            'parts_total' => (string) $quotation->parts_total,
            'labour_charge' => (string) $quotation->labour_charge,
            'quotation_total' => (string) $quotation->total,
            'terms_and_conditions' => $quotation->terms_and_conditions,
            'prepared_by' => $quotation->createdByUser?->name,
        ];
    }

    private function restrictToVisibleJobs($query): void
    {
        $user = auth()->user();
        if ($user->hasRole('technician') && ! $user->hasAnyRole(['administrator', 'manager'])) {
            $query->whereHas('repairJob', fn ($job) => $job->where('technician_id', $user->employee?->getKey() ?? 0));
        }
    }

    private function safeMargin(mixed $value, float $fallback): float
    {
        return is_numeric($value) ? max(0, min(20, (float) $value)) : $fallback;
    }

    private function logoDataUri(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        $public = realpath(public_path());
        $file = realpath(public_path($path));
        if ($public === false || $file === false || ! str_starts_with($file, $public.DIRECTORY_SEPARATOR) || ! is_file($file)) {
            return null;
        }
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (! in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            return null;
        }
        $contents = file_get_contents($file);

        return $contents === false
            ? null
            : 'data:image/'.($extension === 'jpg' ? 'jpeg' : $extension).';base64,'.base64_encode($contents);
    }
}
