<?php

namespace App\Modules\Quotations\Services;

use App\Enums\ApprovalStatus;
use App\Enums\JobStatus;
use App\Enums\QuotationPreparationStatus;
use App\Models\User;
use App\Modules\Quotations\Models\Quotation;
use App\Modules\Quotations\Models\QuotationApproval;
use App\Modules\Quotations\Models\QuotationHistory;
use App\Modules\RepairJobs\Models\JobApproval;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\RepairJobs\Services\RepairJobService;
use App\Support\BusinessAudit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationApprovalService
{
    public function __construct(private readonly RepairJobService $repairJobs) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordDecision(Quotation $quotation, array $data, User $user): Quotation
    {
        return DB::transaction(function () use ($quotation, $data, $user): Quotation {
            $job = RepairJob::query()->whereKey($quotation->repair_job_id)->lockForUpdate()->firstOrFail();
            $locked = Quotation::query()->whereKey($quotation->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->preparation_status !== QuotationPreparationStatus::Issued
                || $locked->approval_status !== ApprovalStatus::Pending) {
                throw ValidationException::withMessages(['decision' => __('ui.quotation_decision_not_available')]);
            }

            if ($job->status === JobStatus::Cancelled || $job->delivered_at !== null) {
                throw ValidationException::withMessages(['decision' => __('ui.quotation_job_closed')]);
            }

            $decision = ApprovalStatus::from($data['decision']);
            $today = now(config('business.timezone', 'Asia/Colombo'))->startOfDay();
            if ($decision === ApprovalStatus::Approved
                && ($locked->valid_until === null || $locked->valid_until->lt($today))) {
                throw ValidationException::withMessages(['decision' => __('ui.quotation_expired_cannot_approve')]);
            }

            $now = isset($data['decision_date'])
                ? Carbon::parse($data['decision_date'], config('business.timezone', 'Asia/Colombo'))
                : now(config('business.timezone', 'Asia/Colombo'));
            if ($now->greaterThan(now(config('business.timezone', 'Asia/Colombo')))) {
                throw ValidationException::withMessages([
                    'decision_date' => __('ui.quotation_decision_date_future'),
                ]);
            }
            $old = [
                'approval_status' => $locked->approval_status->value,
                'job_approval_status' => $job->approval_status?->value,
                'job_status' => $job->status->value,
            ];
            $locked->approval_status = $decision;
            $locked->updated_by_user_id = $user->getKey();
            $locked->save();

            QuotationApproval::query()->create([
                'quotation_id' => $locked->getKey(),
                'decision' => $decision->value,
                'decided_at' => $now,
                'customer_contact_name' => trim($data['customer_contact_name']),
                'approval_method' => $data['approval_method'],
                'recorded_by_user_id' => $user->getKey(),
                'notes' => $this->nullableTrim($data['notes'] ?? null),
            ]);

            JobApproval::query()->create([
                'repair_job_id' => $job->getKey(),
                'decision' => $decision->value,
                'decided_at' => $now,
                'approved_by_name' => trim($data['customer_contact_name']),
                'recorded_by_user_id' => $user->getKey(),
                'notes' => $this->nullableTrim($data['notes'] ?? null),
            ]);

            $job->approval_status = $decision;
            $job->save();
            $targetStatus = $decision === ApprovalStatus::Approved
                ? JobStatus::UnderRepair
                : JobStatus::CustomerContactFailed;
            $job = $this->repairJobs->updateStatus($job, [
                'status' => $targetStatus->value,
                'notes' => __('ui.quotation_job_status_note', [
                    'quotation' => $locked->quotation_number,
                    'decision' => $decision->value,
                ]),
            ], $user);

            $new = [
                'approval_status' => $decision->value,
                'job_approval_status' => $job->approval_status?->value,
                'job_status' => $job->status->value,
                'decision_date' => $now->toIso8601String(),
                'customer_contact_name' => trim($data['customer_contact_name']),
                'approval_method' => $data['approval_method'],
            ];
            QuotationHistory::query()->create([
                'quotation_id' => $locked->getKey(),
                'actor_user_id' => $user->getKey(),
                'event_type' => $decision === ApprovalStatus::Approved ? 'customer_approved' : 'customer_rejected',
                'old_values' => $old,
                'new_values' => $new,
                'event_at' => now(),
            ]);
            BusinessAudit::record(
                $user,
                $decision === ApprovalStatus::Approved ? 'quotation.customer_approved' : 'quotation.customer_rejected',
                Quotation::class,
                (int) $locked->getKey(),
                $old,
                [
                    ...$new,
                    'repair_job_id' => $job->getKey(),
                ],
            );

            return $locked->refresh();
        });
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
