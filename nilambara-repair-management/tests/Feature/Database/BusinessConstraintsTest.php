<?php

namespace Tests\Feature\Database;

use App\Models\User;
use App\Modules\Inventory\Models\JobPartUsage;
use App\Modules\Invoices\Models\InvoiceJob;
use App\Modules\JobTracking\Models\JobStatusHistory;
use App\Modules\Payments\Models\PaymentAllocation;
use App\Modules\Payments\Models\PaymentReversal;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class BusinessConstraintsTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    public function test_job_numbers_cannot_be_duplicated(): void
    {
        $job = $this->job();

        $this->assertDatabaseRejects(fn () => $this->job(null, ['job_number' => $job->job_number]));
    }

    public function test_invoice_numbers_cannot_be_duplicated(): void
    {
        $invoice = $this->invoice();

        $this->assertDatabaseRejects(fn () => $this->invoice(null, ['invoice_number' => $invoice->invoice_number]));
    }

    public function test_quotation_numbers_cannot_be_duplicated(): void
    {
        $quotation = $this->quotation();

        $this->assertDatabaseRejects(fn () => $this->quotation(null, ['quotation_number' => $quotation->quotation_number]));
    }

    public function test_customer_codes_and_skus_cannot_be_duplicated(): void
    {
        $customer = $this->customer();
        $part = $this->part();

        $this->assertDatabaseRejects(fn () => $this->customer(['customer_code' => $customer->customer_code]));
        $this->assertDatabaseRejects(fn () => $this->part(['sku' => $part->sku]));
    }

    public function test_optional_barcodes_allow_nulls_but_nonnull_values_are_unique(): void
    {
        $this->part(['barcode' => null]);
        $this->part(['barcode' => null]);
        $part = $this->part(['barcode' => 'TEST-BARCODE']);

        $this->assertDatabaseRejects(fn () => $this->part(['barcode' => $part->barcode]));
    }

    public function test_both_composite_foreign_keys_reject_cross_customer_invoice_job_links(): void
    {
        $firstCustomer = $this->customer();
        $secondCustomer = $this->customer();
        $invoice = $this->invoice($firstCustomer);
        $job = $this->job($secondCustomer);

        foreach ([$firstCustomer->id, $secondCustomer->id] as $customerId) {
            $this->assertDatabaseRejects(fn () => DB::table('invoice_jobs')->insert([
                'invoice_id' => $invoice->id,
                'repair_job_id' => $job->id,
                'customer_id' => $customerId,
                'job_amount' => '0.00',
            ]));
        }
    }

    public function test_one_job_cannot_be_reserved_for_billing_by_two_invoices(): void
    {
        $customer = $this->customer();
        $job = $this->job($customer);
        $firstInvoice = $this->invoice($customer);
        $secondInvoice = $this->invoice($customer);
        InvoiceJob::create([
            'invoice_id' => $firstInvoice->id,
            'repair_job_id' => $job->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertDatabaseRejects(fn () => DB::table('invoice_jobs')->insert([
            'invoice_id' => $secondInvoice->id,
            'repair_job_id' => $job->id,
            'customer_id' => $customer->id,
        ]));
    }

    public function test_orphan_foreign_keys_and_parent_deletion_are_rejected_by_the_database(): void
    {
        $customer = $this->customer();
        $job = $this->job($customer);
        $invoice = $this->invoice($customer);
        InvoiceJob::create([
            'invoice_id' => $invoice->id,
            'repair_job_id' => $job->id,
            'customer_id' => $customer->id,
        ]);

        $this->assertDatabaseRejects(fn () => DB::table('customers')->where('id', $customer->id)->delete());
        $this->assertDatabaseRejects(fn () => DB::table('invoices')->where('id', $invoice->id)->delete());
        $this->assertDatabaseRejects(fn () => DB::table('repair_jobs')->insert([
            'job_number' => $this->code('ORPHAN'),
            'customer_id' => 999999999,
            'item_name' => 'Orphaned item',
            'brand' => 'Test brand',
            'received_by_employee_id' => $this->employee()->id,
        ]));
    }

    public function test_customer_type_requires_the_corresponding_name_and_nonblank_contact_fields(): void
    {
        $this->assertDatabaseRejects(fn () => $this->customer(['customer_name' => null]));
        $this->assertDatabaseRejects(fn () => $this->customer(['customer_type' => 'company', 'company_name' => null]));
        $this->assertDatabaseRejects(fn () => $this->customer(['address' => '   ']));
        $this->assertDatabaseRejects(fn () => $this->customer(['mobile_number' => '   ']));

        $company = $this->customer([
            'customer_type' => 'company',
            'customer_name' => null,
            'company_name' => 'Test Company',
        ]);

        $this->assertSame('Test Company', $company->company_name);
    }

    public function test_nonpositive_payments_and_invalid_header_totals_are_rejected(): void
    {
        $this->assertDatabaseRejects(fn () => $this->payment(null, ['amount' => '0.00']));
        $this->assertDatabaseRejects(fn () => $this->payment(null, ['amount' => '-1.00']));
        $this->assertDatabaseRejects(fn () => $this->invoice(null, ['subtotal' => '100.00', 'total' => '99.00']));
    }

    public function test_payment_and_allocation_idempotency_keys_are_unique(): void
    {
        $customer = $this->customer();
        $payment = $this->payment($customer);
        $invoice = $this->invoice($customer);

        $this->assertDatabaseRejects(fn () => $this->payment($customer, ['idempotency_key' => $payment->idempotency_key]));

        $attributes = [
            'payment_id' => $payment->id,
            'customer_id' => $customer->id,
            'customer_scope_id' => $customer->id,
            'target_type' => 'invoice',
            'invoice_id' => $invoice->id,
            'amount' => '10.00',
            'allocated_at' => '2026-10-18 10:00:00',
            'allocated_by_user_id' => User::factory()->create()->id,
            'idempotency_key' => $this->code('ALLOC'),
        ];
        PaymentAllocation::create($attributes);

        $this->assertDatabaseRejects(fn () => PaymentAllocation::create($attributes));
    }

    public function test_payment_allocations_cannot_cross_customer_boundaries_or_name_multiple_targets(): void
    {
        $firstCustomer = $this->customer();
        $secondCustomer = $this->customer();
        $payment = $this->payment($firstCustomer);
        $invoice = $this->invoice($secondCustomer);
        $user = User::factory()->create();

        foreach ([$firstCustomer->id, $secondCustomer->id] as $customerId) {
            $this->assertDatabaseRejects(fn () => DB::table('payment_allocations')->insert([
                'payment_id' => $payment->id,
                'customer_id' => $customerId,
                'customer_scope_id' => $customerId,
                'target_type' => 'invoice',
                'invoice_id' => $invoice->id,
                'amount' => '10.00',
                'allocated_at' => '2026-10-18 10:00:00',
                'allocated_by_user_id' => $user->id,
                'idempotency_key' => $this->code('ALLOC'),
            ]));
        }

        $job = $this->job($firstCustomer);
        $sameCustomerInvoice = $this->invoice($firstCustomer);

        $this->assertDatabaseRejects(fn () => DB::table('payment_allocations')->insert([
            'payment_id' => $payment->id,
            'customer_id' => $firstCustomer->id,
            'customer_scope_id' => $firstCustomer->id,
            'target_type' => 'invoice',
            'invoice_id' => $sameCustomerInvoice->id,
            'repair_job_id' => $job->id,
            'amount' => '10.00',
            'allocated_at' => '2026-10-18 10:00:00',
            'allocated_by_user_id' => $user->id,
            'idempotency_key' => $this->code('ALLOC'),
        ]));
    }

    public function test_stock_operations_reject_duplicate_keys_duplicate_issue_sources_and_invalid_signs(): void
    {
        $part = $this->part();
        $location = $this->location();
        $opening = $this->movement($part, $location);

        $this->assertDatabaseRejects(fn () => $this->movement(null, null, ['idempotency_key' => $opening->idempotency_key]));

        $usage = JobPartUsage::create([
            'repair_job_id' => $this->job()->id,
            'spare_part_id' => $part->id,
            'location_id' => $location->id,
            'quantity' => '2.000',
            'requested_by_user_id' => User::factory()->create()->id,
            'idempotency_key' => $this->code('USAGE'),
        ]);
        $this->movement($part, $location, [
            'movement_type' => 'job_issue',
            'quantity_delta' => '-2.000',
            'job_part_usage_id' => $usage->id,
        ]);

        $this->assertDatabaseRejects(fn () => $this->movement($part, $location, [
            'movement_type' => 'job_issue',
            'quantity_delta' => '-2.000',
            'job_part_usage_id' => $usage->id,
        ]));
        $this->assertDatabaseRejects(fn () => $this->movement(null, null, ['quantity_delta' => '-1.000']));
        $this->assertDatabaseRejects(fn () => $this->movement(null, null, ['quantity_delta' => '0.000']));
    }

    public function test_job_identifiers_cannot_be_changed_through_the_model(): void
    {
        $job = $this->job();

        $this->expectException(LogicException::class);

        $job->update(['job_number' => 'REPLACED-JOB-NUMBER']);
    }

    public function test_status_history_cannot_be_updated_through_the_model(): void
    {
        $history = $this->statusHistory();

        $this->expectException(LogicException::class);

        $history->update(['notes' => 'Overwritten history']);
    }

    public function test_status_history_cannot_be_deleted_through_the_model(): void
    {
        $history = $this->statusHistory();

        $this->expectException(LogicException::class);

        $history->delete();
    }

    public function test_repair_payments_must_have_a_customer(): void
    {
        $this->assertDatabaseRejects(fn () => $this->payment(null, [
            'customer_id' => null, 'customer_scope_id' => 0, 'payment_type' => 'invoice',
        ]));
    }

    public function test_allocation_releases_cannot_use_a_reversal_of_another_payment(): void
    {
        $user = User::factory()->create();
        $customer = $this->customer();
        $firstPayment = $this->payment($customer);
        $secondPayment = $this->payment($customer);
        $allocation = PaymentAllocation::create([
            'payment_id' => $firstPayment->id,
            'customer_id' => $customer->id,
            'customer_scope_id' => $customer->id,
            'target_type' => 'invoice',
            'invoice_id' => $this->invoice($customer)->id,
            'amount' => '20.00',
            'allocated_at' => '2026-10-18 10:00:00',
            'allocated_by_user_id' => $user->id,
            'idempotency_key' => $this->code('ALLOC'),
        ]);
        $reversal = PaymentReversal::create([
            'payment_id' => $secondPayment->id,
            'customer_id' => $customer->id,
            'customer_scope_id' => $customer->id,
            'reversal_type' => 'refund',
            'amount' => '5.00',
            'reversed_at' => '2026-10-18 11:00:00',
            'reversed_by_user_id' => $user->id,
            'reason' => 'Test reversal of a different payment',
            'idempotency_key' => $this->code('REFUND'),
        ]);

        $this->assertDatabaseRejects(fn () => DB::table('payment_allocation_releases')->insert([
            'payment_allocation_id' => $allocation->id,
            'payment_id' => $firstPayment->id,
            'payment_reversal_id' => $reversal->id,
            'amount' => '5.00',
            'reason_type' => 'refund',
            'released_at' => '2026-10-18 11:00:00',
            'released_by_user_id' => $user->id,
            'idempotency_key' => $this->code('RELEASE'),
        ]));
    }

    public function test_stock_movements_cannot_use_a_source_from_another_location(): void
    {
        $part = $this->part();
        $location = $this->location();
        $usage = JobPartUsage::create([
            'repair_job_id' => $this->job()->id,
            'spare_part_id' => $part->id,
            'location_id' => $location->id,
            'quantity' => '2.000',
            'requested_by_user_id' => User::factory()->create()->id,
            'idempotency_key' => $this->code('USAGE'),
        ]);

        $this->assertDatabaseRejects(fn () => $this->movement($part, $this->location(), [
            'movement_type' => 'job_issue',
            'quantity_delta' => '-2.000',
            'job_part_usage_id' => $usage->id,
        ]));
    }

    public function test_the_job_status_enum_matches_all_seventeen_brd_statuses(): void
    {
        $enum = (new RepairJob)->getCasts()['status'];
        $values = array_map(fn ($case) => $case->value, $enum::cases());

        $this->assertSame([
            'Received',
            'Pending Inspection',
            'Under Inspection',
            'Quotation Pending',
            'Waiting for Customer Approval',
            'Customer Contact Failed',
            'Advance Payment Pending',
            'Waiting for Spare Parts',
            'Under Repair',
            'Price Modification',
            "Re \u{2013} Assembling",
            'Repair Completed',
            'Ready for Collection',
            'Customer Contact Failed After Ready',
            'Delivered',
            'Cancelled',
            'Unable to Repair',
        ], $values);
    }

    private function statusHistory(): JobStatusHistory
    {
        return JobStatusHistory::create([
            'repair_job_id' => $this->job()->id,
            'new_status' => 'Received',
            'changed_by_user_id' => User::factory()->create()->id,
            'changed_at' => '2026-10-18 09:00:00',
        ]);
    }
}
