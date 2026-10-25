<?php

namespace App\Modules\Payments\Services;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTerms;
use App\Models\User;
use App\Modules\CreditManagement\Models\CustomerAccountEntry;
use App\Modules\Customers\Models\Customer;
use App\Modules\Employees\Models\Employee;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Services\InvoiceBalanceService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentAllocation;
use App\Modules\Payments\Models\PaymentReversal;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly PaymentAllocationService $allocations,
        private readonly PaymentBalanceService $balances,
        private readonly InvoiceBalanceService $invoiceBalances,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function receiveInvoicePayment(array $data, User $actor): Payment
    {
        $this->authorizeReceiver($actor);

        return DB::transaction(function () use ($data, $actor): Payment {
            $invoice = Invoice::query()->whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail();
            if ($invoice->payment_terms === PaymentTerms::Credit) {
                abort_unless($actor->can('credit.collect'), 403);
            }
            if ($existing = $this->existingPayment($data, 'invoice', null, $actor)) {
                return $existing;
            }
            $customer = Customer::query()->whereKey($invoice->customer_id)->lockForUpdate()->firstOrFail();
            $this->assertInvoiceReceivable($invoice);
            $amount = $this->money((string) $data['amount']);
            $outstanding = BigDecimal::of($this->invoiceBalances->summary($invoice)['outstanding'])->toScale(2);
            if ($amount->isGreaterThan($outstanding)) {
                throw ValidationException::withMessages(['amount' => __('payments.exceeds_outstanding')]);
            }

            $employee = $this->activeEmployee($actor, $data['received_by_employee_id'] ?? null);
            $paymentType = $invoice->payment_terms === PaymentTerms::Credit
                ? 'credit_collection'
                : 'invoice';
            $payment = $this->createPayment(
                $customer,
                $employee,
                $actor,
                $data,
                $paymentType,
            );
            $this->allocations->allocateToInvoice(
                $payment,
                $invoice,
                (string) $amount,
                $actor,
                'payment-allocation:'.$data['idempotency_key'],
            );
            $this->recordPaymentLedger($payment, $customer, $actor, $paymentType);
            if (! empty($data['due_date']) && BigDecimal::of($this->invoiceBalances->summary($invoice)['outstanding'])->isGreaterThan(0)) {
                DB::table('invoices')->where('id', $invoice->getKey())->update(['due_date' => $data['due_date'], 'updated_at' => now()]);
            }
            BusinessAudit::record($actor, $paymentType === 'credit_collection' ? 'credit.payment_collected' : 'payment.created', Payment::class, (int) $payment->getKey(), newValues: [
                'payment_number' => $payment->payment_number,
                'customer_id' => $customer->getKey(),
                'invoice_id' => $invoice->getKey(),
                'invoice_number' => $invoice->invoice_number,
                'amount' => (string) $amount,
                'method' => $payment->method->value,
            ]);

            return $payment->refresh();
        }, 3);
    }

    /**
     * Credit method: no money is received; the remaining balance moves to credit management.
     *
     * @param  array<string, mixed>  $data
     */
    public function moveInvoiceToCredit(array $data, User $actor): Invoice
    {
        $this->authorizeReceiver($actor);

        return DB::transaction(function () use ($data, $actor): Invoice {
            $invoice = Invoice::query()->whereKey($data['invoice_id'])->lockForUpdate()->firstOrFail();
            $this->assertInvoiceReceivable($invoice);
            if ($invoice->payment_terms === PaymentTerms::Credit) {
                throw ValidationException::withMessages(['invoice_id' => __('payments.already_on_credit')]);
            }
            $outstanding = $this->invoiceBalances->summary($invoice)['outstanding'];
            DB::table('invoices')->where('id', $invoice->getKey())->update([
                'payment_terms' => PaymentTerms::Credit->value,
                'payment_method' => null,
                'due_date' => $data['due_date'] ?? $invoice->due_date?->toDateString(),
                'updated_at' => now(),
            ]);
            BusinessAudit::record($actor, 'credit.invoice_moved_to_credit', Invoice::class, (int) $invoice->getKey(), newValues: [
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'credit_amount' => $outstanding,
            ]);

            return $invoice->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function receiveAdvance(array $data, User $actor, ?RepairJob $job = null): Payment
    {
        $this->authorizeReceiver($actor);

        return DB::transaction(function () use ($data, $actor, $job): Payment {
            if ($existing = $this->existingPayment($data, 'advance', $job, $actor)) {
                return $existing;
            }

            $customerId = $job?->customer_id ?? (int) $data['customer_id'];
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
            if ($existing = $this->existingPayment($data, 'advance', $job, $actor)) {
                return $existing;
            }
            if (! $customer->is_active) {
                throw ValidationException::withMessages(['customer_id' => __('payments.active_customer_required')]);
            }
            if ($job !== null) {
                $job = RepairJob::query()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();
                if ((int) $job->customer_id !== (int) $customer->getKey() || $job->cancelled_at !== null) {
                    throw ValidationException::withMessages(['repair_job_id' => __('payments.job_customer_mismatch')]);
                }
            }

            $amount = $this->money((string) $data['amount']);
            $employee = $this->activeEmployee($actor, $data['received_by_employee_id'] ?? null);
            $payment = $this->createPayment($customer, $employee, $actor, $data, 'advance');
            if ($job !== null) {
                PaymentAllocation::query()->create([
                    'payment_id' => $payment->getKey(),
                    'customer_id' => $customer->getKey(),
                    'target_type' => 'job_advance',
                    'repair_job_id' => $job->getKey(),
                    'amount' => (string) $amount,
                    'allocated_at' => $payment->paid_at,
                    'allocated_by_user_id' => $actor->getKey(),
                    'idempotency_key' => 'payment-allocation:'.$data['idempotency_key'],
                    'customer_scope_id' => $customer->getKey(),
                ]);
            }
            $this->recordPaymentLedger($payment, $customer, $actor, 'advance', $job);
            BusinessAudit::record($actor, 'payment.advance_received', Payment::class, (int) $payment->getKey(), newValues: [
                'payment_number' => $payment->payment_number,
                'customer_id' => $customer->getKey(),
                'repair_job_id' => $job?->getKey(),
                'amount' => (string) $amount,
                'method' => $payment->method->value,
            ]);

            return $payment->refresh();
        }, 3);
    }

    public function reverse(Payment $payment, array $data, User $actor): PaymentReversal
    {
        abort_unless($actor->can('payments.reverse'), 403);

        return DB::transaction(function () use ($payment, $data, $actor): PaymentReversal {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            if ($existing = PaymentReversal::query()->where('idempotency_key', $data['idempotency_key'])->first()) {
                if ((int) $existing->payment_id !== (int) $locked->getKey()
                    || $existing->reversal_type !== $data['reversal_type']
                    || $this->money((string) $existing->amount)->compareTo($this->money((string) $data['amount'])) !== 0
                    || $existing->method?->value !== ($data['method'] ?? null)
                    || $existing->external_reference !== $this->nullableTrim($data['external_reference'] ?? null)
                    || $existing->reason !== trim($data['reason'])) {
                    throw ValidationException::withMessages(['idempotency_key' => __('payments.idempotency_mismatch')]);
                }

                return $existing;
            }

            $amount = $this->money((string) $data['amount']);
            $reversedBefore = $locked->paymentReversals()->sum('amount');
            $remaining = BigDecimal::of((string) $locked->amount)->minus((string) $reversedBefore)->toScale(2);
            if (! $amount->isGreaterThan(0) || $amount->isGreaterThan($remaining)) {
                throw ValidationException::withMessages(['amount' => __('payments.reversal_exceeds_remaining')]);
            }
            $unallocatedBefore = BigDecimal::of($this->balances->summary($locked)['available'])->toScale(2);

            $type = $data['reversal_type'];
            $paymentEntry = CustomerAccountEntry::query()->where('payment_id', $locked->getKey())->first();
            if ($type === 'reversal' && (
                $amount->compareTo($remaining) !== 0
                || BigDecimal::of((string) $reversedBefore)->isGreaterThan(0)
                || $paymentEntry === null
            )) {
                throw ValidationException::withMessages(['reversal_type' => __('payments.full_reversal_only')]);
            }

            $reversal = PaymentReversal::query()->create([
                'payment_id' => $locked->getKey(),
                'customer_id' => $locked->customer_id,
                'reversal_type' => $type,
                'amount' => (string) $amount,
                'method' => $type === 'refund' ? $data['method'] : null,
                'reversed_at' => now(config('business.timezone', 'Asia/Colombo')),
                'reversed_by_user_id' => $actor->getKey(),
                'reason' => trim($data['reason']),
                'external_reference' => $this->nullableTrim($data['external_reference'] ?? null),
                'idempotency_key' => $data['idempotency_key'],
                'customer_scope_id' => $locked->customer_scope_id,
            ]);
            $this->allocations->releaseForReversal($locked, $reversal, $actor, $amount, $unallocatedBefore);

            if ($locked->customer_id !== null) {
                $entryType = $type === 'refund' ? 'refund' : 'reversal';
                $entryAmount = $type === 'refund'
                    ? (string) $amount
                    : (string) BigDecimal::of((string) $paymentEntry->amount)->negated()->toScale(2);
                CustomerAccountEntry::query()->create([
                    'customer_id' => $locked->customer_id,
                    'entry_type' => $entryType,
                    'amount' => $entryAmount,
                    'occurred_at' => $reversal->reversed_at,
                    'reference_number' => $locked->payment_number,
                    'description' => trim($data['reason']),
                    'recorded_by_user_id' => $actor->getKey(),
                    'payment_reversal_id' => $type === 'refund' ? $reversal->getKey() : null,
                    'reverses_entry_id' => $type === 'reversal' ? $paymentEntry->getKey() : null,
                    'idempotency_key' => 'payment-reversal-ledger:'.$reversal->getKey(),
                ]);
            }

            BusinessAudit::record($actor, $type === 'refund' ? 'payment.refund_recorded' : 'payment.reversed', PaymentReversal::class, (int) $reversal->getKey(), newValues: [
                'payment_number' => $locked->payment_number,
                'customer_id' => $locked->customer_id,
                'amount' => (string) $amount,
                'reversal_type' => $type,
                'reason' => trim($data['reason']),
            ]);

            return $reversal;
        }, 3);
    }

    private function createPayment(
        ?Customer $customer,
        Employee $employee,
        User $actor,
        array $data,
        string $paymentType,
    ): Payment {
        $amount = $this->money((string) $data['amount']);
        $payment = Payment::query()->create([
            'payment_number' => 'TEMP-'.Str::uuid(),
            'customer_id' => $customer?->getKey(),
            'payment_type' => $paymentType,
            'method' => $data['method'],
            'amount' => (string) $amount,
            'paid_at' => $this->paidAt($data['payment_date']),
            'received_by_employee_id' => $employee->getKey(),
            'bank_reference' => $this->nullableTrim($data['reference_number'] ?? null),
            'cheque_number' => $data['method'] === PaymentMethod::Cheque->value
                ? $this->nullableTrim($data['reference_number'] ?? null)
                : null,
            'cheque_date' => $data['method'] === PaymentMethod::Cheque->value ? ($data['cheque_date'] ?? null) : null,
            'bank_name' => $this->nullableTrim($data['bank_name'] ?? null),
            'notes' => $this->nullableTrim($data['notes'] ?? null),
            'idempotency_key' => $data['idempotency_key'],
            'customer_scope_id' => $customer?->getKey() ?? 0,
        ]);
        $number = trim((string) config('business.payment_number_prefix', 'PAY-'));
        if ($number === '' || strlen($number) > 20) {
            throw new \LogicException('The payment number prefix must be non-empty and no longer than 20 characters.');
        }
        $payment->payment_number = $number.str_pad((string) $payment->getKey(), 5, '0', STR_PAD_LEFT);
        DB::table('payments')->where('id', $payment->getKey())->update(['payment_number' => $payment->payment_number]);
        $payment->syncOriginalAttribute('payment_number');

        return $payment;
    }

    private function recordPaymentLedger(
        Payment $payment,
        Customer $customer,
        User $actor,
        string $paymentType,
        ?RepairJob $job = null,
    ): void {
        CustomerAccountEntry::query()->create([
            'customer_id' => $customer->getKey(),
            'entry_type' => 'payment',
            'amount' => '-'.$payment->amount,
            'occurred_at' => $payment->paid_at,
            'reference_number' => $payment->payment_number,
            'description' => $paymentType === 'advance'
                ? __('payments.advance_received_description', ['job' => $job?->automatic_number ?? __('payments.customer_account')])
                : __('payments.payment_received_description', ['invoice' => $payment->paymentAllocations()->first()?->invoice?->invoice_number ?? '']),
            'recorded_by_user_id' => $actor->getKey(),
            'payment_id' => $payment->getKey(),
            'idempotency_key' => 'payment-ledger:'.$payment->getKey(),
        ]);
    }

    private function existingPayment(array $data, string $expectedType, ?RepairJob $job, User $actor): ?Payment
    {
        $existing = Payment::query()->where('idempotency_key', $data['idempotency_key'])->first();
        if ($existing === null) {
            return null;
        }

        $expectedPaymentType = $expectedType === 'invoice'
            ? Invoice::query()->whereKey($data['invoice_id'])->value('payment_terms') === PaymentTerms::Credit->value
                ? 'credit_collection'
                : 'invoice'
            : 'advance';
        $targetMatches = $expectedType === 'invoice'
            ? $existing->paymentAllocations()->where('target_type', 'invoice')->where('invoice_id', $data['invoice_id'])->exists()
            : ($job === null
                ? (int) $existing->customer_id === (int) ($data['customer_id'] ?? 0)
                    && ! $existing->paymentAllocations()->where('target_type', 'job_advance')->exists()
                : $existing->paymentAllocations()->where('target_type', 'job_advance')->where('repair_job_id', $job->getKey())->exists());

        $expectedEmployeeId = $data['received_by_employee_id']
            ?? Employee::query()->where('user_id', $actor->getKey())->where('is_active', true)->value('id');

        if ($existing->payment_type !== $expectedPaymentType
            || $existing->method->value !== $data['method']
            || $this->money((string) $existing->amount)->compareTo($this->money((string) $data['amount'])) !== 0
            || $existing->paid_at->toDateString() !== $data['payment_date']
            || $existing->bank_reference !== $this->nullableTrim($data['reference_number'] ?? null)
            || $existing->bank_name !== $this->nullableTrim($data['bank_name'] ?? null)
            || $existing->cheque_date?->toDateString() !== ($data['cheque_date'] ?? null)
            || $existing->notes !== $this->nullableTrim($data['notes'] ?? null)
            || ($expectedEmployeeId !== null && (int) $existing->received_by_employee_id !== (int) $expectedEmployeeId)
            || ! $targetMatches) {
            throw ValidationException::withMessages(['idempotency_key' => __('payments.idempotency_mismatch')]);
        }

        return $existing;
    }

    private function assertInvoiceReceivable(Invoice $invoice): void
    {
        if ($invoice->status !== DocumentStatus::Finalized) {
            throw ValidationException::withMessages(['invoice_id' => __('payments.invoice_not_issued')]);
        }
        if (! BigDecimal::of($this->invoiceBalances->summary($invoice)['outstanding'])->isGreaterThan(0)) {
            throw ValidationException::withMessages(['invoice_id' => __('payments.invoice_no_outstanding')]);
        }
    }

    private function authorizeReceiver(User $actor): void
    {
        abort_unless($actor->can('payments.create') && $actor->can('payments.receive'), 403);
    }

    private function activeEmployee(User $actor, ?int $employeeId = null): Employee
    {
        if ($employeeId !== null) {
            $actingEmployeeId = Employee::query()
                ->where('user_id', $actor->getKey())
                ->where('is_active', true)
                ->value('id');
            if ((int) $actingEmployeeId !== $employeeId) {
                abort_unless($actor->can('payments.receive-for-others'), 403);
            }

            return Employee::query()->whereKey($employeeId)->where('is_active', true)->first()
                ?? throw ValidationException::withMessages(['received_by_employee_id' => __('payments.active_receiver_required')]);
        }

        $employee = Employee::query()->where('user_id', $actor->getKey())->where('is_active', true)->first();
        if ($employee === null) {
            throw ValidationException::withMessages(['payment' => __('payments.active_receiver_required')]);
        }

        return $employee;
    }

    private function paidAt(string $date): CarbonImmutable
    {
        $time = now(config('business.timezone', 'Asia/Colombo'))->format('H:i:s');

        return CarbonImmutable::parse($date.' '.$time, config('business.timezone', 'Asia/Colombo'));
    }

    private function money(string $value): BigDecimal
    {
        try {
            $amount = BigDecimal::of($value)->toScale(2);
        } catch (\InvalidArgumentException|MathException) {
            throw ValidationException::withMessages(['amount' => __('payments.invalid_amount')]);
        }
        if ($amount->isLessThanOrEqualTo(0)) {
            throw ValidationException::withMessages(['amount' => __('payments.amount_must_be_positive')]);
        }

        return $amount;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
