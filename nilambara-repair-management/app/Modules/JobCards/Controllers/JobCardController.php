<?php

namespace App\Modules\JobCards\Controllers;

use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Modules\JobCards\Models\JobCard;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\PrintTemplates\Models\PrintTemplate;
use App\Modules\PrintTemplates\Services\IntakePdfService;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Support\BusinessAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JobCardController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(JobStatus::class)],
        ]);
        $query = RepairJob::query()
            ->with(['customer', 'technician', 'jobCard'])
            ->orderByDesc('received_at')
            ->orderByDesc('id');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('automatic_number', 'like', $like)
                    ->orWhere('job_number', 'like', $like)
                    ->orWhere('item_name', 'like', $like)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('customer_name', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('mobile_number', 'like', $like))
                    ->orWhereHas('technician', fn ($technician) => $technician->where('name', 'like', $like));
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('modules.job-cards.index', [
            'jobs' => $query->paginate(20)->withQueryString(),
            'search' => $search,
            'status' => $filters['status'] ?? '',
        ]);
    }

    public function print(RepairJob $job): View
    {
        return $this->render($job, false);
    }

    public function duplicate(RepairJob $job): View
    {
        return $this->render($job, true);
    }

    public function pdf(RepairJob $job)
    {
        return app(IntakePdfService::class)->download($this->render($job, request()->boolean('duplicate'), true), ($job->automatic_number ?: $job->job_number).'-card.pdf');
    }

    private function render(RepairJob $job, bool $duplicate, bool $pdf = false): View
    {
        Gate::authorize('view', $job);
        $job->load([
            'customer',
            'receivedByEmployee',
            'technician',
            'jobCard',
        ]);
        $jobCard = $job->jobCard ?? $job->jobCards()->create(['paper_size' => 'A5']);
        $template = PrintTemplate::query()
            ->where('document_type', 'job_card')
            ->where('is_active', true)
            ->orderByDesc('version')
            ->first();
        $label = $template?->duplicate_copy_label ?: __('ui.duplicate_copy');
        $printedAt = now(config('business.timezone', 'Asia/Colombo'));
        $paperSize = 'A5';
        $pageWidth = 148 - $this->safeMillimetres($template?->margin_left_mm, 5) - $this->safeMillimetres($template?->margin_right_mm, 5);
        $orientation = 'portrait';
        $snapshot = [
            'automatic_number' => $job->automatic_number,
            'job_number' => $job->job_number,
            'item_name' => $job->item_name,
            'brand' => $job->brand,
            'model' => $job->model,
            'item_description' => $job->item_description,
            'reported_fault' => $job->reported_fault,
            'document_kind' => 'technician_worksheet',
            'handwritten_fields' => ['status', 'issues_found', 'inspected_by', 'inspection_date', 'repaired_by', 'completion_date', 'work_performed', 'parts_used', 'parts_cost', 'total_amount', 'notes', 'signature', 'returned_to_cashier_date', 'cashier_received_by'],
            'duplicate' => $duplicate,
        ];

        DB::transaction(function () use ($jobCard, $job, $template, $duplicate, $label, $printedAt, $snapshot, $paperSize, $pdf): void {
            DocumentPrintHistory::query()->create([
                'print_template_id' => $template?->getKey(),
                'job_card_id' => $jobCard->getKey(),
                'printed_at' => $printedAt,
                'printed_by_user_id' => auth()->id(),
                'is_duplicate' => $duplicate,
                'duplicate_label' => $duplicate ? $label : null,
                'output_reference' => $pdf ? ($job->automatic_number ?: $job->job_number).'-card.pdf' : null,
                'document_snapshot' => [
                    ...$snapshot,
                    'duplicate_label' => $duplicate ? $label : null,
                    'printed_at' => $printedAt->toIso8601String(),
                    'paper_size' => $paperSize,
                    'export_format' => $pdf ? 'pdf' : 'html',
                ],
            ]);

            BusinessAudit::record(
                auth()->user(),
                $pdf ? 'job_card.pdf_exported' : ($duplicate ? 'job_card.reprinted' : 'job_card.print_prepared'),
                JobCard::class,
                (int) $jobCard->getKey(),
                newValues: [
                    'job_id' => $job->getKey(),
                    'automatic_number' => $job->automatic_number,
                    'duplicate' => $duplicate,
                    'paper_size' => $paperSize,
                ],
            );
        });

        return view('modules.job-cards.print', [
            'job' => $job,
            'nativeWorksheetLayout' => true,
            'template' => $template,
            'duplicate' => $duplicate,
            'duplicateLabel' => $label,
            'printedAt' => $printedAt,
            'paperSize' => $paperSize,
            'orientation' => $orientation,
            'pageWidth' => $pageWidth.'mm',
            'margins' => [
                'top' => $this->safeMillimetres($template?->margin_top_mm, 5),
                'right' => $this->safeMillimetres($template?->margin_right_mm, 5),
                'bottom' => $this->safeMillimetres($template?->margin_bottom_mm, 5),
                'left' => $this->safeMillimetres($template?->margin_left_mm, 5),
            ],
            'fontSize' => is_numeric($template?->font_size_pt) ? max(7, min(12, (float) $template->font_size_pt)) : 9,
            'businessName' => $template?->business_name ?: config('business.name'),
            'businessAddress' => $template?->business_address ?: config('business.address'),
            'businessTelephone' => $template?->telephone_number ?: config('business.contact_number'),
            'logoUrl' => $template?->logo_path ? asset($template->logo_path) : asset(config('business.logo')),
            'documentTitle' => $template?->document_title ?: __('ui.repair_job_card'),
            'footerText' => $template?->footer ?: config('business.document_footer'),
        ]);
    }

    private function safeMillimetres(mixed $value, float $fallback): float
    {
        $number = is_numeric($value) ? (float) $value : $fallback;

        return max(0, min(20, $number));
    }
}
