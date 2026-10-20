<?php

namespace App\Modules\Suppliers\Services;

use App\Models\User;
use App\Modules\Inventory\Models\StockReturn;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Models\SupplierAccountEntry;
use App\Modules\Suppliers\Models\SupplierAllocationRelease;
use App\Modules\Suppliers\Models\SupplierPayment;
use App\Modules\Suppliers\Models\SupplierPaymentAllocation;
use App\Modules\Suppliers\Models\SupplierPaymentReversal;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SupplierPaymentService
{
    public function openingOutstanding(Supplier $supplier): string
    {
        $paid = $supplier->supplierPaymentAllocations()->where('target_type', 'opening_balance')->lockForUpdate()->get()->reduce(fn (BigDecimal $sum, $line) => $sum->plus($line->amount)->minus($line->releases()->lockForUpdate()->get()->reduce(fn (BigDecimal $r, $release) => $r->plus($release->amount), BigDecimal::zero())), BigDecimal::zero());
        $balance = BigDecimal::of($supplier->opening_balance)->minus($paid);

        return (string) ($balance->isNegative() ? BigDecimal::zero()->toScale(2) : $balance->toScale(2));
    }

    public function payOpening(Supplier $supplier, array $data, User $actor): SupplierPayment
    {
        Gate::forUser($actor)->authorize('purchases.approve');

        return DB::transaction(function () use ($supplier, $data, $actor) {
            $supplier = Supplier::whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            if ($old = SupplierPayment::where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) {
                $this->assertTenderSame($old, $data);
                if ($old->paid_by_user_id !== $actor->id || $old->supplier_id !== $supplier->id || BigDecimal::of($old->amount)->compareTo($data['amount']) !== 0 || $old->method->value !== $data['method'] || ! $old->supplierPaymentAllocations()->where('target_type', 'opening_balance')->exists()) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Payment key already used with different details.']);
                }

                return $old;
            }
            $amount = BigDecimal::of($data['amount'])->toScale(2);
            if (! $supplier->accountEntries()->where('entry_type', 'opening')->exists()) {
                throw ValidationException::withMessages(['supplier' => 'Reconcile the supplier opening ledger before paying its opening balance.']);
            }
            if (! $amount->isPositive() || $amount->isGreaterThan($this->openingOutstanding($supplier))) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the unpaid opening balance.']);
            }
            $payment = SupplierPayment::create(['payment_number' => 'SP-'.$data['idempotency_key'], 'supplier_id' => $supplier->id, 'method' => $data['method'], 'amount' => (string) $amount, 'paid_at' => now(), 'paid_by_user_id' => $actor->id, 'bank_reference' => $data['reference'] ?? null, 'cheque_number' => $data['method'] === 'cheque' ? ($data['reference'] ?? null) : null, 'cheque_date' => $data['cheque_date'] ?? null, 'notes' => $data['notes'] ?? null, 'idempotency_key' => $data['idempotency_key']]);
            SupplierPaymentAllocation::create(['supplier_payment_id' => $payment->id, 'supplier_id' => $supplier->id, 'target_type' => 'opening_balance', 'amount' => (string) $amount, 'allocated_at' => now(), 'allocated_by_user_id' => $actor->id, 'idempotency_key' => 'sp-alloc:'.$payment->id]);
            SupplierAccountEntry::create(['supplier_id' => $supplier->id, 'entry_type' => 'payment', 'amount' => (string) $amount->negated(), 'occurred_at' => now(), 'reference_number' => $payment->payment_number, 'description' => 'Supplier opening balance payment', 'recorded_by_user_id' => $actor->id, 'supplier_payment_id' => $payment->id, 'idempotency_key' => 'sp-ledger:'.$payment->id]);
            BusinessAudit::record($actor, 'supplier.payment.created', SupplierPayment::class, $payment->id, newValues: ['amount' => (string) $amount, 'target' => 'opening_balance']);

            return $payment;
        }, 3);
    }

    public function outstanding(Purchase $purchase): string
    {
        $allocations = $purchase->supplierPaymentAllocations()->lockForUpdate()->get();
        $paid = $allocations->reduce(fn (BigDecimal $sum, $line) => $sum->plus($line->amount)->minus($line->releases()->lockForUpdate()->get()->reduce(fn (BigDecimal $r, $release) => $r->plus($release->amount), BigDecimal::zero())), BigDecimal::zero());

        $credit = SupplierAccountEntry::where('entry_type', 'adjustment')->whereIn('reference_number', StockReturn::where('purchase_id', $purchase->id)->where('return_type', 'supplier_return')->select('return_number'))->lockForUpdate()->pluck('amount')->reduce(fn (BigDecimal $sum, $value) => $sum->minus((string) $value), BigDecimal::zero());
        $outstanding = BigDecimal::of($purchase->total)->minus($paid)->minus($credit)->toScale(2);

        return (string) ($outstanding->isNegative() ? BigDecimal::zero()->toScale(2) : $outstanding);
    }

    private function assertTenderSame(SupplierPayment $payment, array $data): void
    {
        if ((string) $payment->bank_reference !== (string) ($data['reference'] ?? '') || (string) $payment->notes !== (string) ($data['notes'] ?? '') || (string) $payment->cheque_date?->format('Y-m-d') !== (string) ($data['cheque_date'] ?? '')) {
            throw ValidationException::withMessages(['idempotency_key' => 'Payment key already used with different tender details.']);
        }
    }

    public function pay(Purchase $purchase, array $data, User $actor): SupplierPayment
    {
        Gate::forUser($actor)->authorize('purchases.approve');

        return DB::transaction(function () use ($purchase, $data, $actor) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            Supplier::whereKey($purchase->supplier_id)->lockForUpdate()->firstOrFail();
            if ($old = SupplierPayment::where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) {
                $this->assertTenderSame($old, $data);
                if ($old->paid_by_user_id !== $actor->id || $old->supplier_id !== $purchase->supplier_id || BigDecimal::of($old->amount)->compareTo($data['amount']) !== 0 || $old->method->value !== $data['method'] || ! $old->supplierPaymentAllocations()->where('purchase_id', $purchase->id)->exists()) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Payment key already used with different details.']);
                }

                return $old;
            }
            $amount = BigDecimal::of($data['amount'])->toScale(2);
            if ($purchase->status->value !== 'finalized' || ! $amount->isPositive() || $amount->isGreaterThan($this->outstanding($purchase))) {
                throw ValidationException::withMessages(['amount' => 'Payment must be positive and cannot exceed finalized purchase outstanding.']);
            }
            $payment = SupplierPayment::create(['payment_number' => 'SP-'.$data['idempotency_key'], 'supplier_id' => $purchase->supplier_id, 'method' => $data['method'], 'amount' => (string) $amount, 'paid_at' => now(), 'paid_by_user_id' => $actor->id, 'bank_reference' => $data['reference'] ?? null, 'cheque_number' => $data['method'] === 'cheque' ? ($data['reference'] ?? null) : null, 'cheque_date' => $data['cheque_date'] ?? null, 'notes' => $data['notes'] ?? null, 'idempotency_key' => $data['idempotency_key']]);
            SupplierPaymentAllocation::create(['supplier_payment_id' => $payment->id, 'supplier_id' => $purchase->supplier_id, 'purchase_id' => $purchase->id, 'target_type' => 'purchase', 'amount' => (string) $amount, 'allocated_at' => now(), 'allocated_by_user_id' => $actor->id, 'idempotency_key' => 'sp-alloc:'.$payment->id]);
            SupplierAccountEntry::create(['supplier_id' => $purchase->supplier_id, 'entry_type' => 'payment', 'amount' => (string) $amount->negated(), 'occurred_at' => now(), 'reference_number' => $payment->payment_number, 'description' => 'Supplier purchase payment', 'recorded_by_user_id' => $actor->id, 'supplier_payment_id' => $payment->id, 'idempotency_key' => 'sp-ledger:'.$payment->id]);
            BusinessAudit::record($actor, 'supplier.payment.created', SupplierPayment::class, $payment->id, newValues: ['amount' => (string) $amount, 'purchase_id' => $purchase->id]);

            return $payment;
        }, 3);
    }

    public function reverse(SupplierPayment $payment, array $data, User $actor): SupplierPaymentReversal
    {
        Gate::forUser($actor)->authorize('purchases.approve');

        return DB::transaction(function () use ($payment, $data, $actor) {
            // Lock purchase before payment, matching payment allocation's order.
            $allocation = $payment->supplierPaymentAllocations()->sole();
            if ($allocation->purchase_id !== null) {
                Purchase::whereKey($allocation->purchase_id)->lockForUpdate()->firstOrFail();
            } else {
                Supplier::whereKey($payment->supplier_id)->lockForUpdate()->firstOrFail();
            }
            $payment = SupplierPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            Supplier::whereKey($payment->supplier_id)->lockForUpdate()->firstOrFail();
            if ($old = SupplierPaymentReversal::where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) {
                if ($old->supplier_payment_id !== $payment->id || $old->reversed_by_user_id !== $actor->id || $old->reason !== $data['reason']) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Reversal key already used with different details.']);
                }

                return $old;
            }
            if ($payment->supplierPaymentReversals()->lockForUpdate()->get()->isNotEmpty()) {
                throw ValidationException::withMessages(['payment' => 'Payment has already been reversed.']);
            }
            $reversal = SupplierPaymentReversal::create(['supplier_payment_id' => $payment->id, 'supplier_id' => $payment->supplier_id, 'reversal_type' => 'reversal', 'amount' => $payment->amount, 'reversed_at' => now(), 'reversed_by_user_id' => $actor->id, 'reason' => $data['reason'], 'idempotency_key' => $data['idempotency_key']]);
            SupplierAllocationRelease::create(['supplier_payment_allocation_id' => $allocation->id, 'supplier_payment_id' => $payment->id, 'supplier_payment_reversal_id' => $reversal->id, 'amount' => $allocation->amount, 'released_at' => now(), 'released_by_user_id' => $actor->id, 'reason' => $data['reason'], 'idempotency_key' => 'sp-release:'.$reversal->id]);
            $original = $payment->supplierAccountEntries()->sole();
            SupplierAccountEntry::create(['supplier_id' => $payment->supplier_id, 'entry_type' => 'reversal', 'amount' => $payment->amount, 'occurred_at' => now(), 'reference_number' => $payment->payment_number, 'description' => $data['reason'], 'recorded_by_user_id' => $actor->id, 'reverses_entry_id' => $original->id, 'idempotency_key' => 'sp-reversal:'.$reversal->id]);
            BusinessAudit::record($actor, 'supplier.payment.reversed', SupplierPaymentReversal::class, $reversal->id, newValues: ['amount' => $payment->amount, 'reason' => $data['reason']]);

            return $reversal;
        }, 3);
    }
}
