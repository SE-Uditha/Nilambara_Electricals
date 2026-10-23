<?php

namespace App\Modules\RepairJobs\Services;

use App\Enums\JobStatus;
use App\Models\User;
use App\Modules\Inventory\Services\StockWorkflowService;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class JobBillingService
{
    public function assertEditable(RepairJob $job, User $actor): void
    {
        Gate::forUser($actor)->authorize('view', $job);
        Gate::forUser($actor)->authorize('invoices.create');
        if (! in_array($job->status, [JobStatus::RepairCompleted, JobStatus::ReadyForCollection], true) || $job->cancelled_at || $job->invoiceJobs()->exists()) {
            throw ValidationException::withMessages(['billing' => 'Prepare the final bill only after work is completed and before an invoice exists.']);
        }
    }

    public function save(RepairJob $job, array $data, User $actor): RepairJob
    {
        return DB::transaction(function () use ($job, $data, $actor) {
            $job = RepairJob::whereKey($job->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($job, $actor);
            if ($job->quotations()->where('preparation_status', 'issued')->exists()) {
                throw ValidationException::withMessages(['billing' => 'This job has an issued quotation. Revise and approve the quotation before changing the agreed bill.']);
            }
            $before = $job->only(['labour_charge', 'parts_charge', 'total_job_amount']);
            $linesBefore = $job->billingCharges()->get()->toArray();
            $total = BigDecimal::of($data['labour_charge'])->toScale(2);
            foreach (array_values($data['charges'] ?? []) as $index => $line) {
                $amount = BigDecimal::of($line['quantity'])->multipliedBy($line['unit_price'])->toScale(2, RoundingMode::HalfUp);
                if ($amount->isGreaterThan('999999999999.99')) {
                    throw ValidationException::withMessages(['charges' => 'A charge exceeds the supported amount.']);
                }
                $job->billingCharges()->updateOrCreate(['line_number' => $index + 1], [
                    'description' => trim($line['description']), 'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'],
                    'line_total' => (string) $amount, 'removed_at' => null,
                ]);
                $total = $total->plus($amount);
            }
            $job->billingCharges()->where('line_number', '>', count($data['charges'] ?? []))->whereNull('removed_at')->update(['removed_at' => now()]);
            if ($total->plus($job->parts_charge)->isGreaterThan('999999999999.99')) {
                throw ValidationException::withMessages(['charges' => 'The total exceeds the supported amount.']);
            }
            $job->fill(['labour_charge' => (string) $total, 'total_job_amount' => (string) $total->plus($job->parts_charge)->toScale(2)])->save();
            $stock = app(StockWorkflowService::class);
            foreach (array_values($data['parts'] ?? []) as $index => $line) {
                $usage = $stock->request([
                    'repair_job_id' => $job->id, 'spare_part_id' => $line['spare_part_id'], 'location_id' => $line['location_id'],
                    'quantity' => $line['quantity'], 'notes' => 'Added during final bill preparation',
                    'idempotency_key' => 'bill:'.$job->id.':'.$data['idempotency_key'].':'.$index,
                ], $actor);
                $stock->issue($usage, $actor);
            }
            $job->refresh();
            if (BigDecimal::of($job->total_job_amount)->isGreaterThan('999999999999.99')) {
                throw ValidationException::withMessages(['parts' => 'The total exceeds the supported amount.']);
            }
            BusinessAudit::record($actor, 'repair_job.bill_prepared', RepairJob::class, $job->id,
                ['totals' => $before, 'charges' => $linesBefore], ['totals' => $job->only(['labour_charge', 'parts_charge', 'total_job_amount']), 'charges' => $job->billingCharges()->get()->toArray()]);

            return $job;
        }, 3);
    }
}
