<?php

namespace App\Modules\Approvals\Services;

use App\Models\User;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\RepairJobs\Services\RepairJobService;
use App\Modules\Inventory\Services\StockWorkflowService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Purchases\Services\PurchaseWorkflowService;
use App\Support\BusinessAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function submit(array $data, User $actor): ApprovalRequest
    {
        abort_unless($actor->hasActiveAccount() && isset(ApprovalAccess::requestKinds($actor)[$data['kind']]), 403);

        return DB::transaction(function () use ($data, $actor) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $kind = $data['kind'];
            if (in_array($kind, ['stock_adjustment', 'stock_count'], true)) {
                $part = app(StockWorkflowService::class)->lockPart((int) $data['spare_part_id'], (int) $data['location_id']);
                $location = StockLocation::findOrFail($data['location_id']);
                if (! $part->is_active || ! $location->is_active) {
                    throw ValidationException::withMessages(['spare_part_id' => 'Select an active part and stock location.']);
                }
                $quantity = app(StockWorkflowService::class)->quantity($part, (string) $data['quantity'], $kind === 'stock_adjustment');
                if (($kind === 'stock_adjustment' && ($quantity->isZero() || ($data['adjustment_type'] === 'damaged' && ! $quantity->isNegative()))) || ($kind === 'stock_count' && $quantity->isNegative())) {
                    throw ValidationException::withMessages(['quantity' => 'Enter a nonzero correction, a negative damage quantity, or a nonnegative physical count.']);
                }
            }
            $previous = ApprovalRequest::where('source_key', $data['idempotency_key'])->first();
            if ($kind === 'stock_count') {
                $data['balance_at_request'] = $previous?->payload['balance_at_request'] ?? (string) app(StockWorkflowService::class)->balance((int) $data['spare_part_id'], (int) $data['location_id']);
            }
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            if ($old = ApprovalRequest::where('source_key', $data['idempotency_key'])->first()) {
                if ((int) $old->requested_by_user_id !== (int) $actor->id || ! hash_equals($old->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This request key is already used for different details.']);
                }

                return $old;
            }
            $approval = ApprovalRequest::create([
                'source_key' => $data['idempotency_key'], 'kind' => $kind, 'status' => 'pending',
                'requested_by_user_id' => $actor->id, 'reason' => $data['reason'], 'payload' => $data, 'payload_hash' => $hash,
                'amount' => $data['amount'] ?? null, 'payment_id' => $data['payment_id'] ?? null,
                'spare_part_id' => $data['spare_part_id'] ?? null,
            ]);
            BusinessAudit::record($actor, 'approval.requested', ApprovalRequest::class, $approval->id, newValues: ['kind' => $kind, 'reason' => $approval->reason, 'amount' => $approval->amount]);

            return $approval;
        }, 3);
    }

    public const CHANGE_FIELDS = [
        'customer_update' => ['customer_type', 'customer_name', 'company_name', 'contact_person', 'address', 'mobile_number', 'alternate_phone', 'email', 'special_notes'],
        'job_update' => ['job_type', 'item_name', 'brand', 'model', 'item_description', 'reported_fault', 'accessories_received', 'physical_condition', 'visible_damage', 'special_notes'],
    ];

    /**
     * Stores an edit proposed by a user without direct edit rights so an administrator can approve it.
     *
     * @param  array<string, mixed>  $changes
     */
    public function submitChange(string $kind, Customer|RepairJob $subject, array $changes, string $reason, string $key, User $actor): ApprovalRequest
    {
        abort_unless(ApprovalAccess::canRequestChange($actor, $kind), 403);

        $fields = self::CHANGE_FIELDS[$kind];
        $proposed = [];
        foreach ($fields as $field) {
            $value = $changes[$field] ?? null;
            $proposed[$field] = is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
        }

        $payload = [
            'subject_id' => $subject->getKey(),
            'subject_label' => $subject instanceof Customer ? $subject->customer_code.' · '.$subject->display_name : $subject->automatic_number,
            'original' => $this->snapshot($subject, $fields),
            'changes' => $proposed,
            'idempotency_key' => $key,
            'reason' => $reason,
        ];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($kind, $key, $actor, $payload, $hash, $reason): ApprovalRequest {
            if ($old = ApprovalRequest::where('source_key', $key)->first()) {
                if ((int) $old->requested_by_user_id !== (int) $actor->id || $old->kind !== $kind) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This request key is already used for different details.']);
                }

                return $old;
            }
            $approval = ApprovalRequest::create([
                'source_key' => $key, 'kind' => $kind, 'status' => 'pending',
                'requested_by_user_id' => $actor->id, 'reason' => $reason, 'payload' => $payload, 'payload_hash' => $hash,
            ]);
            BusinessAudit::record($actor, 'approval.requested', ApprovalRequest::class, $approval->id, newValues: ['kind' => $kind, 'reason' => $reason]);

            return $approval;
        }, 3);
    }

    /** Stores a request from a user without delete rights so an administrator can approve removing a repair job. */
    public function submitDelete(RepairJob $job, string $reason, string $key, User $actor): ApprovalRequest
    {
        abort_unless(ApprovalAccess::canRequestChange($actor, 'job_delete'), 403);

        $blockers = app(RepairJobService::class)->deletionBlockers($job);
        if ($blockers !== []) {
            throw ValidationException::withMessages(['reason' => 'This job cannot be deleted because it has '.implode(', ', $blockers).'. Cancel the job instead.']);
        }

        $payload = [
            'subject_id' => $job->getKey(),
            'subject_label' => $job->automatic_number.' · '.$job->item_name,
            'idempotency_key' => $key,
            'reason' => $reason,
        ];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($key, $actor, $payload, $hash, $reason): ApprovalRequest {
            if ($old = ApprovalRequest::where('source_key', $key)->first()) {
                if ((int) $old->requested_by_user_id !== (int) $actor->id || $old->kind !== 'job_delete') {
                    throw ValidationException::withMessages(['idempotency_key' => 'This request key is already used for different details.']);
                }

                return $old;
            }
            $approval = ApprovalRequest::create([
                'source_key' => $key, 'kind' => 'job_delete', 'status' => 'pending',
                'requested_by_user_id' => $actor->id, 'reason' => $reason, 'payload' => $payload, 'payload_hash' => $hash,
            ]);
            BusinessAudit::record($actor, 'approval.requested', ApprovalRequest::class, $approval->id, newValues: ['kind' => 'job_delete', 'reason' => $reason]);

            return $approval;
        }, 3);
    }

    /** @param  list<string>  $fields */
    private function snapshot(Customer|RepairJob $subject, array $fields): array
    {
        return json_decode(json_encode($subject->only($fields)), true);
    }

    private function applyChange(ApprovalRequest $approval, User $actor): void
    {
        $payload = $approval->payload;
        $fields = self::CHANGE_FIELDS[$approval->kind];

        if ($approval->kind === 'customer_update') {
            $subject = Customer::query()->whereKey($payload['subject_id'])->lockForUpdate()->firstOrFail();
        } else {
            $subject = RepairJob::query()->whereKey($payload['subject_id'])->lockForUpdate()->firstOrFail();
        }

        if ($this->snapshot($subject, $fields) != $payload['original']) {
            throw ValidationException::withMessages(['decision' => 'This record changed after the edit was requested. Reject it and ask for a fresh request.']);
        }

        if ($approval->kind === 'customer_update') {
            app(CustomerService::class)->update($subject, $payload['changes'], $actor);
        } else {
            app(RepairJobService::class)->updateDetails($subject, $payload['changes'], $actor);
        }
    }

    public function decide(ApprovalRequest $approval, string $decision, string $comment, User $actor): ApprovalRequest
    {
        $this->validateDecision($decision, $comment);
        abort_unless(ApprovalAccess::canReview($actor, $approval->kind), 403);
        abort_if((int) $approval->requested_by_user_id === (int) $actor->id, 403, 'Another authorized user must review your request.');

        return DB::transaction(function () use ($approval, $decision, $comment, $actor) {
            $approval = ApprovalRequest::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if ($approval->status !== 'pending') {
                if ($approval->status !== $decision) {
                    throw ValidationException::withMessages(['decision' => 'This request has already been decided.']);
                }

                return $approval;
            }
            if ($decision === 'approved') {
                $data = $approval->payload;
                $data['idempotency_key'] = $approval->source_key;
                $data['reason'] = $approval->reason;
                if ($approval->kind === 'stock_count') {
                    $stock = app(StockWorkflowService::class);
                    $stock->lockPart((int) $data['spare_part_id'], (int) $data['location_id']);
                    if ($stock->balance((int) $data['spare_part_id'], (int) $data['location_id'])->compareTo($data['balance_at_request']) !== 0) {
                        throw ValidationException::withMessages(['quantity' => 'Stock changed after this count was requested. Reject it and request a fresh count.']);
                    }
                }
                match ($approval->kind) {
                    'payment_refund', 'payment_reversal' => app(PaymentService::class)->reverse(Payment::findOrFail($approval->payment_id), $data + ['reversal_type' => $approval->kind === 'payment_refund' ? 'refund' : 'reversal'], $actor),
                    'stock_adjustment' => app(StockWorkflowService::class)->adjust($data, $actor),
                    'stock_count' => app(StockWorkflowService::class)->count($data, $actor),
                    'customer_update', 'job_update' => $this->applyChange($approval, $actor),
                    'job_delete' => app(RepairJobService::class)->delete(
                        RepairJob::query()->find($data['subject_id']) ?? throw ValidationException::withMessages(['decision' => 'This repair job no longer exists. Reject the request.']),
                        $actor,
                    ),
                    default => throw ValidationException::withMessages(['kind' => 'Unsupported request type.']),
                };
            }

            return $this->recordDecision($approval, $decision, $comment, $actor);
        }, 3);
    }

    public function decideDocument(string $kind, int $id, string $decision, string $comment, User $actor): ApprovalRequest
    {
        $this->validateDecision($decision, $comment);
        abort_unless($kind === 'purchase' && ApprovalAccess::canReview($actor, $kind), 403);

        return DB::transaction(function () use ($id, $decision, $comment, $actor) {
            $document = Purchase::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($old = ApprovalRequest::where('source_key', 'purchase:'.$id)->first()) {
                if ($old->status !== $decision) {
                    throw ValidationException::withMessages(['decision' => 'This document has already been decided.']);
                }

                return $old;
            }
            if ($document->status->value !== 'draft') {
                throw ValidationException::withMessages(['decision' => 'This document is no longer awaiting approval.']);
            }
            $payload = ['reference' => $document->purchase_number, 'total' => $document->total];
            $approval = ApprovalRequest::create([
                'source_key' => 'purchase:'.$id, 'kind' => 'purchase', 'status' => 'pending',
                'requested_by_user_id' => $document->created_by_user_id, 'purchase_id' => $id,
                'amount' => $document->total, 'reason' => $document->notes ?: 'Review '.$payload['reference'],
                'payload' => $payload, 'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            ]);
            if ($decision === 'approved') {
                app(PurchaseWorkflowService::class)->finalize($document, $actor);
            } else {
                $document->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $comment]);
                BusinessAudit::record($actor, 'purchase.rejected', Purchase::class, $document->id, newValues: ['reason' => $comment]);
            }

            return $this->recordDecision($approval, $decision, $comment, $actor);
        }, 3);
    }

    private function validateDecision(string $decision, string $comment): void
    {
        validator(['decision' => $decision, 'comment' => $comment], ['decision' => 'required|in:approved,rejected', 'comment' => 'required|string|min:5|max:2000'])->validate();
    }

    private function recordDecision(ApprovalRequest $approval, string $decision, string $comment, User $actor): ApprovalRequest
    {
        $approval->update(['status' => $decision, 'decided_by_user_id' => $actor->id, 'decided_at' => now(), 'decision_comment' => $comment]);
        BusinessAudit::record($actor, 'approval.'.$decision, ApprovalRequest::class, $approval->id, ['status' => 'pending'], ['kind' => $approval->kind, 'status' => $decision, 'comment' => $comment, 'requested_by_user_id' => $approval->requested_by_user_id]);

        return $approval;
    }
}
