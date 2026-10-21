<?php

namespace App\Modules\Inventory\Services;

use App\Models\User;
use App\Modules\Inventory\Models\JobPartUsage;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockCount;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReturn;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StockWorkflowService
{
    public function quantity(SparePart $part, string $value, bool $signed = false): BigDecimal
    {
        Validator::make(['quantity' => $value], ['quantity' => ['required', 'numeric', 'decimal:0,3', 'between:-999999999999.999,999999999999.999']])->validate();
        $quantity = BigDecimal::of($value)->toScale(3);
        if ((! $signed && $quantity->isNegative()) || $quantity->compareTo($quantity->toScale($part->unit->quantity_scale, RoundingMode::Down)) !== 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must match the unit precision and allowed sign.']);
        }

        return $quantity;
    }

    public function balance(int $part, int $location): BigDecimal
    {
        return DB::table('stock_movements')->where('spare_part_id', $part)->where('location_id', $location)
            ->lockForUpdate()->pluck('quantity_delta')->reduce(fn (BigDecimal $sum, $value) => $sum->plus((string) $value), BigDecimal::zero())->toScale(3);
    }

    public function lockPart(int $part, int $location): SparePart
    {
        $model = SparePart::whereKey($part)->lockForUpdate()->firstOrFail();
        if (! $model->is_active || ! StockLocation::whereKey($location)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['spare_part_id' => 'Select an active part and location.']);
        }

        return $model;
    }

    public function register(array $data, User $actor): SparePart
    {
        Gate::forUser($actor)->authorize('inventory.create');

        return DB::transaction(function () use ($data, $actor) {
            $opening = $data['opening_quantity'] ?? '0';
            $openingCost = $data['opening_unit_cost'] ?? null;
            unset($data['opening_quantity'], $data['opening_unit_cost']);
            $part = SparePart::create($data);
            $quantity = $this->quantity($part, (string) $opening);
            if ($quantity->isPositive()) {
                $this->lockPart($part->id, $part->default_location_id);
                $this->movement($part->id, $part->default_location_id, 'opening', $quantity, $actor, 'OPEN-'.$part->sku, 'Opening stock', 'opening:'.$part->id, ['unit_cost' => $openingCost]);
            }
            BusinessAudit::record($actor, 'inventory.part.created', SparePart::class, $part->id, newValues: ['sku' => $part->sku, 'opening_quantity' => (string) $quantity]);

            return $part;
        }, 3);
    }

    public function request(array $data, User $actor): JobPartUsage
    {
        return DB::transaction(function () use ($data, $actor) {
            $job = RepairJob::whereKey($data['repair_job_id'])->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('repair', $job);
            $this->assertUnbilled($job);
            $part = $this->lockPart($data['spare_part_id'], $data['location_id']);
            $quantity = $this->quantity($part, (string) $data['quantity']);
            if (! $quantity->isPositive()) {
                throw ValidationException::withMessages(['quantity' => 'Request a positive quantity.']);
            }
            $payload = ['repair_job_id' => $job->id, 'spare_part_id' => $part->id, 'location_id' => (int) $data['location_id'], 'quantity' => (string) $quantity, 'requested_by_user_id' => $actor->id, 'notes' => trim($data['notes'] ?? '')];
            if ($old = JobPartUsage::where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) {
                $this->same($old, $payload);

                return $old;
            }
            $usage = JobPartUsage::create($payload + ['status' => 'draft', 'unit_price' => $part->selling_price, 'unit_cost' => null, 'idempotency_key' => $data['idempotency_key']]);
            BusinessAudit::record($actor, 'inventory.part.requested', JobPartUsage::class, $usage->id, newValues: $payload);

            return $usage;
        }, 3);
    }

    public function issue(JobPartUsage $usage, User $actor): JobPartUsage
    {
        Gate::forUser($actor)->authorize('inventory.issue');

        return DB::transaction(function () use ($usage, $actor) {
            // Job then part: a consistent product lock protects the shared ledger.
            $job = RepairJob::whereKey($usage->repair_job_id)->lockForUpdate()->firstOrFail();
            $usage = JobPartUsage::whereKey($usage->id)->lockForUpdate()->firstOrFail();
            if ($usage->status === 'issued') {
                return $usage;
            }
            $this->assertUnbilled($job);
            if (! in_array($usage->status, ['draft', 'approved'], true)) {
                throw ValidationException::withMessages(['usage' => 'Only an open request can be issued.']);
            }
            $part = $this->lockPart($usage->spare_part_id, $usage->location_id);
            $quantity = BigDecimal::of($usage->quantity);
            $this->ensureAvailable($part->id, $usage->location_id, $quantity);
            $quote = $job->quotations()->where('preparation_status', 'issued')->orderByDesc('revision')->orderByDesc('id')->first();
            if ($quote && $quote->approval_status->value !== 'approved') {
                throw ValidationException::withMessages(['usage' => 'Customer quotation approval is required before issuing repair parts.']);
            }
            $cost = app(InventoryService::class)->latestUnitCost($part->id, $usage->location_id);
            $usage->fill(['status' => 'issued', 'unit_cost' => $cost, 'approved_at' => now(), 'approved_by_user_id' => $actor->id])->save();
            $this->movement($part->id, $usage->location_id, 'job_issue', $quantity->negated(), $actor, $job->job_number, 'Approved repair part issue', 'job-issue:'.$usage->id, ['job_part_usage_id' => $usage->id, 'unit_cost' => $cost]);
            if (! $quote) {
                $parts = BigDecimal::of($job->parts_charge)->plus($quantity->multipliedBy($usage->unit_price)->toScale(2, RoundingMode::HalfUp));
                $job->fill(['parts_charge' => (string) $parts, 'total_job_amount' => (string) $parts->plus($job->labour_charge)->toScale(2)])->save();
            }
            BusinessAudit::record($actor, 'inventory.part.issued', JobPartUsage::class, $usage->id, newValues: ['quantity' => $usage->quantity, 'job_number' => $job->job_number]);

            return $usage;
        }, 3);
    }

    public function adjust(array $data, User $actor): StockAdjustment
    {
        Gate::forUser($actor)->authorize('inventory.adjust');

        return DB::transaction(function () use ($data, $actor) {
            $part = $this->lockPart($data['spare_part_id'], $data['location_id']);
            $delta = $this->quantity($part, (string) $data['quantity'], true);
            if ($delta->isZero() || ($data['adjustment_type'] === 'damaged' && ! $delta->isNegative())) {
                throw ValidationException::withMessages(['quantity' => 'Enter a nonzero correction, or a negative damage quantity.']);
            }
            $number = 'ADJ-'.$data['idempotency_key'];
            if ($old = StockAdjustment::where('adjustment_number', $number)->lockForUpdate()->first()) {
                $this->same($old, ['created_by_user_id' => $actor->id, 'reason' => $data['reason'], 'adjustment_type' => $data['adjustment_type']]);
                $this->same($old->items()->sole(), ['spare_part_id' => $part->id, 'location_id' => $data['location_id'], 'quantity_delta' => (string) $delta]);

                return $old;
            }
            if ($delta->isNegative()) {
                $this->ensureAvailable($part->id, $data['location_id'], $delta->abs());
            }
            $header = StockAdjustment::create(['adjustment_number' => $number, 'adjusted_at' => now(), 'adjustment_type' => $data['adjustment_type'], 'reason' => $data['reason'], 'created_by_user_id' => $actor->id, 'status' => 'finalized', 'approved_by_user_id' => $actor->id, 'approved_at' => now()]);
            $line = $header->items()->create(['spare_part_id' => $part->id, 'location_id' => $data['location_id'], 'quantity_delta' => (string) $delta]);
            $this->movement($part->id, $data['location_id'], $data['adjustment_type'] === 'damaged' ? 'damaged' : 'adjustment', $delta, $actor, $number, $data['reason'], 'adjust:'.$data['idempotency_key'], ['adjustment_item_id' => $line->id]);
            BusinessAudit::record($actor, 'inventory.stock.adjusted', StockAdjustment::class, $header->id, newValues: ['quantity_delta' => (string) $delta, 'reason' => $data['reason']]);

            return $header;
        }, 3);
    }

    public function cancelUsage(JobPartUsage $usage, string $reason, User $actor): JobPartUsage
    {
        Gate::forUser($actor)->authorize('inventory.issue');

        return DB::transaction(function () use ($usage, $reason, $actor) {
            $job = RepairJob::whereKey($usage->repair_job_id)->lockForUpdate()->firstOrFail();
            $usage = JobPartUsage::whereKey($usage->id)->lockForUpdate()->firstOrFail();
            if ($usage->status === 'cancelled') {
                return $usage;
            }
            $this->assertUnbilled($job);
            if ($usage->status === 'issued') {
                $part = $this->lockPart($usage->spare_part_id, $usage->location_id);
                $return = StockReturn::create(['return_number' => 'RPR-'.$usage->id, 'return_type' => 'repair_return', 'repair_job_id' => $job->id, 'returned_at' => now(), 'recorded_by_user_id' => $actor->id, 'status' => 'finalized', 'reason' => $reason, 'idempotency_key' => 'repair-return:'.$usage->id]);
                $item = $return->items()->create(['spare_part_id' => $part->id, 'location_id' => $usage->location_id, 'quantity' => $usage->quantity, 'unit_cost' => $usage->unit_cost, 'condition' => 'resalable', 'notes' => 'Returned complete issue '.$usage->id]);
                $this->movement($part->id, $usage->location_id, 'customer_return', BigDecimal::of($usage->quantity), $actor, $return->return_number, $reason, 'repair-return:'.$usage->id, ['return_item_id' => $item->id, 'unit_cost' => $usage->unit_cost, 'reverses_movement_id' => $usage->stockMovements()->sole()->id]);
                // Preserve the agreed quotation price. Unquoted jobs remove only this issue's charge.
                if (! $job->quotations()->where('preparation_status', 'issued')->where('approval_status', 'approved')->exists()) {
                    $charge = BigDecimal::of($usage->quantity)->multipliedBy($usage->unit_price)->toScale(2, RoundingMode::HalfUp);
                    $parts = BigDecimal::of($job->parts_charge)->minus($charge);
                    if ($parts->isNegative()) {
                        throw ValidationException::withMessages(['usage' => 'Job charges changed since issue; reconcile them before returning this issue.']);
                    }
                    $job->fill(['parts_charge' => (string) $parts, 'total_job_amount' => (string) $parts->plus($job->labour_charge)->toScale(2)])->save();
                }
            }
            $usage->fill(['status' => 'cancelled'])->save();
            BusinessAudit::record($actor, 'inventory.part.cancelled', JobPartUsage::class, $usage->id, newValues: ['reason' => $reason]);

            return $usage;
        }, 3);
    }

    public function count(array $data, User $actor): StockCount
    {
        Gate::forUser($actor)->authorize('inventory.stock-count');

        return DB::transaction(function () use ($data, $actor) {
            $part = $this->lockPart($data['spare_part_id'], $data['location_id']);
            $quantity = $this->quantity($part, (string) $data['quantity']);
            $number = 'COUNT-'.$data['idempotency_key'];
            if ($old = StockCount::where('count_number', $number)->lockForUpdate()->first()) {
                $this->same($old, ['counted_by_user_id' => $actor->id, 'notes' => $data['reason']]);
                $this->same($old->items()->sole(), ['spare_part_id' => $part->id, 'location_id' => $data['location_id'], 'counted_quantity' => (string) $quantity]);

                return $old;
            }
            $expected = $this->balance($part->id, $data['location_id']);
            $header = StockCount::create(['count_number' => $number, 'counted_at' => now(), 'counted_by_user_id' => $actor->id, 'status' => 'finalized', 'notes' => $data['reason']]);
            $line = $header->items()->create(['spare_part_id' => $part->id, 'location_id' => $data['location_id'], 'expected_quantity' => (string) $expected, 'counted_quantity' => (string) $quantity]);
            $delta = $quantity->minus($expected);
            if (! $delta->isZero()) {
                $this->movement($part->id, $data['location_id'], 'count_correction', $delta, $actor, $number, $data['reason'], 'count:'.$data['idempotency_key'], ['count_item_id' => $line->id]);
            }
            BusinessAudit::record($actor, 'inventory.stock.counted', StockCount::class, $header->id, newValues: ['expected' => (string) $expected, 'counted' => (string) $quantity]);

            return $header;
        }, 3);
    }

    public function movement(int $part, int $location, string $type, BigDecimal $delta, User $actor, string $reference, string $reason, string $key, array $source = []): StockMovement
    {
        return StockMovement::create($source + ['spare_part_id' => $part, 'location_id' => $location, 'movement_type' => $type, 'quantity_delta' => (string) $delta->toScale(3), 'occurred_at' => now(), 'reference_number' => $reference, 'recorded_by_user_id' => $actor->id, 'reason' => $reason, 'idempotency_key' => $key]);
    }

    private function ensureAvailable(int $part, int $location, BigDecimal $quantity): void
    {
        if ($quantity->isGreaterThan($this->balance($part, $location))) {
            throw ValidationException::withMessages(['quantity' => 'Insufficient stock; refresh and try again.']);
        }
    }

    private function assertUnbilled(RepairJob $job): void
    {
        if ($job->invoiceJobs()->exists() || in_array($job->status->value, ['Cancelled', 'Delivered', 'Unable to Repair'], true)) {
            throw ValidationException::withMessages(['repair_job_id' => 'Parts cannot change for billed or closed jobs.']);
        }
    }

    private function same($model, array $payload): void
    {
        foreach ($payload as $key => $value) {
            if ((string) $model->$key !== (string) $value) {
                throw ValidationException::withMessages(['idempotency_key' => 'This request key was already used with different details.']);
            }
        }
    }
}
