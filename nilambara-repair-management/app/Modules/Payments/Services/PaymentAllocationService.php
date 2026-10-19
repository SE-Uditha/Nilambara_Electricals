<?php

namespace App\Modules\Payments\Services;

use App\Enums\DocumentStatus;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Services\InvoiceBalanceService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentAllocation;
use App\Modules\Payments\Models\PaymentAllocationRelease;
use App\Modules\Payments\Models\PaymentReversal;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentAllocationService
{
    public function __construct(
        private readonly PaymentBalanceService $paymentBalances,
        private readonly InvoiceBalanceService $invoiceBalances,
    ) {}

    public function allocateToInvoice(Payment $payment, Invoice $invoice, string $amount, User $actor, string $key): PaymentAllocation
    {
        return DB::transaction(function () use ($payment, $invoice, $amount, $actor, $key): PaymentAllocation {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $lockedInvoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            $this->assertInvoiceCustomer($lockedPayment, $lockedInvoice);
            $amountValue = BigDecimal::of($amount)->toScale(2);
            $this->assertPositive($amountValue);

            $available = BigDecimal::of($this->paymentBalances->summary($lockedPayment)['available'])->toScale(2);
            if ($amountValue->isGreaterThan($available)) {
                throw ValidationException::withMessages(['amount' => __('payments.allocation_exceeds_available')]);
            }
            $outstanding = BigDecimal::of($this->invoiceBalances->summary($lockedInvoice)['outstanding'])->toScale(2);
            if ($lockedInvoice->status !== DocumentStatus::Finalized || $amountValue->isGreaterThan($outstanding)) {
                throw ValidationException::withMessages(['amount' => __('payments.allocation_exceeds_outstanding')]);
            }

            $allocation = PaymentAllocation::query()->create([
                'payment_id' => $lockedPayment->getKey(),
                'customer_id' => $lockedPayment->customer_id,
                'target_type' => 'invoice',
                'invoice_id' => $lockedInvoice->getKey(),
                'amount' => (string) $amountValue,
                'allocated_at' => now(config('business.timezone', 'Asia/Colombo')),
                'allocated_by_user_id' => $actor->getKey(),
                'idempotency_key' => $key,
                'customer_scope_id' => $lockedPayment->customer_scope_id,
            ]);
            BusinessAudit::record($actor, 'payment.allocated', PaymentAllocation::class, (int) $allocation->getKey(), newValues: [
                'payment_number' => $lockedPayment->payment_number,
                'invoice_number' => $lockedInvoice->invoice_number,
                'amount' => (string) $amountValue,
            ]);

            return $allocation;
        });
    }

    public function applyAvailableAdvances(Invoice $invoice, User $actor): void
    {
        $customerId = (int) $invoice->customer_id;
        $advances = Payment::query()
            ->where('customer_id', $customerId)
            ->where('payment_type', 'advance')
            ->orderBy('paid_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($advances as $advance) {
            $available = BigDecimal::of($this->paymentBalances->summary($advance)['available'])->toScale(2);
            if (! $available->isGreaterThan(0)) {
                continue;
            }

            $outstanding = BigDecimal::of($this->invoiceBalances->summary($invoice)['outstanding'])->toScale(2);
            if (! $outstanding->isGreaterThan(0)) {
                break;
            }

            $applied = $available->compareTo($outstanding) <= 0 ? $available : $outstanding;
            $this->createAllocation($advance, $invoice, (string) $applied, $actor, 'advance-apply:'.$advance->getKey().':'.$invoice->getKey());
        }
    }

    /**
     * Release applied value before recording a refund/reversal. Free, unapplied
     * value is consumed first so unrelated invoice allocations are preserved.
     */
    public function releaseForReversal(
        Payment $payment,
        PaymentReversal $reversal,
        User $actor,
        BigDecimal $amount,
        BigDecimal $unallocatedBefore,
    ): void {
        $payment->load('paymentAllocations.releases', 'paymentReversals');
        $remainingToRelease = $amount->minus(
            $amount->compareTo($unallocatedBefore) <= 0 ? $amount : $unallocatedBefore,
        )->toScale(2);
        if (! $remainingToRelease->isGreaterThan(0)) {
            return;
        }

        foreach ($payment->paymentAllocations->sortBy('id') as $allocation) {
            $net = $this->paymentBalances->netAllocation($allocation);
            if (! $net->isGreaterThan(0)) {
                continue;
            }
            $releaseAmount = $net->compareTo($remainingToRelease) <= 0 ? $net : $remainingToRelease;
            $release = PaymentAllocationRelease::query()->create([
                'payment_allocation_id' => $allocation->getKey(),
                'payment_id' => $payment->getKey(),
                'payment_reversal_id' => $reversal->getKey(),
                'amount' => (string) $releaseAmount,
                'reason_type' => $reversal->reversal_type,
                'released_at' => $reversal->reversed_at,
                'released_by_user_id' => $actor->getKey(),
                'notes' => $reversal->reason,
                'idempotency_key' => 'release:'.$reversal->getKey().':'.$allocation->getKey(),
            ]);
            BusinessAudit::record($actor, 'payment.allocation_released', PaymentAllocationRelease::class, (int) $release->getKey(), newValues: [
                'payment_number' => $payment->payment_number,
                'allocation_id' => $allocation->getKey(),
                'reversal_id' => $reversal->getKey(),
                'amount' => (string) $releaseAmount,
            ]);
            $remainingToRelease = $remainingToRelease->minus($releaseAmount)->toScale(2);
            if (! $remainingToRelease->isGreaterThan(0)) {
                return;
            }
        }

        throw ValidationException::withMessages(['amount' => __('payments.reversal_exceeds_unreleased_value')]);
    }

    private function createAllocation(Payment $payment, Invoice $invoice, string $amount, User $actor, string $key): PaymentAllocation
    {
        $allocation = PaymentAllocation::query()->create([
            'payment_id' => $payment->getKey(),
            'customer_id' => $payment->customer_id,
            'target_type' => 'invoice',
            'invoice_id' => $invoice->getKey(),
            'amount' => $amount,
            'allocated_at' => now(config('business.timezone', 'Asia/Colombo')),
            'allocated_by_user_id' => $actor->getKey(),
            'idempotency_key' => $key,
            'customer_scope_id' => $payment->customer_scope_id,
        ]);
        BusinessAudit::record($actor, 'payment.advance_allocated', PaymentAllocation::class, (int) $allocation->getKey(), newValues: [
            'payment_number' => $payment->payment_number,
            'invoice_number' => $invoice->invoice_number,
            'amount' => $amount,
        ]);

        return $allocation;
    }

    private function assertInvoiceCustomer(Payment $payment, Invoice $invoice): void
    {
        if ($payment->customer_id === null || (int) $payment->customer_id !== (int) $invoice->customer_id) {
            throw ValidationException::withMessages(['invoice_id' => __('payments.customer_mismatch')]);
        }
    }

    private function assertPositive(BigDecimal $amount): void
    {
        if (! $amount->isGreaterThan(0)) {
            throw ValidationException::withMessages(['amount' => __('payments.amount_must_be_positive')]);
        }
    }
}
