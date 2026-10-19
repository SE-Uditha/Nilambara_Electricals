<?php

namespace App\Modules\Quotations\Services;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Enums\JobStatus;
use App\Enums\QuotationPreparationStatus;
use App\Models\User;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Quotations\Models\Quotation;
use App\Modules\Quotations\Models\QuotationHistory;
use App\Modules\Quotations\Models\QuotationItem;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\RepairJobs\Services\RepairJobService;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(
        private readonly QuotationCalculationService $calculator,
        private readonly RepairJobService $repairJobs,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Quotation
    {
        return DB::transaction(function () use ($data, $user): Quotation {
            $job = RepairJob::query()->whereKey($data['repair_job_id'])->lockForUpdate()->firstOrFail();
            $this->ensureJobIsQuoteable($job);

            $quotation = Quotation::query()->create([
                'quotation_number' => 'TEMP-'.Str::uuid(),
                'revision' => 1,
                'quotation_date' => $data['quotation_date'],
                'repair_job_id' => $job->getKey(),
                'customer_id' => $job->customer_id,
                'proposed_repair_description' => trim($data['proposed_repair_description']),
                'document_snapshot' => [],
                'labour_charge' => '0.00',
                'parts_total' => '0.00',
                'subtotal' => '0.00',
                'discount_amount' => '0.00',
                'total' => '0.00',
                'valid_until' => $data['valid_until'],
                'terms_and_conditions' => $this->nullableTrim($data['terms_and_conditions'] ?? null),
                'approval_status' => ApprovalStatus::Pending->value,
                'status' => DocumentStatus::Draft->value,
                'preparation_status' => QuotationPreparationStatus::Draft->value,
                'created_by_user_id' => $user->getKey(),
                'updated_by_user_id' => $user->getKey(),
            ]);
            $quotation->quotation_number = $this->numberFor((int) $quotation->getKey());
            DB::table('quotations')->where('id', $quotation->getKey())->update([
                'quotation_number' => $quotation->quotation_number,
            ]);
            $quotation->syncOriginalAttribute('quotation_number');

            $this->replaceDraftItems($quotation, $data['items'], $data['labour_charge']);
            $quotation->refresh();
            $quotation->document_snapshot = $this->snapshot($quotation);
            $quotation->save();
            $this->recordHistory($quotation, $user, 'created', newValues: $this->summary($quotation));
            BusinessAudit::record(
                $user,
                'quotation.created',
                Quotation::class,
                (int) $quotation->getKey(),
                newValues: $this->summary($quotation),
            );

            if (($data['intent'] ?? 'draft') === 'issue') {
                return $this->issueLocked($quotation, $job, $user);
            }

            return $quotation->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(Quotation $quotation, array $data, User $user): Quotation
    {
        return DB::transaction(function () use ($quotation, $data, $user): Quotation {
            $job = RepairJob::query()->whereKey($quotation->repair_job_id)->lockForUpdate()->firstOrFail();
            $locked = Quotation::query()->whereKey($quotation->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureDraft($locked);
            $this->ensureJobIsQuoteable($job);
            $old = $this->summary($locked->load('items'));
            $locked->quotation_date = $data['quotation_date'];
            $locked->valid_until = $data['valid_until'];
            $locked->proposed_repair_description = trim($data['proposed_repair_description']);
            $locked->terms_and_conditions = $this->nullableTrim($data['terms_and_conditions'] ?? null);
            $locked->updated_by_user_id = $user->getKey();
            $this->replaceDraftItems($locked, $data['items'], $data['labour_charge']);
            $locked->refresh();
            $locked->document_snapshot = $this->snapshot($locked);
            $locked->save();
            $new = $this->summary($locked->load('items'));
            $this->recordHistory($locked, $user, 'draft_edited', $old, $new);
            BusinessAudit::record($user, 'quotation.draft_edited', Quotation::class, (int) $locked->getKey(), $old, $new);

            if (($data['intent'] ?? 'draft') === 'issue') {
                return $this->issueLocked($locked, $job, $user);
            }

            return $locked->refresh();
        });
    }

    public function issue(Quotation $quotation, User $user): Quotation
    {
        return DB::transaction(function () use ($quotation, $user): Quotation {
            $job = RepairJob::query()->whereKey($quotation->repair_job_id)->lockForUpdate()->firstOrFail();
            $locked = Quotation::query()->whereKey($quotation->getKey())->lockForUpdate()->firstOrFail();

            return $this->issueLocked($locked, $job, $user);
        });
    }

    public function createRevision(Quotation $source, User $user): Quotation
    {
        return DB::transaction(function () use ($source, $user): Quotation {
            $job = RepairJob::query()->whereKey($source->repair_job_id)->lockForUpdate()->firstOrFail();
            $locked = Quotation::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->preparation_status !== QuotationPreparationStatus::Issued) {
                throw ValidationException::withMessages(['quotation' => __('ui.quotation_revision_requires_issued')]);
            }

            if ($locked->revisions()->exists()) {
                throw ValidationException::withMessages(['quotation' => __('ui.quotation_revision_already_exists')]);
            }

            $this->ensureJobIsQuoteable($job);
            $revisionDate = now(config('business.timezone', 'Asia/Colombo'));
            $validUntil = $locked->valid_until;
            if ($validUntil === null || $validUntil->lt($revisionDate->startOfDay())) {
                $validUntil = $revisionDate->copy()->addDays(14)->toDateString();
            }
            $data = [
                'quotation_number' => 'TEMP-'.Str::uuid(),
                'revision' => $locked->revision + 1,
                'revises_quotation_id' => $locked->getKey(),
                'quotation_date' => $revisionDate->toDateString(),
                'repair_job_id' => $job->getKey(),
                'customer_id' => $locked->customer_id,
                'proposed_repair_description' => $locked->proposed_repair_description,
                'document_snapshot' => [],
                'labour_charge' => '0.00',
                'parts_total' => '0.00',
                'subtotal' => '0.00',
                'discount_amount' => '0.00',
                'total' => '0.00',
                'valid_until' => $validUntil,
                'terms_and_conditions' => $locked->terms_and_conditions,
                'approval_status' => ApprovalStatus::Pending->value,
                'status' => DocumentStatus::Draft->value,
                'preparation_status' => QuotationPreparationStatus::Draft->value,
                'created_by_user_id' => $user->getKey(),
                'updated_by_user_id' => $user->getKey(),
            ];
            $revision = Quotation::query()->create($data);
            $revision->quotation_number = $this->numberFor((int) $revision->getKey());
            DB::table('quotations')->where('id', $revision->getKey())->update([
                'quotation_number' => $revision->quotation_number,
            ]);
            $revision->syncOriginalAttribute('quotation_number');
            $sourceItems = $locked->items()->orderBy('display_order')->orderBy('line_number')->get();
            $this->replaceDraftItems($revision, $sourceItems->map(fn (QuotationItem $item): array => [
                'spare_part_id' => $item->spare_part_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])->all(), (string) $locked->labour_charge);
            $revision->refresh();
            $revision->document_snapshot = $this->snapshot($revision);
            $revision->save();
            $this->recordHistory($revision, $user, 'revision_created', newValues: [
                ...$this->summary($revision),
                'revises_quotation_id' => $locked->getKey(),
                'revises_quotation_number' => $locked->quotation_number,
            ]);
            $this->recordHistory($locked, $user, 'revision_created', newValues: [
                'revision_quotation_id' => $revision->getKey(),
                'revision_quotation_number' => $revision->quotation_number,
            ]);
            BusinessAudit::record($user, 'quotation.revision_created', Quotation::class, (int) $revision->getKey(), [
                'revises_quotation_id' => $locked->getKey(),
                'revises_quotation_number' => $locked->quotation_number,
            ], $this->summary($revision));

            return $revision;
        });
    }

    public function cancel(Quotation $quotation, User $user, ?string $notes = null): Quotation
    {
        return DB::transaction(function () use ($quotation, $user, $notes): Quotation {
            RepairJob::query()->whereKey($quotation->repair_job_id)->lockForUpdate()->firstOrFail();
            $locked = Quotation::query()->whereKey($quotation->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->preparation_status, [QuotationPreparationStatus::Draft, QuotationPreparationStatus::Issued], true)
                || $locked->approval_status === ApprovalStatus::Approved) {
                throw ValidationException::withMessages(['quotation' => __('ui.quotation_cannot_cancel')]);
            }

            $old = $this->summary($locked);
            $locked->preparation_status = QuotationPreparationStatus::Cancelled;
            $locked->status = DocumentStatus::Cancelled;
            $locked->updated_by_user_id = $user->getKey();
            $locked->save();
            $new = [...$this->summary($locked), 'notes' => $this->nullableTrim($notes)];
            $this->recordHistory($locked, $user, 'cancelled', $old, $new);
            BusinessAudit::record($user, 'quotation.cancelled', Quotation::class, (int) $locked->getKey(), $old, $new);

            return $locked->refresh();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $submittedItems
     */
    private function replaceDraftItems(Quotation $quotation, array $submittedItems, string|int $labourCharge): void
    {
        $normalized = [];

        foreach ($submittedItems as $index => $item) {
            $part = null;
            if (! empty($item['spare_part_id'])) {
                $part = SparePart::query()
                    ->whereKey($item['spare_part_id'])
                    ->where('is_active', true)
                    ->first();

                if ($part === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.spare_part_id" => __('ui.active_spare_part_required'),
                    ]);
                }
            }

            $normalized[] = [
                'spare_part_id' => $part?->getKey(),
                'description' => $part?->name ?? trim((string) ($item['description'] ?? '')),
                'quantity' => (string) $item['quantity'],
                'unit_price' => $part?->selling_price ?? (string) $item['unit_price'],
                'discount_amount' => '0.00',
                'item_type' => 'part',
            ];
        }

        $calculation = $this->calculator->calculate($normalized, $labourCharge);
        $existingItems = $quotation->items()->whereNull('removed_at')
            ->orderBy('display_order')->orderBy('line_number')->get();
        $maxLine = (int) $quotation->items()->max('line_number');

        foreach ($calculation['items'] as $index => $itemData) {
            $item = $existingItems->get($index);
            $isNew = $item === null;
            if ($item === null) {
                $item = new QuotationItem(['line_number' => ++$maxLine]);
            }

            $item->fill([
                'display_order' => $index + 1,
                'item_type' => $itemData['item_type'],
                'spare_part_id' => $itemData['spare_part_id'],
                'description' => $itemData['description'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'discount_amount' => '0.00',
                'line_total' => $itemData['line_total'],
                'removed_at' => null,
            ]);
            if ($isNew) {
                $quotation->items()->save($item);
            } else {
                $item->save();
            }
        }

        foreach ($existingItems->slice(count($calculation['items'])) as $removedItem) {
            $removedItem->removed_at = now();
            $removedItem->save();
        }

        $quotation->labour_charge = $calculation['labour_charge'];
        $quotation->parts_total = $calculation['parts_total'];
        $quotation->subtotal = (string) BigDecimal::of($calculation['parts_total'])
            ->plus(BigDecimal::of($calculation['labour_charge']))->toScale(2);
        $quotation->discount_amount = '0.00';
        $quotation->total = $quotation->subtotal;
        $quotation->save();
    }

    private function issueLocked(Quotation $quotation, RepairJob $job, User $user): Quotation
    {
        if ($quotation->preparation_status !== QuotationPreparationStatus::Draft) {
            throw ValidationException::withMessages(['quotation' => __('ui.quotation_not_draft')]);
        }
        $this->ensureJobIsQuoteable($job);

        $quotation->load(['customer', 'repairJob.jobInspections', 'items' => fn ($query) => $query
            ->whereNull('removed_at')->orderBy('display_order')->orderBy('line_number')]);
        if ($quotation->items->isEmpty()) {
            throw ValidationException::withMessages(['items' => __('ui.quotation_items_required')]);
        }

        $today = now(config('business.timezone', 'Asia/Colombo'))->toDateString();
        if ($quotation->valid_until === null || $quotation->valid_until->toDateString() < $today) {
            throw ValidationException::withMessages(['valid_until' => __('ui.quotation_expiry_must_be_future')]);
        }

        $old = $this->summary($quotation);
        $issuedAt = now(config('business.timezone', 'Asia/Colombo'));
        $quotation->preparation_status = QuotationPreparationStatus::Issued;
        $quotation->status = DocumentStatus::Finalized;
        $quotation->approval_status = ApprovalStatus::Pending;
        $quotation->issued_at = $issuedAt;
        $quotation->updated_by_user_id = $user->getKey();
        $quotation->document_snapshot = $this->snapshot($quotation, $issuedAt);
        $quotation->save();

        $previousQuotes = Quotation::query()->where('repair_job_id', $job->getKey())
            ->where('id', '!=', $quotation->getKey())
            ->where('preparation_status', QuotationPreparationStatus::Issued->value)
            ->lockForUpdate()
            ->get();
        foreach ($previousQuotes as $previous) {
            $oldPrevious = $this->summary($previous);
            $previous->preparation_status = QuotationPreparationStatus::Superseded;
            $previous->save();
            $newPrevious = $this->summary($previous);
            $this->recordHistory($previous, $user, 'superseded', $oldPrevious, [
                ...$newPrevious,
                'replaced_by_quotation_id' => $quotation->getKey(),
                'replaced_by_quotation_number' => $quotation->quotation_number,
            ]);
            BusinessAudit::record($user, 'quotation.superseded', Quotation::class, (int) $previous->getKey(), $oldPrevious, $newPrevious);
        }

        $previousJobApproval = $job->approval_status?->value;
        $job->approval_status = ApprovalStatus::Pending;
        $job->save();
        if ($previousJobApproval !== ApprovalStatus::Pending->value) {
            BusinessAudit::record(
                $user,
                'repair_job.quotation_approval_reset',
                RepairJob::class,
                (int) $job->getKey(),
                ['approval_status' => $previousJobApproval],
                [
                    'approval_status' => ApprovalStatus::Pending->value,
                    'quotation_id' => $quotation->getKey(),
                ],
            );
        }
        if ($job->status !== JobStatus::WaitingForCustomerApproval) {
            $job = $this->repairJobs->updateStatus($job, [
                'status' => JobStatus::WaitingForCustomerApproval->value,
                'notes' => __('ui.quotation_waiting_status_note', [
                    'quotation' => $quotation->quotation_number,
                ]),
            ], $user);
        }

        $new = $this->summary($quotation);
        $this->recordHistory($quotation, $user, 'issued', $old, $new);
        BusinessAudit::record($user, 'quotation.issued', Quotation::class, (int) $quotation->getKey(), $old, $new);

        return $quotation->refresh();
    }

    private function snapshot(Quotation $quotation, ?CarbonInterface $issuedAt = null): array
    {
        $quotation->loadMissing(['customer', 'repairJob.jobInspections', 'items' => fn ($query) => $query
            ->whereNull('removed_at')->orderBy('display_order')->orderBy('line_number')]);
        $job = $quotation->repairJob;
        $inspection = $job->jobInspections->sortByDesc('inspected_at')->first();

        return [
            'quotation_number' => $quotation->quotation_number,
            'revision' => $quotation->revision,
            'quotation_date' => $quotation->quotation_date?->toDateString(),
            'valid_until' => $quotation->valid_until?->toDateString(),
            'job_number' => $job->job_number,
            'automatic_job_number' => $job->automatic_number,
            'customer' => [
                'customer_code' => $quotation->customer->customer_code,
                'name' => $quotation->customer->display_name,
                'address' => $quotation->customer->address,
                'mobile_number' => $quotation->customer->mobile_number,
            ],
            'item' => [
                'name' => $job->item_name,
                'brand' => $job->brand,
                'model' => $job->model,
                'description' => $job->item_description,
                'reported_fault' => $job->reported_fault,
                'inspection_summary' => $inspection?->inspection_result,
                'technical_information' => $inspection?->technical_information,
            ],
            'proposed_repair_description' => $quotation->proposed_repair_description,
            'items' => $quotation->items->map(fn (QuotationItem $item): array => [
                'description' => $item->description,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'line_total' => (string) $item->line_total,
            ])->values()->all(),
            'parts_total' => (string) $quotation->parts_total,
            'labour_charge' => (string) $quotation->labour_charge,
            'discount_amount' => (string) $quotation->discount_amount,
            'quotation_total' => (string) $quotation->total,
            'terms_and_conditions' => $quotation->terms_and_conditions,
            'prepared_by' => $quotation->createdByUser?->name,
            'issued_at' => $issuedAt?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Quotation $quotation): array
    {
        return [
            'quotation_number' => $quotation->quotation_number,
            'repair_job_id' => $quotation->repair_job_id,
            'preparation_status' => $quotation->preparation_status?->value,
            'approval_status' => $quotation->approval_status?->value,
            'parts_total' => (string) $quotation->parts_total,
            'labour_charge' => (string) $quotation->labour_charge,
            'total' => (string) $quotation->total,
            'revision' => $quotation->revision,
            'items' => $quotation->items
                ->filter(fn (QuotationItem $item): bool => $item->removed_at === null)
                ->sortBy('display_order')
                ->map(fn (QuotationItem $item): array => [
                    'description' => $item->description,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'line_total' => (string) $item->line_total,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordHistory(
        Quotation $quotation,
        User $user,
        string $event,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): void {
        QuotationHistory::query()->create([
            'quotation_id' => $quotation->getKey(),
            'actor_user_id' => $user->getKey(),
            'event_type' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'event_at' => now(),
        ]);
    }

    private function ensureDraft(Quotation $quotation): void
    {
        if ($quotation->preparation_status !== QuotationPreparationStatus::Draft) {
            throw ValidationException::withMessages(['quotation' => __('ui.quotation_not_draft')]);
        }
    }

    private function ensureJobIsQuoteable(RepairJob $job): void
    {
        if ($job->status === JobStatus::Cancelled || $job->delivered_at !== null) {
            throw ValidationException::withMessages(['repair_job_id' => __('ui.quotation_job_not_quoteable')]);
        }
    }

    private function numberFor(int $id): string
    {
        $prefix = (string) config('business.quotation_number_prefix', 'QUO-');

        return $prefix.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
