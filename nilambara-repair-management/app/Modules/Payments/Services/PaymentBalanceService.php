<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Models\Payment;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;

class PaymentBalanceService
{
    /**
     * @return array{amount: string, allocated: string, reversed: string, available: string, status: string}
     */
    public function summary(Payment $payment): array
    {
        $payment->loadMissing([
            'paymentAllocations.releases',
            'paymentReversals',
        ]);
        $zero = BigDecimal::zero()->toScale(2);
        $allocated = $payment->paymentAllocations->reduce(
            fn (BigDecimal $sum, $allocation): BigDecimal => $sum->plus($this->netAllocation($allocation)),
            $zero,
        );
        $reversed = $payment->paymentReversals->reduce(
            fn (BigDecimal $sum, $reversal): BigDecimal => $sum->plus((string) $reversal->amount),
            $zero,
        );
        $amount = BigDecimal::of((string) $payment->amount)->toScale(2);
        $available = $amount->minus($allocated)->minus($reversed)->toScale(2);

        if ($available->isLessThan(0)) {
            throw new \LogicException('Payment '.$payment->payment_number.' is over-allocated or over-reversed.');
        }

        $status = match (true) {
            $reversed->compareTo($amount) === 0 => 'reversed',
            $reversed->isGreaterThan(0) => 'partially_reversed',
            $allocated->isGreaterThan(0) && $available->isGreaterThan(0) => 'partially_allocated',
            $allocated->isGreaterThan(0) => 'allocated',
            default => 'unallocated',
        };

        return [
            'amount' => (string) $amount,
            'allocated' => (string) $allocated,
            'reversed' => (string) $reversed,
            'available' => (string) $available,
            'status' => $status,
        ];
    }

    public function netAllocation($allocation): BigDecimal
    {
        $released = $allocation->releases->reduce(
            fn (BigDecimal $sum, $release): BigDecimal => $sum->plus((string) $release->amount),
            BigDecimal::zero()->toScale(2),
        );
        $net = BigDecimal::of((string) $allocation->amount)->minus($released)->toScale(2);
        if ($net->isLessThan(0)) {
            throw new \LogicException('Payment allocation '.$allocation->getKey().' was released beyond its allocated amount.');
        }

        return $net;
    }

    public function constrainStatus(Builder $query, string $status): Builder
    {
        $allocated = '(SELECT COALESCE(SUM(pa.amount - COALESCE((SELECT SUM(pr.amount) FROM payment_allocation_releases pr WHERE pr.payment_allocation_id = pa.id), 0)), 0) FROM payment_allocations pa WHERE pa.payment_id = payments.id)';
        $reversed = '(SELECT COALESCE(SUM(pv.amount), 0) FROM payment_reversals pv WHERE pv.payment_id = payments.id)';
        $available = "(payments.amount - {$allocated} - {$reversed})";

        return match ($status) {
            'allocated' => $query->whereRaw("{$available} = 0 AND {$allocated} > 0"),
            'partially_allocated' => $query->whereRaw("{$available} > 0 AND {$available} < payments.amount AND {$reversed} = 0"),
            'partially_reversed' => $query->whereRaw("{$reversed} > 0 AND {$reversed} < payments.amount"),
            'reversed' => $query->whereRaw("{$reversed} >= payments.amount"),
            'unallocated' => $query->whereRaw("{$allocated} = 0 AND {$reversed} = 0"),
            default => $query,
        };
    }
}
