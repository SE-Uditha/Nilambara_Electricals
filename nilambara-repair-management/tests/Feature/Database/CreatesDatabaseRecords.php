<?php

namespace Tests\Feature\Database;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Employees\Models\Employee;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\UnitOfMeasure;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Payments\Models\Payment;
use App\Modules\Quotations\Models\Quotation;
use App\Modules\RepairJobs\Models\RepairJob;
use Closure;
use Illuminate\Database\QueryException;

trait CreatesDatabaseRecords
{
    protected function code(string $prefix): string
    {
        return $prefix.'-'.bin2hex(random_bytes(6));
    }

    protected function employee(array $attributes = []): Employee
    {
        return Employee::create(array_replace([
            'employee_code' => $this->code('EMP'),
            'name' => 'Database Test Employee',
        ], $attributes));
    }

    protected function customerAttributes(array $attributes = []): array
    {
        return array_replace([
            'customer_code' => $this->code('CUS'),
            'customer_type' => 'individual',
            'customer_name' => 'Database Test Customer',
            'address' => 'Test address, Colombo',
            'mobile_number' => '0771234567',
            'registration_date' => '2026-10-18',
        ], $attributes);
    }

    protected function customer(array $attributes = []): Customer
    {
        return Customer::create($this->customerAttributes($attributes));
    }

    protected function job(?Customer $customer = null, array $attributes = []): RepairJob
    {
        return RepairJob::create(array_replace([
            'job_number' => $this->code('JOB'),
            'customer_id' => ($customer ?? $this->customer())->id,
            'item_name' => 'Test motor',
            'brand' => 'Test brand',
            'received_at' => '2026-10-18 09:00:00',
            'received_by_employee_id' => $this->employee()->id,
        ], $attributes));
    }

    protected function invoice(?Customer $customer = null, array $attributes = []): Invoice
    {
        return Invoice::create(array_replace([
            'invoice_number' => $this->code('INV'),
            'invoice_date' => '2026-10-18',
            'customer_id' => ($customer ?? $this->customer())->id,
            'cashier_employee_id' => $this->employee()->id,
            'subtotal' => '0.00',
            'total' => '0.00',
        ], $attributes));
    }

    protected function part(array $attributes = []): SparePart
    {
        $unit = UnitOfMeasure::create([
            'code' => $this->code('UNIT'),
            'name' => 'Test unit',
            'quantity_scale' => 3,
        ]);

        return SparePart::create(array_replace([
            'sku' => $this->code('SKU'),
            'name' => 'Test spare part',
            'unit_id' => $unit->id,
        ], $attributes));
    }

    protected function location(): StockLocation
    {
        return StockLocation::create([
            'code' => $this->code('LOC'),
            'name' => 'Test stock location',
        ]);
    }

    protected function payment(?Customer $customer = null, array $attributes = []): Payment
    {
        $customer ??= $this->customer();

        return Payment::create(array_replace([
            'payment_number' => $this->code('PAY'),
            'customer_id' => $customer->id,
            'customer_scope_id' => $customer->id,
            'payment_type' => 'invoice',
            'method' => 'cash',
            'amount' => '50.00',
            'paid_at' => '2026-10-18 10:00:00',
            'received_by_employee_id' => $this->employee()->id,
            'idempotency_key' => $this->code('PAY-OP'),
        ], $attributes));
    }

    protected function quotation(?RepairJob $job = null, array $attributes = []): Quotation
    {
        $job ??= $this->job();

        return Quotation::create(array_replace([
            'quotation_number' => $this->code('QUO'),
            'quotation_date' => '2026-10-18',
            'repair_job_id' => $job->id,
            'customer_id' => $job->customer_id,
            'proposed_repair_description' => 'Test repair proposal',
            'created_by_user_id' => User::factory()->create()->id,
        ], $attributes));
    }

    protected function movement(?SparePart $part = null, ?StockLocation $location = null, array $attributes = []): StockMovement
    {
        return StockMovement::create(array_replace([
            'spare_part_id' => ($part ?? $this->part())->id,
            'location_id' => ($location ?? $this->location())->id,
            'movement_type' => 'opening',
            'quantity_delta' => '10.000',
            'occurred_at' => '2026-10-18 09:00:00',
            'reference_number' => $this->code('STK'),
            'recorded_by_user_id' => User::factory()->create()->id,
            'reason' => 'Database test fixture',
            'idempotency_key' => $this->code('STK-OP'),
        ], $attributes));
    }

    protected function assertDatabaseRejects(Closure $operation): void
    {
        try {
            $operation();
        } catch (QueryException $exception) {
            // Reject syntax/missing-column errors: this helper requires an
            // integrity error, including MySQL's missing-required-value code.
            $state = (string) ($exception->errorInfo[0] ?? '');
            $number = (int) ($exception->errorInfo[1] ?? 0);

            $this->assertTrue(
                str_starts_with($state, '23') || in_array($number, [19, 1048, 1062, 1364, 1451, 1452, 3819, 4025], true),
                'Expected an integrity violation, received SQLSTATE '.$state.' / '.$number,
            );

            return;
        }

        $this->fail('The database accepted a record that violates the integrity contract.');
    }
}
