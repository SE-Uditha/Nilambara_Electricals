<?php

namespace Tests\Feature\Database;

use App\Models\User;
use App\Modules\CreditManagement\Models\CustomerAccountEntry;
use App\Modules\Inventory\Models\JobPartUsage;
use App\Modules\Invoices\Models\InvoiceJob;
use App\Modules\JobTracking\Models\JobStatusHistory;
use App\Modules\Payments\Models\PaymentAllocation;
use App\Modules\Payments\Models\PaymentReversal;
use App\Modules\POS\Models\PosSale;
use App\Modules\POS\Models\PosSaleItem;
use App\Modules\Purchases\Models\GoodsReceivedNote;
use App\Modules\Purchases\Models\GoodsReceivedNoteItem;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Purchases\Models\PurchaseItem;
use App\Modules\RepairJobs\Models\JobInspection;
use App\Modules\RepairReceipts\Models\RepairReceipt;
use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessRelationshipsTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    public function test_customers_relate_to_their_jobs_receipts_invoices_payments_and_account_entries(): void
    {
        $user = User::factory()->create();
        $customer = $this->customer(['opening_balance' => '100.00']);
        $job = $this->job($customer);
        $receipt = RepairReceipt::create([
            'repair_job_id' => $job->id,
            'customer_id' => $customer->id,
            'receipt_number' => $this->code('REC'),
            'document_snapshot' => ['customer_name' => 'Name at receipt creation'],
        ]);
        $invoice = $this->invoice($customer);
        $payment = $this->payment($customer);
        $entry = CustomerAccountEntry::create([
            'customer_id' => $customer->id,
            'entry_type' => 'opening',
            'amount' => '100.00',
            'occurred_at' => '2026-10-18 09:00:00',
            'reference_number' => $this->code('OPEN'),
            'description' => 'Opening accounting value, not a sale',
            'recorded_by_user_id' => $user->id,
            'idempotency_key' => $this->code('OPEN-OP'),
        ]);

        $this->assertTrue($job->customer->is($customer));
        $this->assertTrue($invoice->customer->is($customer));
        $this->assertTrue($customer->repairJobs->contains($job));
        $this->assertTrue($customer->receipts->contains($receipt));
        $this->assertTrue($customer->invoices->contains($invoice));
        $this->assertTrue($customer->payments->contains($payment));
        $this->assertTrue($customer->accountEntries->contains($entry));
        $this->assertTrue($job->receipt->is($receipt));
        $this->assertSame(['customer_name' => 'Name at receipt creation'], $receipt->fresh()->document_snapshot);
    }

    public function test_repair_jobs_retain_multiple_status_history_inspection_and_parts_usage_records(): void
    {
        $user = User::factory()->create();
        $employee = $this->employee();
        $job = $this->job();
        $first = JobStatusHistory::create([
            'repair_job_id' => $job->id,
            'new_status' => 'Received',
            'changed_by_user_id' => $user->id,
            'changed_at' => '2026-10-18 09:00:00',
        ]);
        $second = JobStatusHistory::create([
            'repair_job_id' => $job->id,
            'previous_status' => 'Received',
            'new_status' => 'Under Inspection',
            'changed_by_user_id' => $user->id,
            'changed_at' => '2026-10-18 10:00:00',
        ]);
        $inspection = JobInspection::create([
            'repair_job_id' => $job->id,
            'inspected_at' => '2026-10-18 10:00:00',
            'inspected_by_employee_id' => $employee->id,
            'inspection_result' => 'Test inspection result',
        ]);
        $usage = JobPartUsage::create([
            'repair_job_id' => $job->id,
            'spare_part_id' => $this->part()->id,
            'location_id' => $this->location()->id,
            'quantity' => '1.000',
            'requested_by_user_id' => $user->id,
            'idempotency_key' => $this->code('USAGE'),
        ]);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $job->statusHistories->modelKeys());
        $this->assertTrue($job->inspections->contains($inspection));
        $this->assertTrue($job->partsUsages->contains($usage));
        $this->assertSame('Received', $first->fresh()->getRawOriginal('new_status'));
    }

    public function test_one_invoice_retains_multiple_jobs_and_each_jobs_amount(): void
    {
        $customer = $this->customer();
        $firstJob = $this->job($customer, ['labour_charge' => '120.10', 'total_job_amount' => '120.10']);
        $secondJob = $this->job($customer, ['labour_charge' => '79.90', 'total_job_amount' => '79.90']);
        $invoice = $this->invoice($customer, ['subtotal' => '200.00', 'total' => '200.00']);

        foreach ([[$firstJob, '120.10'], [$secondJob, '79.90']] as [$job, $amount]) {
            InvoiceJob::create([
                'invoice_id' => $invoice->id,
                'repair_job_id' => $job->id,
                'customer_id' => $customer->id,
                'job_amount' => $amount,
            ]);
        }

        $this->assertEqualsCanonicalizing([$firstJob->id, $secondJob->id], $invoice->repairJobs->modelKeys());
        $this->assertSame(['120.10', '79.90'], $invoice->invoiceJobs()->orderBy('id')->get()->pluck('job_amount')->all());
        $this->assertSame('200.00', $invoice->fresh()->total);
    }

    public function test_payments_allocations_and_reversals_retain_their_relationships(): void
    {
        $user = User::factory()->create();
        $customer = $this->customer();
        $invoice = $this->invoice($customer, ['subtotal' => '100.00', 'total' => '100.00']);
        $payment = $this->payment($customer);
        $allocation = PaymentAllocation::create([
            'payment_id' => $payment->id,
            'customer_id' => $customer->id,
            'customer_scope_id' => $customer->id,
            'target_type' => 'invoice',
            'invoice_id' => $invoice->id,
            'amount' => '50.00',
            'allocated_at' => '2026-10-18 10:00:00',
            'allocated_by_user_id' => $user->id,
            'idempotency_key' => $this->code('ALLOC'),
        ]);
        $reversal = PaymentReversal::create([
            'payment_id' => $payment->id,
            'customer_id' => $customer->id,
            'customer_scope_id' => $customer->id,
            'reversal_type' => 'refund',
            'amount' => '10.00',
            'reversed_at' => '2026-10-18 11:00:00',
            'reversed_by_user_id' => $user->id,
            'reason' => 'Test refund record',
            'idempotency_key' => $this->code('REFUND'),
        ]);

        $this->assertTrue($payment->allocations->contains($allocation));
        $this->assertTrue($payment->reversals->contains($reversal));
        $this->assertTrue($allocation->payment->is($payment));
        $this->assertTrue($allocation->invoice->is($invoice));
        $this->assertTrue($invoice->paymentAllocations->contains($allocation));
        $this->assertSame('50.00', $allocation->fresh()->amount);
        $this->assertSame('50.00', $payment->fresh()->amount);
    }

    public function test_suppliers_purchases_and_goods_received_notes_share_the_ordered_parts(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create(['supplier_code' => $this->code('SUP'), 'name' => 'Test supplier']);
        $part = $this->part(['supplier_id' => $supplier->id]);
        $location = $this->location();
        $purchase = Purchase::create([
            'purchase_number' => $this->code('PUR'),
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-10-18',
            'subtotal' => '70.00',
            'total' => '70.00',
            'created_by_user_id' => $user->id,
        ]);
        $item = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'line_number' => 1,
            'spare_part_id' => $part->id,
            'description' => 'Test ordered part',
            'quantity' => '2.000',
            'unit_cost' => '35.0000',
            'line_total' => '70.00',
        ]);
        $note = GoodsReceivedNote::create([
            'grn_number' => $this->code('GRN'),
            'purchase_id' => $purchase->id,
            'supplier_id' => $supplier->id,
            'received_at' => '2026-10-18 10:00:00',
            'received_by_employee_id' => $this->employee()->id,
        ]);
        $receivedItem = GoodsReceivedNoteItem::create([
            'goods_received_note_id' => $note->id,
            'purchase_id' => $purchase->id,
            'purchase_item_id' => $item->id,
            'spare_part_id' => $part->id,
            'location_id' => $location->id,
            'quantity' => '2.000',
            'unit_cost' => '35.0000',
        ]);

        $this->assertTrue($supplier->purchases->contains($purchase));
        $this->assertTrue($purchase->supplier->is($supplier));
        $this->assertTrue($purchase->purchaseItems->contains($item));
        $this->assertTrue($purchase->goodsReceivedNotes->contains($note));
        $this->assertTrue($note->goodsReceivedNoteItems->contains($receivedItem));
        $this->assertTrue($receivedItem->sparePart->is($part));
        $this->assertSame(0, $part->stockMovements()->count(), 'Draft purchasing must have no implicit stock side effects.');
    }

    public function test_pos_and_repair_usage_reference_the_same_spare_parts_and_movement_history(): void
    {
        $user = User::factory()->create();
        $part = $this->part();
        $location = $this->location();
        $opening = $this->movement($part, $location);
        $usage = JobPartUsage::create([
            'repair_job_id' => $this->job()->id,
            'spare_part_id' => $part->id,
            'location_id' => $location->id,
            'quantity' => '2.000',
            'requested_by_user_id' => $user->id,
            'idempotency_key' => $this->code('USAGE'),
        ]);
        $jobIssue = $this->movement($part, $location, [
            'movement_type' => 'job_issue',
            'quantity_delta' => '-2.000',
            'job_part_usage_id' => $usage->id,
        ]);
        $sale = PosSale::create([
            'sale_number' => $this->code('POS'),
            'sale_at' => '2026-10-18 11:00:00',
            'cashier_employee_id' => $this->employee()->id,
            'subtotal' => '5.50',
            'total' => '5.50',
        ]);
        $saleItem = PosSaleItem::create([
            'pos_sale_id' => $sale->id,
            'line_number' => 1,
            'spare_part_id' => $part->id,
            'location_id' => $location->id,
            'description' => 'Same inventory part',
            'quantity' => '1.000',
            'unit_price' => '5.5000',
            'line_total' => '5.50',
        ]);
        $saleIssue = $this->movement($part, $location, [
            'movement_type' => 'pos_sale',
            'quantity_delta' => '-1.000',
            'pos_sale_item_id' => $saleItem->id,
        ]);

        $this->assertEqualsCanonicalizing([$opening->id, $jobIssue->id, $saleIssue->id], $part->stockMovements->modelKeys());
        $this->assertTrue($usage->sparePart->is($part));
        $this->assertTrue($saleItem->sparePart->is($part));
        $this->assertTrue($jobIssue->sparePart->is($part));
        $this->assertTrue($saleIssue->sparePart->is($part));
    }

    public function test_an_employee_can_link_to_one_existing_login_account(): void
    {
        $user = User::factory()->create();
        $employee = $this->employee(['user_id' => $user->id]);

        $this->assertTrue($employee->user->is($user));
        $this->assertTrue($user->employee->is($employee));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertDatabaseRejects(fn () => $this->employee(['user_id' => $user->id]));
    }

    public function test_money_unit_prices_and_quantities_are_returned_as_decimal_strings(): void
    {
        $customer = $this->customer(['opening_balance' => '0.10']);
        $invoice = $this->invoice($customer, ['subtotal' => '120.05', 'total' => '120.05']);
        $payment = $this->payment($customer, ['amount' => '0.10']);
        $part = $this->part(['selling_price' => '12.3456']);
        $movement = $this->movement($part, null, ['quantity_delta' => '2.125', 'unit_cost' => '10.1234']);

        $this->assertSame('0.10', $customer->fresh()->opening_balance);
        $this->assertSame('120.05', $invoice->fresh()->total);
        $this->assertSame('0.10', $payment->fresh()->amount);
        $this->assertSame('12.3456', $part->fresh()->selling_price);
        $this->assertSame('2.125', $movement->fresh()->quantity_delta);
        $this->assertSame('10.1234', $movement->fresh()->unit_cost);
    }
}
