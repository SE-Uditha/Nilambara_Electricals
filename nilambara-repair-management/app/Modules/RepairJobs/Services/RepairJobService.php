<?php

namespace App\Modules\RepairJobs\Services;

use App\Enums\ApprovalStatus;
use App\Enums\JobStatus;
use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\JobTracking\Models\JobStatusHistory;
use App\Modules\RepairJobs\Models\JobInspection;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RepairJobService
{
    /** Tables that hold money, stock or quotation history for a job; any row blocks deletion. */
    private const DELETE_BLOCKING_TABLES = [
        'payment_allocations' => 'a payment (reverse it first)',
        'job_part_usages' => 'issued or requested spare parts',
        'stock_returns' => 'a stock return',
        'quotations' => 'a quotation',
        'job_approvals' => 'a customer approval record',
    ];

    /** Records that exist only to document the job and are removed with it. */
    private const DELETE_CASCADE_TABLES = ['repair_receipts', 'job_cards', 'job_status_histories', 'job_inspections', 'repair_job_charges'];

    /** Invoice records removed together with an invoice that was issued only for the deleted job. */
    private const DELETE_INVOICE_TABLES = ['invoice_items', 'invoice_history', 'document_print_histories', 'customer_account_entries', 'invoice_jobs'];

    /** @return list<string> */
    public function deletionBlockers(RepairJob $job): array
    {
        $blockers = [];
        foreach (self::DELETE_BLOCKING_TABLES as $table => $label) {
            if (DB::table($table)->where('repair_job_id', $job->getKey())->exists()) {
                $blockers[] = $label;
            }
        }

        $invoiceIds = DB::table('invoice_jobs')->where('repair_job_id', $job->getKey())->pluck('invoice_id');
        if ($invoiceIds->isNotEmpty()) {
            if (DB::table('invoice_jobs')->whereIn('invoice_id', $invoiceIds)->where('repair_job_id', '!=', $job->getKey())->exists()) {
                $blockers[] = 'an invoice shared with other jobs';
            }
            if (DB::table('payment_allocations')->whereIn('invoice_id', $invoiceIds)->exists() && ! in_array('a payment (reverse it first)', $blockers, true)) {
                $blockers[] = 'a payment (reverse it first)';
            }
        }

        return $blockers;
    }

    public function delete(RepairJob $job, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $job);

        DB::transaction(function () use ($job, $actor): void {
            $locked = RepairJob::query()->whereKey($job->getKey())->lockForUpdate()->first();
            if ($locked === null) {
                throw ValidationException::withMessages(['delete' => 'This repair job no longer exists.']);
            }

            $blockers = $this->deletionBlockers($locked);
            if ($blockers !== []) {
                throw ValidationException::withMessages(['delete' => 'This job cannot be deleted because it has '.implode(', ', $blockers).'. Cancel the job instead.']);
            }

            $snapshot = $locked->only(['automatic_number', 'job_number', 'customer_id', 'item_name', 'brand', 'model', 'status', 'received_at', 'total_job_amount']);
            $invoiceIds = DB::table('invoice_jobs')->where('repair_job_id', $locked->getKey())->pluck('invoice_id');
            if ($invoiceIds->isNotEmpty()) {
                $snapshot['deleted_invoices'] = DB::table('invoices')->whereIn('id', $invoiceIds)->pluck('invoice_number')->all();
                foreach (self::DELETE_INVOICE_TABLES as $table) {
                    DB::table($table)->whereIn('invoice_id', $invoiceIds)->delete();
                }
                DB::table('invoices')->whereIn('id', $invoiceIds)->delete();
            }
            $receiptIds = DB::table('repair_receipts')->where('repair_job_id', $locked->getKey())->pluck('id');
            $cardIds = DB::table('job_cards')->where('repair_job_id', $locked->getKey())->pluck('id');
            DB::table('document_print_histories')->where(fn ($q) => $q->whereIn('repair_receipt_id', $receiptIds)->orWhereIn('job_card_id', $cardIds))->delete();
            foreach (self::DELETE_CASCADE_TABLES as $table) {
                DB::table($table)->where('repair_job_id', $locked->getKey())->delete();
            }
            DB::table('repair_jobs')->where('id', $locked->getKey())->delete();

            BusinessAudit::record($actor, 'repair_job.deleted', RepairJob::class, (int) $locked->getKey(), json_decode(json_encode($snapshot), true));
        }, 3);
    }

    public function setLabourCharge(RepairJob $job, string $amount, User $actor): RepairJob
    {
        Gate::forUser($actor)->authorize('invoices.create');
        Gate::forUser($actor)->authorize('view', $job);

        return DB::transaction(function () use ($job, $amount, $actor) {
            $job = RepairJob::whereKey($job->id)->lockForUpdate()->firstOrFail();
            if ($job->invoiceJobs()->exists() || $job->quotations()->where('preparation_status', 'issued')->exists() || $job->cancelled_at !== null || $job->delivered_at !== null) {
                throw ValidationException::withMessages(['labour_charge' => 'Billed, quoted or closed job prices cannot be changed here.']);
            }
            Validator::make(['labour_charge' => $amount], ['labour_charge' => 'required|numeric|decimal:0,2|between:0,999999999999.99'])->validate();
            $old = $job->only(['labour_charge', 'parts_charge', 'total_job_amount']);
            $customTotal = $job->billingCharges()->whereNull('removed_at')->get()->reduce(fn (BigDecimal $sum, $line) => $sum->plus($line->line_total), BigDecimal::zero());
            $amount = (string) BigDecimal::of($amount)->plus($customTotal)->toScale(2);
            $job->labour_charge = $amount;
            $job->total_job_amount = (string) BigDecimal::of($amount)->plus($job->parts_charge)->toScale(2);
            $job->save();
            BusinessAudit::record($actor, 'repair_job.charge.updated', RepairJob::class, $job->id, $old, $job->only(['labour_charge', 'parts_charge', 'total_job_amount']));

            return $job;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDetails(RepairJob $job, array $data, User $user): RepairJob
    {
        return DB::transaction(function () use ($job, $data, $user): RepairJob {
            $lockedJob = RepairJob::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();
            $old = $lockedJob->only([
                'job_type',
                'item_name',
                'brand',
                'model',
                'item_description',
                'reported_fault',
                'accessories_received',
                'physical_condition',
                'visible_damage',
                'special_notes',
            ]);
            $lockedJob->fill($data);
            $changes = $lockedJob->getDirty();

            if ($changes !== []) {
                $lockedJob->save();
                BusinessAudit::record(
                    $user,
                    'repair_job.updated',
                    RepairJob::class,
                    (int) $lockedJob->getKey(),
                    array_intersect_key($old, $changes),
                    array_intersect_key($lockedJob->getAttributes(), $changes),
                );
            }

            return $lockedJob->refresh();
        });
    }

    public function assignTechnician(RepairJob $job, int $employeeId, User $user): RepairJob
    {
        return DB::transaction(function () use ($job, $employeeId, $user): RepairJob {
            $lockedJob = RepairJob::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();
            $technician = Employee::query()->with('user')
                ->activeTechnicians()
                ->whereKey($employeeId)
                ->first();

            if ($technician === null) {
                throw ValidationException::withMessages([
                    'technician_id' => __('ui.active_technician_required'),
                ]);
            }

            if ((int) $lockedJob->technician_id === (int) $technician->getKey()) {
                return $lockedJob;
            }

            $previousTechnicianId = $lockedJob->technician_id;
            $lockedJob->technician_id = $technician->getKey();
            $lockedJob->save();

            BusinessAudit::record(
                $user,
                $previousTechnicianId === null ? 'repair_job.technician_assigned' : 'repair_job.technician_reassigned',
                RepairJob::class,
                (int) $lockedJob->getKey(),
                ['technician_id' => $previousTechnicianId],
                ['technician_id' => $technician->getKey(), 'technician_name' => $technician->name],
            );

            return $lockedJob->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordInspection(RepairJob $job, array $data, User $user): JobInspection
    {
        return DB::transaction(function () use ($job, $data, $user): JobInspection {
            $lockedJob = RepairJob::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();
            $employee = $this->activeEmployeeFor($user);
            $inspection = $lockedJob->jobInspections()->create([
                'inspected_at' => $data['inspected_at'] ?? now(),
                'inspected_by_employee_id' => $employee->getKey(),
                'existing_fault' => $data['existing_fault'] ?? $lockedJob->reported_fault,
                'inspection_result' => $data['inspection_result'],
                'technical_information' => $data['technical_information'] ?? null,
                'special_notes' => $data['special_notes'] ?? null,
            ]);

            BusinessAudit::record(
                $user,
                'repair_job.inspection_recorded',
                RepairJob::class,
                (int) $lockedJob->getKey(),
                newValues: [
                    'inspection_id' => $inspection->getKey(),
                    'inspected_by_employee_id' => $employee->getKey(),
                    'inspected_at' => $inspection->inspected_at->toIso8601String(),
                ],
            );

            return $inspection;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordRepairInformation(RepairJob $job, array $data, User $user): JobInspection
    {
        return DB::transaction(function () use ($job, $data, $user): JobInspection {
            $lockedJob = RepairJob::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();
            $employee = $this->activeEmployeeFor($user);
            $completedAt = $data['repair_completed_at'] ?? null;
            $inspection = $lockedJob->jobInspections()->create([
                'inspected_at' => now(),
                'inspected_by_employee_id' => $employee->getKey(),
                'inspection_result' => '',
                'technical_information' => null,
                'work_performed' => $data['work_performed'],
                'parts_used' => $data['parts_used'] ?? null,
                'parts_quantity' => $data['parts_quantity'] ?? null,
                'repaired_by_employee_id' => $employee->getKey(),
                'repair_completed_at' => $completedAt,
                'testing_details' => $data['testing_details'] ?? null,
                'special_notes' => $data['special_notes'] ?? null,
            ]);

            $oldValues = [
                'repair_description' => $lockedJob->repair_description,
                'parts_cost' => $lockedJob->parts_cost,
                'labour_cost' => $lockedJob->labour_cost,
                'completed_at' => $lockedJob->completed_at?->toIso8601String(),
            ];
            $lockedJob->repair_description = $data['work_performed'];

            if (array_key_exists('parts_cost', $data) && $data['parts_cost'] !== null) {
                $lockedJob->parts_cost = $data['parts_cost'];
            }

            if (array_key_exists('labor_cost', $data) && $data['labor_cost'] !== null) {
                $lockedJob->labour_cost = $data['labor_cost'];
            }

            if ($completedAt !== null) {
                $lockedJob->completed_at = $completedAt;
            }

            $lockedJob->save();
            BusinessAudit::record(
                $user,
                'repair_job.repair_information_recorded',
                RepairJob::class,
                (int) $lockedJob->getKey(),
                $oldValues,
                [
                    'inspection_id' => $inspection->getKey(),
                    'repair_description' => $lockedJob->repair_description,
                    'parts_cost' => $lockedJob->parts_cost,
                    'labour_cost' => $lockedJob->labour_cost,
                    'completed_at' => $lockedJob->completed_at?->toIso8601String(),
                ],
            );

            return $inspection;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateStatus(RepairJob $job, array $data, User $user): RepairJob
    {
        return DB::transaction(function () use ($job, $data, $user): RepairJob {
            $lockedJob = RepairJob::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();
            $previous = $lockedJob->status instanceof JobStatus ? $lockedJob->status : JobStatus::from($lockedJob->status);
            $next = JobStatus::from($data['status']);

            if ($previous === $next) {
                return $lockedJob;
            }
            if ($next === JobStatus::UnderRepair
                && $lockedJob->quotations()->whereNotNull('issued_at')->exists()
                && $lockedJob->approval_status !== ApprovalStatus::Approved) {
                throw ValidationException::withMessages([
                    'status' => __('ui.quotation_approval_required_before_repair'),
                ]);
            }

            $lockedJob->status = $next->value;
            if ($next === JobStatus::RepairCompleted && $lockedJob->completed_at === null) {
                $lockedJob->completed_at = now();
            }
            if ($next === JobStatus::Delivered && $lockedJob->delivered_at === null) {
                $lockedJob->delivered_at = now();
            }

            if ($next === JobStatus::Cancelled && $lockedJob->cancelled_at === null) {
                $lockedJob->cancelled_at = now();
                $lockedJob->cancelled_by_user_id = $user->getKey();
                $lockedJob->cancellation_reason = $data['cancellation_reason'] ?? null;
            }

            $lockedJob->save();
            JobStatusHistory::query()->create([
                'repair_job_id' => $lockedJob->getKey(),
                'previous_status' => $previous->value,
                'new_status' => $next->value,
                'changed_by_user_id' => $user->getKey(),
                'changed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            BusinessAudit::record(
                $user,
                $next === JobStatus::Cancelled ? 'repair_job.cancelled' : 'repair_job.status_changed',
                RepairJob::class,
                (int) $lockedJob->getKey(),
                ['status' => $previous->value],
                [
                    'status' => $next->value,
                    'notes' => $data['notes'] ?? null,
                    'cancellation_reason' => $data['cancellation_reason'] ?? null,
                ],
            );

            return $lockedJob->refresh();
        });
    }

    private function activeEmployeeFor(User $user): Employee
    {
        $employee = Employee::query()
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->first();

        if ($employee === null) {
            throw ValidationException::withMessages([
                'employee' => __('ui.active_employee_required'),
            ]);
        }

        return $employee;
    }
}
