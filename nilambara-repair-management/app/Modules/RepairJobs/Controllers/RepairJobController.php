<?php

namespace App\Modules\RepairJobs\Controllers;

use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Services\EmployeeRegistrationService;
use App\Rules\PhoneNumber;
use App\Modules\Payments\Models\PaymentAllocation;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\RepairJobs\Requests\AssignTechnicianRequest;
use App\Modules\RepairJobs\Requests\RequestRepairJobEditRequest;
use App\Modules\Approvals\Services\ApprovalAccess;
use App\Modules\Approvals\Services\ApprovalService;
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
            'contactAttempts.recordedBy',
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

    public function requestEdit(Request $request, RepairJob $job): View
    {
        Gate::authorize('view', $job);
        abort_unless(ApprovalAccess::canRequestChange($request->user(), 'job_update'), 403);

        return view('modules.repair-jobs.edit', ['job' => $job, 'requestMode' => true]);
    }

    public function storeEditRequest(RequestRepairJobEditRequest $request, RepairJob $job, ApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('view', $job);
        $data = $request->validated();
        $approvals->submitChange('job_update', $job, $data, $data['reason'], $data['idempotency_key'], $request->user());

        return redirect()->route('repair-jobs.show', $job)->with('status', __('ui.edit_request_submitted'));
    }

    public function destroy(RepairJob $job, RepairJobService $jobs, Request $request): RedirectResponse
    {
        Gate::authorize('delete', $job);
        $jobs->delete($job, $request->user());

        return redirect()->route('repair-jobs.index')->with('status', __('ui.repair_job_deleted'));
    }

    public function requestDelete(Request $request, RepairJob $job, ApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('view', $job);
        abort_unless(ApprovalAccess::canRequestChange($request->user(), 'job_delete'), 403);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $approvals->submitDelete($job, $data['reason'], $data['idempotency_key'], $request->user());

        return redirect()->route('repair-jobs.show', $job)->with('status', __('ui.delete_request_submitted'));
    }

    public function assignForm(RepairJob $job): View
    {
        Gate::authorize('assign', $job);

        return view('modules.repair-jobs.assign', [
            'job' => $job->load('technician'),
            'technicians' => Employee::query()->activeTechnicians()->orderBy('name')
                ->with(['technicianRepairJobs' => fn ($query) => $query
                    ->whereNotIn('status', [JobStatus::Delivered->value, JobStatus::Cancelled->value, JobStatus::UnableToRepair->value])
                    ->with('customer')
                    ->latest('id')])
                ->get(['id', 'name', 'employee_code', 'mobile_number', 'user_id']),
        ]);
    }

    public function updateTechnician(Request $request, RepairJob $job, Employee $technician): RedirectResponse
    {
        Gate::authorize('assign', $job);
        $this->authorizeManagedTechnician($technician);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:30', new PhoneNumber],
        ]);

        app(EmployeeRegistrationService::class)->update($technician, $data, $request->user());

        return redirect()->route('repair-jobs.assign-form', $job)->with('status', __('ui.technician_updated'));
    }

    public function destroyTechnician(Request $request, RepairJob $job, Employee $technician): RedirectResponse
    {
        Gate::authorize('assign', $job);
        $this->authorizeManagedTechnician($technician);

        // Employee records are retained for history, so removal deactivates the technician.
        app(EmployeeRegistrationService::class)->update($technician, ['is_active' => false], $request->user());

        return redirect()->route('repair-jobs.assign-form', $job)->with('status', __('ui.technician_removed'));
    }

    private function authorizeManagedTechnician(Employee $technician): void
    {
        abort_unless(
            $technician->user_id === null && Employee::query()->activeTechnicians()->whereKey($technician->getKey())->exists(),
            403,
        );
    }

    public function storeTechnician(Request $request, RepairJob $job): RedirectResponse
    {
        Gate::authorize('assign', $job);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:30', new PhoneNumber],
        ]);

        $technician = app(EmployeeRegistrationService::class)->register([
            'name' => $data['name'],
            'mobile_number' => $data['mobile_number'] ?? null,
            'job_title' => Employee::TECHNICIAN_TITLE,
            'is_active' => true,
        ], $request->user());

        return redirect()->route('repair-jobs.assign-form', $job)
            ->withInput(['technician_id' => $technician->getKey()])
            ->with('status', __('ui.technician_created'));
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

    public function recordContactAttempt(Request $request, RepairJob $job, RepairJobService $jobs): RedirectResponse
    {
        $data = $request->validate([
            'contacted_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $attempt = $jobs->recordContactAttempt($job, $data['contacted_on'], $data['notes'] ?? null, $request->user());

        $target = $request->input('return_to') === 'status-search'
            ? redirect()->to(url()->previous(route('repair-jobs.status-search')))
            : redirect()->route('repair-jobs.show', $job);

        return $target->with('status', __('ui.contact_attempt_saved', ['number' => $attempt->attempt_number]));
    }

    public function updateStatus(UpdateJobStatusRequest $request, RepairJob $job, RepairJobService $jobs): RedirectResponse
    {
        $jobs->updateStatus($job, $request->validated(), $request->user());

        $target = $request->input('return_to') === 'status-search'
            ? redirect()->to(url()->previous(route('repair-jobs.status-search')))
            : redirect()->route('repair-jobs.show', $job);

        return $target->with('status', __('ui.job_status_updated'));
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
            ->with(['customer', 'technician', 'receivedByEmployee', 'receipt', 'contactAttempts'])
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
