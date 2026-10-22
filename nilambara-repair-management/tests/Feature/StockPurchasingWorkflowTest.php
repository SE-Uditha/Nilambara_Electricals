<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\StockWorkflowService;
use App\Modules\Invoices\Services\InvoiceBalanceService;
use App\Modules\Invoices\Services\InvoiceService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Purchases\Services\PurchaseWorkflowService;
use App\Modules\Quotations\Services\QuotationApprovalService;
use App\Modules\Quotations\Services\QuotationService;
use App\Modules\RepairJobs\Services\RepairJobService;
use App\Modules\RepairReceipts\Services\RepairReceiptService;
use App\Modules\Reports\Services\ReportDataService;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Services\SupplierPaymentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class StockPurchasingWorkflowTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    private function actorWithoutPermissions(): User
    {
        return User::factory()->create();
    }

    private function actor(string $role = 'administrator'): User
    {
        $this->seed(RoleSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole($role);
        $this->employee(['user_id' => $actor->id, 'is_active' => true]);

        return $actor;
    }

    private function supplier(): Supplier
    {
        return Supplier::create(['supplier_code' => $this->code('SUP'), 'name' => 'Workflow Supplier']);
    }

    private function draft(User $actor, $part): Purchase
    {
        return app(PurchaseWorkflowService::class)->create(['supplier_id' => $this->supplier()->id, 'purchase_date' => now()->toDateString(), 'payment_terms' => 'credit', 'discount_amount' => '1.00', 'other_costs' => '2.00', 'idempotency_key' => (string) Str::uuid(), 'items' => [['spare_part_id' => $part->id, 'quantity' => '5.000', 'unit_cost' => '3.0000']]], $actor);
    }

    public function test_purchase_grn_payment_reversal_and_shared_stock_integrate_without_duplicate_posting(): void
    {
        $actor = $this->actor();
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id, 'selling_price' => '10.0000']);
        $service = app(PurchaseWorkflowService::class);
        $purchase = $this->draft($actor, $part);
        $this->assertSame('16.00', $purchase->total);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('supplier_account_entries', 0);
        $service->finalize($purchase, $actor);
        $service->finalize($purchase, $actor);
        $this->assertDatabaseCount('supplier_account_entries', 1);
        $data = ['purchase_item_id' => $purchase->items()->sole()->id, 'location_id' => $location->id, 'quantity' => '2.000', 'idempotency_key' => (string) Str::uuid()];
        $service->receive($purchase, $data, $actor);
        $service->receive($purchase, $data, $actor);
        $this->assertDatabaseCount('goods_received_notes', 1);
        $this->assertSame('2.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
        $service->receive($purchase, array_replace($data, ['quantity' => '3.000', 'idempotency_key' => (string) Str::uuid()]), $actor);
        $this->assertSame('5.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
        $payments = app(SupplierPaymentService::class);
        $paymentData = ['amount' => '6.00', 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()];
        $payment = $payments->pay($purchase, $paymentData, $actor);
        $payments->pay($purchase, $paymentData, $actor);
        $this->assertDatabaseCount('supplier_payments', 1);
        $this->assertSame('10.00', $payments->outstanding($purchase));
        $reverseData = ['reason' => 'Incorrect supplier tender recorded', 'idempotency_key' => (string) Str::uuid()];
        $payments->reverse($payment, $reverseData, $actor);
        $payments->reverse($payment, $reverseData, $actor);
        $this->assertSame('16.00', $payments->outstanding($purchase));
        $this->assertDatabaseCount('supplier_payment_reversals', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('customer_account_entries', 0);
        $this->actingAs($actor)->get(route('purchases.show', $purchase))->assertOk();
        $this->get(route('suppliers.show', $purchase->supplier_id))->assertOk();
    }

    public function test_repair_request_does_not_deduct_stock_and_repeated_issue_deducts_once(): void
    {
        $actor = $this->actor();
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id, 'selling_price' => '12.5000']);
        $this->movement($part, $location, ['quantity_delta' => '5.000', 'unit_cost' => '3.0000']);
        $job = $this->job(null, ['labour_charge' => '10.00', 'total_job_amount' => '10.00']);
        $service = app(StockWorkflowService::class);
        $data = ['repair_job_id' => $job->id, 'spare_part_id' => $part->id, 'location_id' => $location->id, 'quantity' => '2.000', 'notes' => 'Motor parts', 'idempotency_key' => (string) Str::uuid()];
        $usage = $service->request($data, $actor);
        $service->request($data, $actor);
        $this->assertDatabaseCount('job_part_usages', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $service->issue($usage, $actor);
        $service->issue($usage, $actor);
        $this->assertSame('3.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
        $this->assertSame('35.00', $job->fresh()->total_job_amount);
        $this->assertSame('3.0000', $usage->fresh()->unit_cost);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->actingAs($actor)->get(route('stock-issues.index'))->assertOk();
        $this->get(route('inventory.show', $part))->assertOk();
    }

    public function test_stock_correction_damage_count_and_replay_preserve_history(): void
    {
        $actor = $this->actor();
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id]);
        $this->movement($part, $location, ['quantity_delta' => '5.000']);
        $service = app(StockWorkflowService::class);
        $data = ['spare_part_id' => $part->id, 'location_id' => $location->id, 'quantity' => '-1.000', 'reason' => 'Damaged during handling', 'adjustment_type' => 'damaged', 'idempotency_key' => (string) Str::uuid()];
        $service->adjust($data, $actor);
        $service->adjust($data, $actor);
        $this->assertSame('4.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
        $count = array_replace($data, ['quantity' => '3.000', 'idempotency_key' => (string) Str::uuid(), 'reason' => 'Physical shelf count']);
        $service->count($count, $actor);
        $service->count($count, $actor);
        $this->assertSame('3.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
        $this->assertDatabaseCount('stock_counts', 1);
        $this->assertDatabaseCount('stock_movements', 3);
        $this->expectException(ValidationException::class);
        $service->adjust(array_replace($data, ['quantity' => '-10.000', 'idempotency_key' => (string) Str::uuid()]), $actor);
    }

    public function test_cashier_stock_pages_render_but_technician_cannot_change_stock_or_suppliers(): void
    {
        $actor = $this->actor('cashier');
        $this->actingAs($actor);
        foreach (['inventory.index', 'inventory.create', 'suppliers.index', 'suppliers.create', 'purchases.index', 'purchases.create', 'stock-issues.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $cashier = $this->actorWithoutPermissions();
        $this->actingAs($cashier)->get(route('inventory.create'))->assertForbidden();
        $this->post(route('inventory.store'), [])->assertForbidden();
        $this->post(route('suppliers.store'), [])->assertForbidden();
        $this->post(route('purchases.store'), [])->assertForbidden();
    }

    public function test_http_master_registration_validates_precision_and_records_opening_balance(): void
    {
        $actor = $this->actor();
        $location = $this->location();
        $part = $this->part();
        $this->actingAs($actor)->post(route('inventory.store'), ['sku' => 'NEW-TEST', 'name' => 'New part', 'unit_id' => $part->unit_id, 'default_location_id' => $location->id, 'selling_price' => '12.5000', 'minimum_stock_level' => '1', 'reorder_level' => '2', 'is_active' => '1', 'opening_quantity' => '5'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'opening', 'quantity_delta' => '5.000']);
        $this->post(route('suppliers.store'), ['name' => 'New supplier', 'address' => 'Colombo', 'mobile_number' => '0771234567', 'opening_balance' => '12.50', 'credit_days' => 30, 'is_active' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('supplier_account_entries', ['entry_type' => 'opening', 'amount' => '12.50']);
        $supplier = Supplier::sole();
        $this->post(route('inventory.store'), ['sku' => 'COSTED-OPENING', 'name' => 'Costed opening part', 'unit_id' => $part->unit_id, 'default_location_id' => $location->id, 'supplier_id' => $supplier->id, 'selling_price' => '5.0000', 'minimum_stock_level' => '0', 'reorder_level' => '1', 'is_active' => 1, 'opening_quantity' => '2.000', 'opening_unit_cost' => '1.2500'])->assertSessionHasNoErrors();
        $created = SparePart::where('sku', 'COSTED-OPENING')->sole();
        $this->assertSame($supplier->id, $created->supplier_id);
        $this->assertSame('1.2500', app(InventoryService::class)->latestUnitCost($created->id, $location->id));
    }

    public function test_complete_unused_repair_issue_can_be_returned_once_without_deleting_history(): void
    {
        $actor = $this->actor();
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id, 'selling_price' => '5.0000']);
        $this->movement($part, $location, ['quantity_delta' => '3.000']);
        $job = $this->job();
        $service = app(StockWorkflowService::class);
        $usage = $service->request(['repair_job_id' => $job->id, 'spare_part_id' => $part->id, 'location_id' => $location->id, 'quantity' => '2.000', 'idempotency_key' => (string) Str::uuid()], $actor);
        $service->issue($usage, $actor);
        $service->cancelUsage($usage, 'All unused parts physically returned', $actor);
        $service->cancelUsage($usage, 'All unused parts physically returned', $actor);
        $this->assertSame('3.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
        $this->assertSame('0.00', $job->fresh()->parts_charge);
        $this->assertSame('cancelled', $usage->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertDatabaseCount('stock_returns', 1);
    }

    public function test_receiving_more_than_ordered_is_rejected_atomically(): void
    {
        $actor = $this->actor();
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id]);
        $purchase = $this->draft($actor, $part);
        $service = app(PurchaseWorkflowService::class);
        $service->finalize($purchase, $actor);
        try {
            $service->receive($purchase, ['purchase_item_id' => $purchase->items()->sole()->id, 'location_id' => $location->id, 'quantity' => '6.000', 'idempotency_key' => (string) Str::uuid()], $actor);
            $this->fail('Over-receiving must fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('goods_received_notes', 0);
            $this->assertDatabaseCount('stock_movements', 0);
        }
    }

    public function test_cashier_can_receive_and_pay_supplier_money(): void
    {
        $admin = $this->actor();
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id]);
        $purchase = $this->draft($admin, $part);
        app(PurchaseWorkflowService::class)->finalize($purchase, $admin);
        $storekeeper = $this->actor('cashier');
        $this->actingAs($storekeeper)->post(route('purchases.receive', $purchase), ['purchase_item_id' => $purchase->items()->sole()->id, 'location_id' => $location->id, 'quantity' => '1', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->post(route('purchases.pay', $purchase), ['amount' => '1.00', 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('supplier_payments', 1);
    }

    public function test_supplier_edit_and_search_preserve_the_opening_ledger(): void
    {
        $actor = $this->actor();
        $data = ['name' => 'Editable Supplier', 'address' => 'Colombo', 'mobile_number' => '0771234567', 'opening_balance' => '12.50', 'credit_days' => 30, 'is_active' => 1];
        $this->actingAs($actor)->post(route('suppliers.store'), $data)->assertSessionHasNoErrors();
        $supplier = Supplier::sole();
        unset($data['opening_balance']);
        $data['name'] = 'Updated Supplier';
        $this->put(route('suppliers.update', $supplier), $data)->assertSessionHasNoErrors();
        $this->get(route('suppliers.index', ['q' => 'Updated Supplier']))->assertOk()->assertViewHas('suppliers', fn ($rows) => $rows->total() === 1);
        $this->put(route('suppliers.update', $supplier), $data + ['opening_balance' => '100.00'])->assertSessionHasErrors('opening_balance');
        $this->assertSame('12.50', $supplier->fresh()->opening_balance);
        $this->assertDatabaseCount('supplier_account_entries', 1);
    }

    public function test_unquoted_labour_charge_is_authorized_audited_and_used_for_billing(): void
    {
        $actor = $this->actor();
        $job = $this->job();
        $this->actingAs($actor)->post(route('repair-jobs.labour-charge', $job), ['labour_charge' => '125.50'])->assertSessionHasNoErrors();
        $this->assertSame('125.50', $job->fresh()->total_job_amount);
        $this->assertDatabaseHas('audit_logs', ['action' => 'repair_job.charge.updated', 'user_id' => $actor->id]);
        $technician = $this->actorWithoutPermissions();
        $this->actingAs($technician)->post(route('repair-jobs.labour-charge', $job), ['labour_charge' => '1.00'])->assertForbidden();
        $this->assertSame('125.50', $job->fresh()->total_job_amount);
    }

    public function test_real_intake_approval_stock_invoice_payment_and_delivery_workflow_needs_no_manual_job_pricing(): void
    {
        $actor = $this->actor();
        $customer = $this->customer();
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id, 'selling_price' => '10.0000']);
        $this->movement($part, $location, ['quantity_delta' => '5.000', 'unit_cost' => '3.0000']);
        $receipt = app(RepairReceiptService::class)->register(['customer_id' => $customer->id, 'job_number' => $this->code('INTAKE'), 'item_name' => 'Motor', 'brand' => 'Test', 'job_type' => 'normal', 'received_by_employee_id' => $actor->employee->id, 'labor_cost' => '2.00', 'advance_payment' => '5.00', 'payment_method' => 'cash'], $actor);
        $job = $receipt->repairJob;
        $quote = app(QuotationService::class)->create(['repair_job_id' => $job->id, 'quotation_date' => now()->toDateString(), 'valid_until' => now()->addDays(7)->toDateString(), 'proposed_repair_description' => 'Replace bearings', 'labour_charge' => '10.00', 'items' => [['spare_part_id' => $part->id, 'description' => 'Bearings', 'quantity' => '2.000', 'unit_price' => '10.0000']], 'intent' => 'issue'], $actor);
        app(QuotationApprovalService::class)->recordDecision($quote, ['decision' => 'approved', 'customer_contact_name' => 'Test customer', 'approval_method' => 'phone'], $actor);
        $this->assertSame('30.00', $job->fresh()->total_job_amount);
        $this->assertDatabaseCount('stock_movements', 1);
        $stock = app(StockWorkflowService::class);
        $usage = $stock->request(['repair_job_id' => $job->id, 'spare_part_id' => $part->id, 'location_id' => $location->id, 'quantity' => '2.000', 'idempotency_key' => (string) Str::uuid()], $actor);
        $stock->issue($usage, $actor);
        $this->assertSame('30.00', $job->fresh()->total_job_amount);
        $jobs = app(RepairJobService::class);
        $jobs->updateStatus($job, ['status' => 'Repair Completed'], $actor);
        $this->assertNotNull($job->fresh()->completed_at);
        $invoice = app(InvoiceService::class)->create(['customer_id' => $customer->id, 'repair_job_ids' => [$job->id], 'invoice_date' => now()->toDateString(), 'payment_terms' => 'cash', 'payment_method' => 'cash', 'intent' => 'issue'], $actor);
        $balances = app(InvoiceBalanceService::class);
        $this->assertSame('25.00', $balances->summary($invoice)['outstanding']);
        app(PaymentService::class)->receiveInvoicePayment(['invoice_id' => $invoice->id, 'amount' => '25.00', 'method' => 'cash', 'payment_date' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid()], $actor);
        $this->assertSame('0.00', $balances->summary($invoice)['outstanding']);
        $jobs->updateStatus($job, ['status' => 'Delivered'], $actor);
        $this->assertNotNull($job->fresh()->delivered_at);
        $this->assertSame('3.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
        $profit = app(ReportDataService::class)->build('profit-estimate', [], $actor);
        $this->assertSame('22.00', $profit['summary']['estimated_profit']);
    }

    public function test_supplier_opening_balance_payment_and_reversal_use_existing_supplier_ledger(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor)->post(route('suppliers.store'), ['name' => 'Opening Supplier', 'address' => 'Colombo', 'mobile_number' => '0771234567', 'opening_balance' => '10.50', 'credit_days' => 0, 'is_active' => 1])->assertSessionHasNoErrors();
        $supplier = Supplier::sole();
        $service = app(SupplierPaymentService::class);
        $data = ['amount' => '3.50', 'method' => 'cash', 'idempotency_key' => (string) Str::uuid()];
        $payment = $service->payOpening($supplier, $data, $actor);
        $service->payOpening($supplier, $data, $actor);
        $this->assertSame('7.00', $service->openingOutstanding($supplier));
        $service->reverse($payment, ['reason' => 'Wrong opening tender entry', 'idempotency_key' => (string) Str::uuid()], $actor);
        $this->assertSame('10.50', $service->openingOutstanding($supplier));
        $this->assertDatabaseCount('supplier_payments', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->get(route('suppliers.show', $supplier))->assertOk();
    }

    public function test_supplier_return_reduces_stock_and_only_agreed_credit_reduces_payable(): void
    {
        $actor = $this->actor();
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id]);
        $purchase = $this->draft($actor, $part);
        $service = app(PurchaseWorkflowService::class);
        $service->finalize($purchase, $actor);
        $line = $purchase->items()->sole();
        $service->receive($purchase, ['purchase_item_id' => $line->id, 'location_id' => $location->id, 'quantity' => '5.000', 'idempotency_key' => (string) Str::uuid()], $actor);
        $data = ['purchase_item_id' => $line->id, 'location_id' => $location->id, 'quantity' => '2.000', 'credit_amount' => '5.00', 'reason' => 'Supplier accepted two returned parts', 'idempotency_key' => (string) Str::uuid()];
        $service->returnStock($purchase, $data, $actor);
        $service->returnStock($purchase, $data, $actor);
        $this->assertSame('3.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
        $this->assertSame('11.00', app(SupplierPaymentService::class)->outstanding($purchase));
        $this->assertDatabaseCount('stock_returns', 1);
        $this->assertDatabaseHas('supplier_account_entries', ['entry_type' => 'adjustment', 'amount' => '-5.00']);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->actingAs($actor)->get(route('purchases.show', $purchase))->assertOk();
    }

    public function test_receipt_and_card_pdf_routes_render_local_sinhala_and_record_exports(): void
    {
        $actor = $this->actor();
        $customer = $this->customer(['customer_name' => 'සිංහල Customer']);
        $receipt = app(RepairReceiptService::class)->register(['customer_id' => $customer->id, 'job_number' => $this->code('MAN'), 'item_name' => 'Motor', 'brand' => 'Test', 'job_type' => 'normal', 'received_by_employee_id' => $actor->employee->id, 'labor_cost' => '0.00', 'advance_payment' => '0.00'], $actor);
        $this->actingAs($actor);
        foreach (['repair-receipts.pdf' => [$receipt, 'pending-receipt.pdf'], 'job-cards.pdf' => [$receipt->repairJob, 'pending-card.pdf']] as $route => [$model, $file]) {
            $response = $this->get(route($route, $model))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
            file_put_contents(storage_path('app/testing/'.$file), $response->getContent());
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'repair_receipt.pdf_exported', 'user_id' => $actor->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'job_card.pdf_exported', 'user_id' => $actor->id]);
    }
}
