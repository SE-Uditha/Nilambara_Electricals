<?php

namespace App\Modules\Invoices\Services;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTerms;
use App\Models\User;
use App\Modules\CreditManagement\Models\CustomerAccountEntry;
use App\Modules\Customers\Models\Customer;
use App\Modules\Employees\Models\Employee;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceHistory;
use App\Modules\Invoices\Models\InvoiceItem;
use App\Modules\Invoices\Models\InvoiceJob;
use App\Modules\Payments\Services\PaymentAllocationService;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private readonly InvoiceCalculationService $calculator,
        private readonly InvoiceBalanceService $balances,
        private readonly PaymentAllocationService $paymentAllocations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Invoice
    {
        return DB::transaction(function () use ($data, $user): Invoice {
            $customer = Customer::query()->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();
            if (! $customer->is_active) {
                throw ValidationException::withMessages(['customer_id' => __('invoices.active_customer_required')]);
            }
            $cashier = $this->activeEmployeeFor($user);
            $jobIds = $this->jobIds($data);
            $jobs = $this->lockEligibleJobs($jobIds, $customer);

            $invoice = Invoice::query()->create([
                'invoice_number' => 'TEMP-'.Str::uuid(),
                'invoice_date' => $data['invoice_date'],
                'customer_id' => $customer->getKey(),
                'subtotal' => '0.00',
                'discount_amount' => '0.00',
                'total' => '0.00',
                'payment_terms' => $data['payment_terms'],
                'payment_method' => $data['payment_method'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'status' => DocumentStatus::Draft->value,
                'cashier_employee_id' => $cashier->getKey(),
                'collected_by' => $this->nullableTrim($data['collected_by'] ?? null),
                'vehicle_number' => $this->nullableTrim($data['vehicle_number'] ?? null),
                'delivery_at' => $data['delivery_at'] ?? null,
                'delivery_information' => $this->nullableTrim($data['delivery_information'] ?? null),
                'special_notes' => $this->nullableTrim($data['special_notes'] ?? null),
                'document_snapshot' => [],
            ]);
            $invoice->invoice_number = $this->numberFor((int) $invoice->getKey());
            DB::table('invoices')->where('id', $invoice->getKey())->update([
                'invoice_number' => $invoice->invoice_number,
            ]);
            $invoice->syncOriginalAttribute('invoice_number');

            foreach ($jobs as $job) {
                $this->linkJob($invoice, $job, $user);
            }
            $this->updateTotals($invoice);
            $invoice->document_snapshot = $this->snapshot($invoice->refresh()->load($this->snapshotRelations()));
            $invoice->save();
            $this->recordHistory($invoice, $user, 'draft_created', newValues: $this->summary($invoice));
            BusinessAudit::record($user, 'invoice.draft_created', Invoice::class, (int) $invoice->getKey(), newValues: $this->summary($invoice));

            if (($data['intent'] ?? 'draft') === 'issue') {
                return $this->issueLocked($invoice, $user);
            }

            return $invoice->refresh();
        });
    }

    /**
     * Draft editing preserves original linked jobs and prices. New jobs can be
     * appended, but links and lines are never deleted or silently repriced.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(Invoice $invoice, array $data, User $user): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $user): Invoice {
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== DocumentStatus::Draft) {
                throw ValidationException::withMessages(['invoice' => __('invoices.only_draft_editable')]);
            }

            $customer = Customer::query()->whereKey($locked->customer_id)->lockForUpdate()->firstOrFail();
            $cashier = $this->activeEmployeeFor($user);
            $jobIds = $this->jobIds($data);
            $existingIds = $locked->invoiceJobs()->orderBy('repair_job_id')->pluck('repair_job_id')->map(fn ($id): int => (int) $id)->all();
            if (array_diff($existingIds, $jobIds) !== []) {
                throw ValidationException::withMessages(['repair_job_ids' => __('invoices.draft_jobs_cannot_be_removed')]);
            }

            $newIds = array_values(array_diff($jobIds, $existingIds));
            $newJobs = $newIds === [] ? collect() : $this->lockEligibleJobs($newIds, $customer);

            $old = $this->summary($locked);
            $locked->invoice_date = $data['invoice_date'];
            $locked->payment_terms = $data['payment_terms'];
            $locked->payment_method = $data['payment_method'] ?? null;
            $locked->due_date = $data['due_date'] ?? null;
            $locked->cashier_employee_id = $cashier->getKey();
            $locked->collected_by = $this->nullableTrim($data['collected_by'] ?? null);
            $locked->vehicle_number = $this->nullableTrim($data['vehicle_number'] ?? null);
            $locked->delivery_at = $data['delivery_at'] ?? null;
            $locked->delivery_information = $this->nullableTrim($data['delivery_information'] ?? null);
            $locked->special_notes = $this->nullableTrim($data['special_notes'] ?? null);
            $locked->save();

            foreach ($newJobs as $job) {
                $this->linkJob($locked, $job, $user);
            }
            $this->updateTotals($locked);
            $locked->document_snapshot = $this->snapshot($locked->refresh()->load($this->snapshotRelations()));
            $locked->save();
            $this->recordHistory($locked, $user, 'draft_updated', $old, $this->summary($locked));
            BusinessAudit::record($user, 'invoice.draft_updated', Invoice::class, (int) $locked->getKey(), $old, $this->summary($locked));

            if (($data['intent'] ?? 'draft') === 'issue') {
                return $this->issueLocked($locked, $user);
            }

            return $locked->refresh();
        });
    }

    public function issue(Invoice $invoice, User $user): Invoice
    {
        return DB::transaction(function () use ($invoice, $user): Invoice {
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            return $this->issueLocked($locked, $user);
        });
    }

    public function cancel(Invoice $invoice, string $reason, User $user): Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $user): Invoice {
            $locked = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [DocumentStatus::Draft, DocumentStatus::Finalized], true)) {
                throw ValidationException::withMessages(['invoice' => __('invoices.only_active_invoices_cancelled')]);
            }

            $balances = $this->balances->summary($locked);
            if (BigDecimal::of($balances['total_paid'])->isGreaterThan(0)) {
                throw ValidationException::withMessages(['invoice' => __('invoices.payment_reversal_required')]);
            }

            $old = $this->summary($locked);
            $locked->status = DocumentStatus::Cancelled;
            $locked->cancelled_at = now(config('business.timezone', 'Asia/Colombo'));
            $locked->cancelled_by_user_id = $user->getKey();
            $locked->cancellation_reason = trim($reason);
            $locked->save();
            $new = [...$this->summary($locked), 'cancellation_reason' => $locked->cancellation_reason];
            $this->recordHistory($locked, $user, 'cancelled', $old, $new);
            BusinessAudit::record($user, 'invoice.cancelled', Invoice::class, (int) $locked->getKey(), $old, $new);

            return $locked->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Invoice $invoice): array
    {
        $invoice->loadMissing($this->snapshotRelations());
        $customer = $invoice->customer;
        $jobs = $invoice->invoiceJobs->map(function (InvoiceJob $link): array {
            $snapshot = $link->job_snapshot ?? [];

            return [
                ...$snapshot,
                'job_number' => $snapshot['job_number'] ?? $link->repairJob?->automatic_number ?? $link->repairJob?->job_number,
                'job_amount' => (string) $link->job_amount,
                'lines' => $link->invoiceItems->map(fn (InvoiceItem $item): array => [
                    'description' => $item->description,
                    'item_type' => $item->item_type,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'line_total' => (string) $item->line_total,
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'customer' => [
                'id' => $invoice->customer_id,
                'code' => $customer?->customer_code,
                'name' => $customer?->display_name,
                'address' => $customer?->address,
                'mobile_number' => $customer?->mobile_number,
                'customer_type' => $customer?->customer_type?->value,
            ],
            'jobs' => $jobs,
            'subtotal' => (string) $invoice->subtotal,
            'discount_amount' => (string) $invoice->discount_amount,
            'total' => (string) $invoice->total,
            'payment_terms' => $invoice->payment_terms?->value,
            'payment_method' => $invoice->payment_method?->value,
            'cashier' => $invoice->cashierEmployee?->name,
            'collected_by' => $invoice->collected_by,
            'vehicle_number' => $invoice->vehicle_number,
            'delivery_at' => $invoice->delivery_at?->toIso8601String(),
            'delivery_information' => $invoice->delivery_information,
            'special_notes' => $invoice->special_notes,
        ];
    }

    private function issueLocked(Invoice $invoice, User $user): Invoice
    {
        if ($invoice->status !== DocumentStatus::Draft) {
            throw ValidationException::withMessages(['invoice' => __('invoices.only_draft_issuable')]);
        }
        if (! $invoice->invoiceJobs()->exists()) {
            throw ValidationException::withMessages(['repair_job_ids' => __('invoices.invoice_requires_job')]);
        }

        $invoice->status = DocumentStatus::Finalized;
        $invoice->finalized_at = now(config('business.timezone', 'Asia/Colombo'));
        $invoice->document_snapshot = $this->snapshot($invoice->load($this->snapshotRelations()));
        $invoice->save();
        $this->paymentAllocations->applyAvailableAdvances($invoice, $user);
        CustomerAccountEntry::query()->firstOrCreate(
            ['invoice_id' => $invoice->getKey()],
            [
                'customer_id' => $invoice->customer_id,
                'entry_type' => 'invoice',
                'amount' => (string) $invoice->total,
                'occurred_at' => $invoice->finalized_at,
                'reference_number' => $invoice->invoice_number,
                'description' => __('payments.invoice_issued_description', ['invoice' => $invoice->invoice_number]),
                'recorded_by_user_id' => $user->getKey(),
                'idempotency_key' => 'invoice-ledger:'.$invoice->getKey(),
            ],
        );
        $this->recordHistory($invoice, $user, 'issued', newValues: $this->summary($invoice));
        BusinessAudit::record($user, 'invoice.issued', Invoice::class, (int) $invoice->getKey(), newValues: $this->summary($invoice));

        return $invoice->refresh();
    }

    /**
     * @param  array<int, int>  $jobIds
     * @return Collection<int, RepairJob>
     */
    private function lockEligibleJobs(array $jobIds, Customer $customer)
    {
        $jobs = RepairJob::query()->whereIn('id', $jobIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->with(['quotations', 'jobPartUsages.sparePart'])
            ->get();
        if ($jobs->count() !== count($jobIds)) {
            throw ValidationException::withMessages(['repair_job_ids' => __('invoices.jobs_not_available')]);
        }

        foreach ($jobs as $job) {
            if ((int) $job->customer_id !== (int) $customer->getKey()) {
                throw ValidationException::withMessages(['repair_job_ids' => __('invoices.jobs_must_share_customer')]);
            }
            if (InvoiceJob::query()->where('repair_job_id', $job->getKey())->exists()) {
                throw ValidationException::withMessages(['repair_job_ids' => __('invoices.job_already_billed', ['job' => $job->automatic_number ?: $job->job_number])]);
            }
            $this->calculator->billableLines($job);
        }

        return $jobs;
    }

    private function linkJob(Invoice $invoice, RepairJob $job, User $user): void
    {
        $billing = $this->calculator->billableLines($job);
        $quotation = $billing['quotation'];
        $link = $invoice->invoiceJobs()->create([
            'repair_job_id' => $job->getKey(),
            'customer_id' => $invoice->customer_id,
            'job_amount' => $billing['amount'],
            'job_snapshot' => [
                'job_number' => $job->automatic_number ?: $job->job_number,
                'item_name' => $job->item_name,
                'repair_description' => $job->repair_description ?: $job->item_description,
                'reported_fault' => $job->reported_fault,
                'status' => $job->status?->value,
                'quotation_id' => $quotation?->getKey(),
                'quotation_number' => $quotation?->quotation_number,
                'quotation_total' => $quotation?->total,
                'parts_details' => $billing['parts_details'],
            ],
        ]);

        $lineNumber = (int) $invoice->invoiceItems()->max('line_number');
        foreach ($billing['lines'] as $line) {
            $invoice->invoiceItems()->create([
                'invoice_job_id' => $link->getKey(),
                'line_number' => ++$lineNumber,
                'quotation_item_id' => $line['quotation_item_id'],
                'item_type' => $line['item_type'],
                'spare_part_id' => $line['spare_part_id'],
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_amount' => '0.00',
                'line_total' => $line['line_total'],
            ]);
        }

        $linkedValues = [
            'repair_job_id' => $job->getKey(),
            'job_number' => $job->automatic_number ?: $job->job_number,
            'job_amount' => $billing['amount'],
        ];
        $this->recordHistory($invoice, $user, 'job_linked', newValues: $linkedValues);
        BusinessAudit::record($user, 'invoice.job_linked', Invoice::class, (int) $invoice->getKey(), newValues: [
            ...$linkedValues,
        ]);
    }

    private function updateTotals(Invoice $invoice): void
    {
        $subtotal = $invoice->invoiceJobs()->pluck('job_amount')
            ->reduce(fn (BigDecimal $sum, $amount): BigDecimal => $sum->plus($this->amount((string) $amount)), BigDecimal::zero()->toScale(2));
        if ($subtotal->isGreaterThan(BigDecimal::of('99999999999999999.99'))) {
            throw ValidationException::withMessages(['repair_job_ids' => __('invoices.invoice_total_exceeded')]);
        }
        $invoice->subtotal = (string) $subtotal->toScale(2, RoundingMode::HalfUp);
        $invoice->discount_amount = '0.00';
        $invoice->total = $invoice->subtotal;
        $invoice->save();
    }

    /**
     * @return array<int, string>
     */
    private function snapshotRelations(): array
    {
        return [
            'customer',
            'cashierEmployee',
            'invoiceJobs.repairJob',
            'invoiceJobs.invoiceItems',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function jobIds(array $data): array
    {
        $ids = array_values(array_unique(array_map('intval', $data['repair_job_ids'] ?? [])));
        if ($ids === [] || count($ids) !== count($data['repair_job_ids'] ?? [])) {
            throw ValidationException::withMessages(['repair_job_ids' => __('invoices.select_unique_jobs')]);
        }

        sort($ids);

        return $ids;
    }

    private function activeEmployeeFor(User $user): Employee
    {
        $employee = $user->employee()->where('is_active', true)->first();
        if ($employee === null) {
            throw ValidationException::withMessages(['cashier' => __('invoices.cashier_employee_required')]);
        }

        return $employee;
    }

    private function numberFor(int $id): string
    {
        return config('business.invoice_number_prefix', 'INV-').str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Invoice $invoice): array
    {
        return [
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status instanceof DocumentStatus ? $invoice->status->value : $invoice->status,
            'customer_id' => $invoice->customer_id,
            'subtotal' => (string) $invoice->subtotal,
            'total' => (string) $invoice->total,
            'payment_terms' => $invoice->payment_terms instanceof PaymentTerms ? $invoice->payment_terms->value : $invoice->payment_terms,
            'payment_method' => $invoice->payment_method instanceof PaymentMethod ? $invoice->payment_method->value : $invoice->payment_method,
            'repair_job_ids' => $invoice->invoiceJobs()->orderBy('repair_job_id')->pluck('repair_job_id')->all(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function recordHistory(Invoice $invoice, User $user, string $event, ?array $oldValues = null, ?array $newValues = null): void
    {
        InvoiceHistory::query()->create([
            'invoice_id' => $invoice->getKey(),
            'actor_user_id' => $user->getKey(),
            'event_type' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'event_at' => now(config('business.timezone', 'Asia/Colombo')),
        ]);
    }

    private function amount(string $amount): string
    {
        try {
            return (string) BigDecimal::of($amount)->toScale(2, RoundingMode::HalfUp);
        } catch (\InvalidArgumentException|MathException) {
            throw ValidationException::withMessages(['repair_job_ids' => __('invoices.invalid_job_amount')]);
        }
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
