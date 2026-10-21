<?php

namespace App\Modules\Approvals\Services;

use App\Models\User;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Inventory\Models\StockLocation;
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
