<?php

namespace App\Modules\RepairJobs\Controllers;

use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Modules\Employees\Models\Employee;
use App\Modules\Payments\Models\PaymentAllocation;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\RepairJobs\Requests\AssignTechnicianRequest;
use App\Modules\RepairJobs\Requests\SaveJobInspectionRequest;
use App\Modules\RepairJobs\Requests\SaveRepairInformationRequest;
use App\Modules\RepairJobs\Requests\UpdateJobStatusRequest;
use App\Modules\RepairJobs\Requests\UpdateRepairJobRequest;
use App\Modules\RepairJobs\Services\RepairJobService;
use App\Modules\RepairReceipts\Controllers\RepairReceiptController;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RepairJobController extends Controller
{
    public function printReceipt(RepairJob $job): View
    {
        Gate::authorize('view', $job);
        abort_unless($job->receipt, 404);

        return app(RepairReceiptController::class)->print($job->receipt);
    }

    public function duplicateReceipt(RepairJob $job): View
    {
        Gate::authorize('view', $job);
        abort_unless($job->receipt, 404);

        return app(RepairReceiptController::class)->duplicate($job->receipt);
    }

    public function receiptPdf(RepairJob $job)
    {
        Gate::authorize('view', $job);
        abort_unless($job->receipt, 404);

        return app(RepairReceiptController::class)->pdf($job->receipt);
    }

    public function setLabourCharge(Request $request, RepairJob $job, RepairJobService $jobs): RedirectResponse
    {
        $data = $request->validate(['labour_charge' => 'required|numeric|decimal:0,2|between:0,999999999999.99']);
        $jobs->setLabourCharge($job, $data['labour_charge'], $request->user());

        return back()->with('status', 'Labour selling charge saved. Parts charges come from issued parts.');
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', RepairJob::class);

        return view('modules.repair-jobs.index', [
            'jobs' => $this->filteredJobs($request)->paginate(20)->withQueryString(),
            'technicians' => $this->technicians(),
            'statuses' => JobStatus::cases(),
            'statusSearch' => false,
        ]);
    }

    public function myAssigned(Request $request): View
    {
        Gate::authorize('viewAny', RepairJob::class);
        abort_unless($request->user()->can('jobs.repair') && $request->user()->employee?->is_active, 403);

        return view('modules.repair-jobs.index', [
            'jobs' => $this->filteredJobs($request)
                ->where('technician_id', $request->user()->employee->getKey())
                ->paginate(20)->withQueryString(),
            'technicians' => $this->technicians(),
            'statuses' => JobStatus::cases(),
            'statusSearch' => false,
            'assignedOnly' => true,
        ]);
    }

    public function statusSearch(Request $request): View
    {
        Gate::authorize('viewAny', RepairJob::class);

        return view('modules.repair-jobs.status-search', [
            'jobs' => $this->filteredJobs($request)->paginate(20)->withQueryString(),
            'statuses' => JobStatus::cases(),
        ]);
    }

    public function show(RepairJob $job): View
    {
        Gate::authorize('view', $job);
        $job->load([
            'customer',
            'receipt',
            'receivedByEmployee',
            'technician',
            'jobInspections.inspectedByEmployee',
            'jobInspections.repairedByEmployee',
            'jobStatusHistories' => fn ($history) => $history->with('changedByUser')
                ->orderByDesc('changed_at')
                ->orderByDesc('id'),
        ]);
        if (auth()->user()->can('quotations.view')) {
            $job->load(['quotations' => fn ($query) => $query
                ->with('createdByUser')
                ->orderByDesc('revision')
                ->orderByDesc('id')]);
        }
        $advancePaid = $this->advancePaid($job);
        $advancePayments = auth()->user()->can('payments.view')
            ? PaymentAllocation::query()
                ->with(['payment.receivedByEmployee', 'releases'])
                ->where('repair_job_id', $job->getKey())
                ->where('target_type', 'job_advance')
                ->orderByDesc('allocated_at')
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('modules.repair-jobs.show', [
            'job' => $job,
            'advancePaid' => $advancePaid,
            'advancePayments' => $advancePayments,
            'statuses' => JobStatus::cases(),
        ]);
    }

    public function edit(RepairJob $job): View
    {
        Gate::authorize('update', $job);

        return view('modules.repair-jobs.edit', ['job' => $job]);
    }

    public function update(UpdateRepairJobRequest $request, RepairJob $job, RepairJobService $jobs): RedirectResponse
    {
        $jobs->updateDetails($job, $request->validated(), $request->user());

        return redirect()->route('repair-jobs.show', $job)->with('status', __('ui.repair_job_updated'));
    }

    public function assignForm(RepairJob $job): View
    {
        Gate::authorize('assign', $job);

        return view('modules.repair-jobs.assign', [
            'job' => $job->load('technician'),
            'technicians' => $this->technicians(),
        ]);
    }

    public function assign(AssignTechnicianRequest $request, RepairJob $job, RepairJobService $jobs): RedirectResponse
    {
        $jobs->assignTechnician($job, (int) $request->validated('technician_id'), $request->user());

        return redirect()->route('repair-jobs.show', $job)->with('status', __('ui.technician_assigned'));
    }

    public function inspect(SaveJobInspectionRequest $request, RepairJob $job, RepairJobService $jobs): RedirectResponse
    {
        $jobs->recordInspection($job, $request->validated(), $request->user());

        return redirect()->route('repair-jobs.show', $job)->with('status', __('ui.inspection_saved'));
    }

    public function saveRepair(SaveRepairInformationRequest $request, RepairJob $job, RepairJobService $jobs): RedirectResponse
    {
        $jobs->recordRepairInformation($job, $request->validated(), $request->user());

        return redirect()->route('repair-jobs.show', $job)->with('status', __('ui.repair_information_saved'));
    }

    public function updateStatus(UpdateJobStatusRequest $request, RepairJob $job, RepairJobService $jobs): RedirectResponse
    {
        $jobs->updateStatus($job, $request->validated(), $request->user());

        return redirect()->route('repair-jobs.show', $job)->with('status', __('ui.job_status_updated'));
    }

    private function filteredJobs(Request $request): Builder
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(JobStatus::class)],
            'technician_id' => ['nullable', 'integer', 'exists:employees,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = RepairJob::query()
            ->with(['customer', 'technician', 'receivedByEmployee', 'receipt'])
            ->select('repair_jobs.*')
            ->selectSub(
                PaymentAllocation::query()
                    ->selectRaw('COALESCE(SUM(payment_allocations.amount - COALESCE((SELECT SUM(payment_allocation_releases.amount) FROM payment_allocation_releases WHERE payment_allocation_releases.payment_allocation_id = payment_allocations.id), 0)), 0)')
                    ->whereColumn('payment_allocations.repair_job_id', 'repair_jobs.id')
                    ->where('payment_allocations.target_type', 'job_advance'),
                'advance_paid',
            )
            ->orderByDesc('received_at')
            ->orderByDesc('id');

        if (($search = trim((string) ($filters['q'] ?? ''))) !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('automatic_number', 'like', $like)
                    ->orWhere('job_number', 'like', $like)
                    ->orWhere('item_name', 'like', $like)
                    ->orWhere('reported_fault', 'like', $like)
                    ->orWhereHas('customer', function ($customer) use ($like): void {
                        $customer->where('customer_name', 'like', $like)
                            ->orWhere('company_name', 'like', $like)
                            ->orWhere('mobile_number', 'like', $like);
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['technician_id'])) {
            $query->where('technician_id', $filters['technician_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('received_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('received_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    /**
     * @return Collection<int, Employee>
     */
    private function technicians()
    {
        return Employee::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code']);
    }

    private function advancePaid(RepairJob $job): string
    {
        return (string) PaymentAllocation::query()
            ->where('repair_job_id', $job->getKey())
            ->where('target_type', 'job_advance')
            ->selectRaw('COALESCE(SUM(payment_allocations.amount - COALESCE((SELECT SUM(payment_allocation_releases.amount) FROM payment_allocation_releases WHERE payment_allocation_releases.payment_allocation_id = payment_allocations.id), 0)), 0) as total')
            ->value('total');
    }
}
