<?php

namespace App\Modules\Invoices\Services;

use App\Enums\ApprovalStatus;
use App\Enums\JobStatus;
use App\Enums\QuotationPreparationStatus;
use App\Modules\Inventory\Models\JobPartUsage;
use App\Modules\Quotations\Models\Quotation;
use App\Modules\RepairJobs\Models\RepairJob;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InvoiceCalculationService
{
    /**
     * @return array{lines: array<int, array<string, mixed>>, amount: string, quotation: ?Quotation, parts_details: array<int, string>}
     */
    public function billableLines(RepairJob $job): array
    {
        if (! in_array($job->status, JobStatus::billable(), true)) {
            throw ValidationException::withMessages([
                'repair_job_ids' => __('invoices.job_not_billable', ['job' => $job->automatic_number ?: $job->job_number]),
            ]);
        }

        $quotation = $job->quotations()
            ->where('preparation_status', QuotationPreparationStatus::Issued->value)
            ->where('approval_status', ApprovalStatus::Approved->value)
            ->with(['items' => fn ($query) => $query->whereNull('removed_at')->orderBy('display_order')->orderBy('line_number')])
            ->orderByDesc('revision')
            ->orderByDesc('id')
            ->first();

        $jobTotal = $this->money((string) $job->total_job_amount);
        if ($quotation !== null) {
            if ($jobTotal->compareTo($this->money((string) $quotation->total)) !== 0) {
                throw ValidationException::withMessages([
                    'repair_job_ids' => __('invoices.approved_quote_amount_mismatch', ['job' => $job->automatic_number ?: $job->job_number]),
                ]);
            }

            return $this->quotedLines($job, $quotation, $jobTotal);
        }

        $lines = [];
        $partsDetails = [];
        $custom = $job->billingCharges()->whereNull('removed_at')->orderBy('line_number')->get();
        $customTotal = $custom->reduce(fn (BigDecimal $sum, $line) => $sum->plus($line->line_total), BigDecimal::zero());
        $labour = $this->money((string) $job->labour_charge)->minus($customTotal);
        foreach ($custom as $charge) {
            $lines[] = $this->line($charge->description, 'service', $charge->quantity, $charge->unit_price, null, null);
        }
        $parts = $this->money((string) $job->parts_charge);

        if ($labour->isGreaterThan(0)) {
            $lines[] = $this->line(__('invoices.labour'), 'labour', '1.000', (string) $labour, null, null);
        }

        if ($parts->isGreaterThan(0)) {
            $usages = $job->jobPartUsages()->with('sparePart')->where('status', 'issued')->get();
            $usageLines = $this->issuedUsageLines($usages, $parts, $partsDetails);
            if ($usageLines !== []) {
                array_push($lines, ...$usageLines);
            } else {
                $description = $partsDetails === []
                    ? __('invoices.parts_for_job')
                    : __('invoices.parts_with_details', ['details' => implode(', ', $partsDetails)]);
                $lines[] = $this->line($description, 'part', '1.000', (string) $parts, null, null);
            }
        }

        $this->assertLinesMatchTotal($lines, $jobTotal, $job);

        return [
            'lines' => $lines,
            'amount' => (string) $jobTotal,
            'quotation' => null,
            'parts_details' => $partsDetails,
        ];
    }

    /**
     * @param  Collection<int, JobPartUsage>  $usages
     * @param  array<int, string>  $partsDetails
     * @return array<int, array<string, mixed>>
     */
    private function issuedUsageLines(Collection $usages, BigDecimal $partsTotal, array &$partsDetails): array
    {
        $lines = [];
        $sum = BigDecimal::zero()->toScale(2);

        foreach ($usages as $usage) {
            if ($usage->sparePart === null) {
                continue;
            }
            $description = $usage->sparePart->name;
            $partsDetails[] = $description;
            $lineTotal = BigDecimal::of((string) $usage->quantity)->toScale(3)
                ->multipliedBy($this->unitPrice((string) $usage->unit_price))
                ->toScale(2, RoundingMode::HalfUp);
            $sum = $sum->plus($lineTotal);
            $lines[] = $this->line(
                $description,
                'part',
                (string) $usage->quantity,
                (string) $usage->unit_price,
                null,
                $usage->spare_part_id,
            );
        }

        return $lines !== [] && $sum->compareTo($partsTotal) === 0 ? $lines : [];
    }

    /**
     * @return array{lines: array<int, array<string, mixed>>, amount: string, quotation: Quotation, parts_details: array<int, string>}
     */
    private function quotedLines(RepairJob $job, Quotation $quotation, BigDecimal $jobTotal): array
    {
        $lines = [];
        $partsDetails = [];
        foreach ($quotation->items as $item) {
            $partsDetails[] = $item->description;
            $lines[] = $this->line(
                $item->description,
                $item->item_type === 'part' ? 'part' : 'service',
                (string) $item->quantity,
                (string) $item->unit_price,
                $item->getKey(),
                $item->spare_part_id,
            );
        }
        $labour = $this->money((string) $quotation->labour_charge);
        if ($labour->isGreaterThan(0)) {
            $lines[] = $this->line(__('invoices.labour'), 'labour', '1.000', (string) $labour, null, null);
        }

        $this->assertLinesMatchTotal($lines, $jobTotal, $job);

        return [
            'lines' => $lines,
            'amount' => (string) $jobTotal,
            'quotation' => $quotation,
            'parts_details' => $partsDetails,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function assertLinesMatchTotal(array $lines, BigDecimal $total, RepairJob $job): void
    {
        $lineTotal = BigDecimal::zero()->toScale(2);
        foreach ($lines as $line) {
            $lineTotal = $lineTotal->plus(BigDecimal::of($line['quantity'])->toScale(3)
                ->multipliedBy($this->unitPrice($line['unit_price']))
                ->toScale(2, RoundingMode::HalfUp));
        }

        if ($lineTotal->compareTo($total) !== 0) {
            throw ValidationException::withMessages([
                'repair_job_ids' => __('invoices.job_charge_breakdown_mismatch', ['job' => $job->automatic_number ?: $job->job_number]),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function line(
        string $description,
        string $type,
        string $quantity,
        string $unitPrice,
        ?int $quotationItemId,
        ?int $sparePartId,
    ): array {
        try {
            $quantity = (string) BigDecimal::of($quantity)->toScale(3);
            $unitPrice = (string) BigDecimal::of($unitPrice)->toScale(4);
            $lineTotal = (string) BigDecimal::of($quantity)
                ->multipliedBy($unitPrice)
                ->toScale(2, RoundingMode::HalfUp);
        } catch (\InvalidArgumentException|MathException) {
            throw ValidationException::withMessages(['repair_job_ids' => __('invoices.invalid_job_amount')]);
        }

        return [
            'description' => $description,
            'item_type' => $type,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'quotation_item_id' => $quotationItemId,
            'spare_part_id' => $sparePartId,
        ];
    }

    private function money(string $value): BigDecimal
    {
        try {
            return BigDecimal::of($value)->toScale(2, RoundingMode::HalfUp);
        } catch (\InvalidArgumentException|MathException) {
            throw ValidationException::withMessages(['repair_job_ids' => __('invoices.invalid_job_amount')]);
        }
    }

    private function unitPrice(string $value): BigDecimal
    {
        try {
            return BigDecimal::of($value)->toScale(4, RoundingMode::HalfUp);
        } catch (\InvalidArgumentException|MathException) {
            throw ValidationException::withMessages(['repair_job_ids' => __('invoices.invalid_job_amount')]);
        }
    }
}
