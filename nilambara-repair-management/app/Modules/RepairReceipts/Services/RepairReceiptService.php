<?php

namespace App\Modules\RepairReceipts\Services;

use App\Enums\JobStatus;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Employees\Models\Employee;
use App\Modules\JobCards\Models\JobCard;
use App\Modules\JobTracking\Models\JobStatusHistory;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\RepairReceipts\Models\RepairReceipt;
use App\Support\BusinessAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepairReceiptService
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, User $user): RepairReceipt
    {
        return DB::transaction(function () use ($data, $user): RepairReceipt {
            $customer = Customer::query()->whereKey($data['customer_id'])
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($customer === null) {
                throw ValidationException::withMessages([
                    'customer_id' => __('ui.active_customer_required'),
                ]);
            }

            $employee = Employee::query()->whereKey($data['received_by_employee_id'])
                ->where('is_active', true)
                ->first();

            if ($employee === null) {
                throw ValidationException::withMessages([
                    'received_by_employee_id' => __('ui.active_employee_required'),
                ]);
            }

            $receivedAt = now();
            $job = RepairJob::query()->create([
                'job_number' => trim($data['job_number']),
                'customer_id' => $customer->getKey(),
                'item_name' => trim($data['item_name']),
                'brand' => $this->nullableTrim($data['brand'] ?? null),
                'model' => $this->nullableTrim($data['model'] ?? null),
                'item_description' => $this->nullableTrim($data['item_description'] ?? null),
                'reported_fault' => $this->nullableTrim($data['reported_fault'] ?? null),
                'accessories_received' => $this->nullableTrim($data['accessories_received'] ?? null),
                'physical_condition' => $this->nullableTrim($data['physical_condition'] ?? null),
                'visible_damage' => $this->nullableTrim($data['visible_damage'] ?? null),
                'job_type' => $data['job_type'],
                'received_at' => $receivedAt,
                'received_by_employee_id' => $employee->getKey(),
                'labour_cost' => $data['labor_cost'] ?? '0.00',
                'labour_charge' => $data['labor_cost'] ?? '0.00',
                'total_job_amount' => $data['labor_cost'] ?? '0.00',
                'status' => ($data['intent'] ?? null) === 'cancel_payments' ? JobStatus::Cancelled->value : JobStatus::Received->value,
                'special_notes' => $this->nullableTrim($data['special_notes'] ?? null),
            ]);

            if (($data['intent'] ?? null) === 'cancel_payments') {
                $job->forceFill(['cancelled_at' => $receivedAt, 'cancelled_by_user_id' => $user->getKey()])->save();
            }

            // The database-generated primary key is the concurrency-safe sequence.
            $jobReference = 'JOB-'.str_pad((string) $job->getKey(), 5, '0', STR_PAD_LEFT);
            DB::table('repair_jobs')->where('id', $job->getKey())->update([
                'automatic_number' => $jobReference,
                'updated_at' => $receivedAt,
            ]);
            $job->refresh();

            $advance = $this->currency($data['advance_payment'] ?? '0');
            $advancePayment = $advance !== '0.00'
                ? $this->recordAdvancePayment(
                    $job,
                    $user,
                    $advance,
                    $data['payment_method'],
                    $receivedAt,
                    (int) $employee->getKey(),
                )
                : null;

            $snapshot = [
                'job_reference' => $jobReference,
                'manual_job_number' => $job->job_number,
                'received_at' => $receivedAt->toIso8601String(),
                'job_type' => $job->job_type,
                'customer' => [
                    'customer_code' => $customer->customer_code,
                    'type' => $customer->customer_type->label(),
                    'name' => $customer->display_name,
                    'contact_person' => $customer->contact_person,
                    'address' => $customer->address,
                    'mobile_number' => $customer->mobile_number,
                ],
                'item' => [
                    'name' => $job->item_name,
                    'brand' => $job->brand,
                    'model' => $job->model,
                    'description' => $job->item_description,
                    'reported_fault' => $job->reported_fault,
                    'accessories_received' => $job->accessories_received,
                    'physical_condition' => $job->physical_condition,
                    'visible_damage' => $job->visible_damage,
                ],
                'advance_payment' => $this->currency($data['advance_payment'] ?? '0'),
                'advance_payment_number' => $advancePayment?->payment_number,
                'labor_cost' => $this->currency($data['labor_cost'] ?? '0.00'),
                'received_by' => $employee->name,
                'special_notes' => $job->special_notes,
            ];

            $receipt = RepairReceipt::query()->create([
                'repair_job_id' => $job->getKey(),
                'customer_id' => $customer->getKey(),
                'receipt_number' => $jobReference,
                'issued_at' => $receivedAt,
                'document_snapshot' => $snapshot,
                'special_notes' => $job->special_notes,
            ]);

            JobStatusHistory::query()->create([
                'repair_job_id' => $job->getKey(),
                'previous_status' => null,
                'new_status' => $job->status->value,
                'changed_by_user_id' => $user->getKey(),
                'changed_at' => $receivedAt,
                'notes' => __('ui.initial_status_note'),
            ]);

            JobCard::query()->create([
                'repair_job_id' => $job->getKey(),
                'paper_size' => 'A5',
            ]);

            BusinessAudit::record(
                $user,
                'repair_job.created',
                RepairJob::class,
                (int) $job->getKey(),
                newValues: [
                    'automatic_number' => $jobReference,
                    'job_number' => $job->job_number,
                    'customer_code' => $customer->customer_code,
                    'status' => $job->status->value,
                    'repair_receipt_id' => $receipt->getKey(),
                ],
            );
            BusinessAudit::record(
                $user,
                'repair_receipt.created',
                RepairReceipt::class,
                (int) $receipt->getKey(),
                newValues: [
                    'receipt_number' => $receipt->receipt_number,
                    'repair_job_id' => $job->getKey(),
                    'manual_job_number' => $job->job_number,
                    'advance_payment' => $advance,
                ],
            );

            return $receipt;
        });
    }

    private function recordAdvancePayment(
        RepairJob $job,
        User $user,
        string $amount,
        string $method,
        Carbon|CarbonImmutable $receivedAt,
        int $receiverEmployeeId,
    ): Payment {
        return $this->payments->receiveAdvance([
            'amount' => $amount,
            'payment_date' => $receivedAt->toDateString(),
            'method' => $method,
            'notes' => __('ui.repair_job_advance_note', ['job' => $job->automatic_number]),
            'idempotency_key' => 'repair-receipt-advance:'.$job->getKey(),
            'received_by_employee_id' => $receiverEmployeeId,
        ], $user, $job);
    }

    private function currency(mixed $value): string
    {
        $parts = explode('.', trim((string) $value), 2);
        $whole = ltrim($parts[0] === '' ? '0' : $parts[0], '0');
        $fraction = substr(str_pad($parts[1] ?? '', 2, '0'), 0, 2);

        return ($whole === '' ? '0' : $whole).'.'.$fraction;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
