<?php

namespace App\Modules\Invoices\Services;

use App\Enums\DocumentStatus;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Payments\Models\PaymentAllocation;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;

class InvoiceBalanceService
{
    /**
     * @return array{advance_paid: string, previous_payments: string, current_payment: string, total_paid: string, outstanding: string, payment_status: string}
     */
    public function summary(Invoice $invoice): array
    {
        $jobIds = $invoice->relationLoaded('invoiceJobs')
            ? $invoice->invoiceJobs->pluck('repair_job_id')
            : $invoice->invoiceJobs()->pluck('repair_job_id');
        $advance = $jobIds->isEmpty()
            ? BigDecimal::zero()->toScale(2)
            : $this->netAllocated(
                PaymentAllocation::query()
                    ->where('target_type', 'job_advance')
                    ->whereIn('repair_job_id', $jobIds),
            );
        $invoicePayments = $this->netAllocated(
            PaymentAllocation::query()
                ->where('target_type', 'invoice')
                ->where('invoice_id', $invoice->getKey()),
        );

        return $this->summarizeAmounts($invoice, $advance, $invoicePayments);
    }

    /** Populate an entire list with one allocations query rather than queries per invoice. */
    public function summaries(Collection $invoices): array
    {
        if ($invoices->isEmpty()) {
            return [];
        }
        $invoices->loadMissing('invoiceJobs');
        $invoiceIds = $invoices->modelKeys();
        $jobIds = $invoices->flatMap(fn (Invoice $invoice) => $invoice->invoiceJobs->pluck('repair_job_id'))->unique()->values();
        $allocations = PaymentAllocation::query()->withSum('releases as released_amount', 'amount')
            ->where(function ($query) use ($invoiceIds, $jobIds): void {
                $query->where(function ($query) use ($invoiceIds): void {
                    $query->where('target_type', 'invoice')->whereIn('invoice_id', $invoiceIds);
                })->orWhere(function ($query) use ($jobIds): void {
                    $query->where('target_type', 'job_advance')->whereIn('repair_job_id', $jobIds);
                });
            })->get();
        $payments = [];
        $advances = [];
        foreach ($allocations as $allocation) {
            $net = $this->netAmount($allocation);
            if ($allocation->target_type === 'invoice') {
                $key = $allocation->invoice_id;
                $payments[$key] = ($payments[$key] ?? BigDecimal::zero())->plus($net);
            } else {
                $key = $allocation->repair_job_id;
                $advances[$key] = ($advances[$key] ?? BigDecimal::zero())->plus($net);
            }
        }
        $summaries = [];
        foreach ($invoices as $invoice) {
            $advance = $invoice->invoiceJobs->reduce(
                fn (BigDecimal $sum, $link) => $sum->plus($advances[$link->repair_job_id] ?? BigDecimal::zero()),
                BigDecimal::zero()->toScale(2),
            );
            $summaries[$invoice->getKey()] = $this->summarizeAmounts($invoice, $advance, ($payments[$invoice->getKey()] ?? BigDecimal::zero())->toScale(2));
        }

        return $summaries;
    }

    private function summarizeAmounts(Invoice $invoice, BigDecimal $advance, BigDecimal $invoicePayments): array
    {
        $totalPaid = $advance->plus($invoicePayments)->toScale(2);
        $total = BigDecimal::of((string) $invoice->total)->toScale(2);
        $outstanding = $total->minus($totalPaid)->toScale(2);
        if ($outstanding->isLessThan(0)) {
            $outstanding = BigDecimal::zero()->toScale(2);
        }

        $status = 'unpaid';
        if ($invoice->status === DocumentStatus::Finalized && $total->isGreaterThan(0)) {
            if ($totalPaid->compareTo($total) >= 0) {
                $status = 'paid';
            } elseif ($totalPaid->isGreaterThan(0)) {
                $status = 'partially_paid';
            }
        }

        return [
            'advance_paid' => (string) $advance,
            'previous_payments' => (string) $invoicePayments,
            'current_payment' => '0.00',
            'total_paid' => (string) $totalPaid,
            'outstanding' => (string) $outstanding,
            'payment_status' => $status,
        ];
    }

    public function constrainPaymentStatus(Builder|QueryBuilder $query, string $status): Builder|QueryBuilder
    {
        $paid = $this->netPaidExpression();

        return match ($status) {
            'paid' => $query->where('invoices.status', DocumentStatus::Finalized->value)
                ->where('invoices.total', '>', 0)
                ->whereRaw("{$paid} >= invoices.total"),
            'partially_paid' => $query->where('invoices.status', DocumentStatus::Finalized->value)
                ->whereRaw("{$paid} > 0 AND {$paid} < invoices.total"),
            'outstanding' => $query->where('invoices.status', DocumentStatus::Finalized->value)
                ->where('invoices.total', '>', 0)
                ->whereRaw("{$paid} < invoices.total"),
            'unpaid' => $query->where(function ($builder) use ($paid): void {
                $builder->where('invoices.status', '!=', DocumentStatus::Finalized->value)
                    ->orWhereRaw("{$paid} <= 0");
            }),
            default => $query,
        };
    }

    public function netPaidExpression(): string
    {
        $allocated = "(SELECT COALESCE(SUM(pa.amount - COALESCE((SELECT SUM(r.amount) FROM payment_allocation_releases r WHERE r.payment_allocation_id = pa.id), 0)), 0) FROM payment_allocations pa WHERE pa.target_type = 'invoice' AND pa.invoice_id = invoices.id)";
        $advances = "(SELECT COALESCE(SUM(pa.amount - COALESCE((SELECT SUM(r.amount) FROM payment_allocation_releases r WHERE r.payment_allocation_id = pa.id), 0)), 0) FROM payment_allocations pa JOIN invoice_jobs ij ON ij.repair_job_id = pa.repair_job_id AND ij.invoice_id = invoices.id WHERE pa.target_type = 'job_advance')";

        return "({$allocated} + {$advances})";
    }

    private function netAllocated($query): BigDecimal
    {
        return $query->withSum('releases as released_amount', 'amount')
            ->get()
            ->reduce(function (BigDecimal $sum, PaymentAllocation $allocation): BigDecimal {
                return $sum->plus($this->netAmount($allocation));
            }, BigDecimal::zero()->toScale(2));
    }

    private function netAmount(PaymentAllocation $allocation): BigDecimal
    {
        $released = BigDecimal::of((string) ($allocation->released_amount ?? '0.00'))->toScale(2);
        $net = BigDecimal::of((string) $allocation->amount)->minus($released)->toScale(2, RoundingMode::HalfUp);

        return $net->isLessThan(0) ? BigDecimal::zero()->toScale(2) : $net;
    }
}
