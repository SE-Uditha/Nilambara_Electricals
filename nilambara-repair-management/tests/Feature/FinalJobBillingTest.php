<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Services\InvoiceCalculationService;
use App\Modules\RepairJobs\Services\RepairJobService;
use App\Modules\RepairReceipts\Services\RepairReceiptService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class FinalJobBillingTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    private function actor(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->employee(['user_id' => $user->id]);
        $this->actingAs($user);

        return $user;
    }

    private function payload(): array
    {
        return ['labour_charge' => '100.00', 'idempotency_key' => (string) Str::uuid(), 'charges' => [['description' => 'Rotor balancing', 'quantity' => '2', 'unit_price' => '15.1250']]];
    }

    public function test_intake_receipt_never_displays_repair_prices_and_omits_zero_payment(): void
    {
        $actor = $this->actor();
        $customer = $this->customer();
        $receipt = app(RepairReceiptService::class)->register(['customer_id' => $customer->id, 'job_number' => $this->code('MAN'), 'item_name' => 'Motor', 'brand' => 'Test', 'job_type' => 'normal', 'received_by_employee_id' => $actor->employee->id, 'labor_cost' => '9876.54'], $actor);
        $this->assertSame('0.00', $receipt->repairJob->total_job_amount);
        $this->get(route('repair-jobs.receipt.print', $receipt->repairJob))->assertOk()->assertDontSee('9876.54')->assertDontSee('0.00')->assertDontSee(__('ui.labor_cost'));
        $this->getJson(route('repair-jobs.billing', $receipt->repairJob))->assertUnprocessable();
        if (! is_dir(base_path('tmp/pdfs'))) {
            mkdir(base_path('tmp/pdfs'), 0700, true);
        }
        $pdf = $this->get(route('repair-jobs.receipt.pdf', $receipt->repairJob))->assertOk();
        file_put_contents(base_path('tmp/pdfs/intake-no-total-preview.pdf'), $pdf->getContent());
    }

    public function test_custom_charges_and_inventory_parts_flow_into_a_separate_invoice_without_double_issuing(): void
    {
        $actor = $this->actor();
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id, 'selling_price' => '12.5000']);
        $this->movement($part, $location, ['quantity_delta' => '5.000', 'unit_cost' => '4.0000']);
        $job = $this->job(null, ['status' => JobStatus::RepairCompleted->value]);
        $page = $this->get(route('repair-jobs.billing', $job))->assertOk()->assertSee('Add Spare Part')->assertSee('Add Custom Charge');
        if (! is_dir(base_path('tmp/pdfs'))) {
            mkdir(base_path('tmp/pdfs'), 0700, true);
        }
        file_put_contents(base_path('tmp/pdfs/final-billing-preview.html'), $page->getContent());
        $data = $this->payload();
        $data['parts'] = [['spare_part_id' => $part->id, 'location_id' => $location->id, 'quantity' => '2']];
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('repair-jobs.billing.update', $job), $data)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame('155.25', $job->fresh()->total_job_amount);
        $this->assertSame('3.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
        $this->assertDatabaseCount('job_part_usages', 1);
        $this->assertDatabaseCount('repair_job_charges', 1);
        $this->get(route('invoices.create', ['customer_id' => $job->customer_id, 'repair_job_ids' => [$job->id]]))->assertOk()->assertSee($job->customer->display_name);
        $this->post(route('invoices.store'), ['customer_id' => $job->customer_id, 'repair_job_ids' => [$job->id], 'invoice_date' => now()->toDateString(), 'payment_terms' => 'cash', 'payment_method' => 'cash', 'intent' => 'issue'])->assertRedirect()->assertSessionHasNoErrors();
        $invoice = Invoice::sole();
        $this->assertSame('155.25', $invoice->total);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id, 'description' => 'Rotor balancing', 'line_total' => '30.25']);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id, 'spare_part_id' => $part->id, 'line_total' => '25.00']);
        $this->get(route('invoices.print', $invoice))->assertOk()->assertSee('Rotor balancing')->assertSee('155.25');
        $pdf = $this->get(route('invoices.pdf', $invoice))->assertOk();
        file_put_contents(base_path('tmp/pdfs/final-invoice-preview.pdf'), $pdf->getContent());
        $this->postJson(route('repair-jobs.billing.update', $job), $data)->assertUnprocessable();
        $this->assertSame('3.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
    }

    public function test_insufficient_stock_rolls_back_custom_charges_prices_and_stock_requests(): void
    {
        $this->actor();
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id]);
        $job = $this->job(null, ['status' => JobStatus::RepairCompleted->value]);
        $data = $this->payload();
        $data['parts'] = [['spare_part_id' => $part->id, 'location_id' => $location->id, 'quantity' => '2']];
        $this->postJson(route('repair-jobs.billing.update', $job), $data)->assertUnprocessable();
        $this->assertSame('0.00', $job->fresh()->total_job_amount);
        $this->assertDatabaseCount('repair_job_charges', 0);
        $this->assertDatabaseCount('job_part_usages', 0);
    }

    public function test_custom_line_removal_keeps_history_and_recalculates_the_invoice_breakdown(): void
    {
        $actor = $this->actor();
        $job = $this->job(null, ['status' => JobStatus::RepairCompleted->value]);
        $data = $this->payload();
        $this->post(route('repair-jobs.billing.update', $job), $data)->assertSessionHasNoErrors();
        app(RepairJobService::class)->setLabourCharge($job, '50.00', $actor);
        $this->assertSame('80.25', $job->fresh()->total_job_amount);
        $data['labour_charge'] = '50.00';
        $data['charges'] = [];
        $this->post(route('repair-jobs.billing.update', $job), $data)->assertSessionHasNoErrors();
        $this->assertSame('50.00', $job->fresh()->total_job_amount);
        $this->assertDatabaseCount('repair_job_charges', 1);
        $this->assertNotNull($job->billingCharges()->sole()->removed_at);
        $billing = app(InvoiceCalculationService::class)->billableLines($job->fresh());
        $this->assertCount(1, $billing['lines']);
        $this->assertSame('50.00', $billing['amount']);
    }

    public function test_customer_quotation_prices_cannot_be_overridden_by_custom_bill_entries(): void
    {
        $this->actor();
        $job = $this->job(null, ['status' => JobStatus::RepairCompleted->value]);
        $this->quotation($job, ['preparation_status' => 'issued', 'approval_status' => 'approved']);
        $this->postJson(route('repair-jobs.billing.update', $job), $this->payload())->assertUnprocessable()->assertJsonValidationErrors('billing');
        $this->assertDatabaseCount('repair_job_charges', 0);
        $this->assertSame('0.00', $job->fresh()->total_job_amount);
    }

    public function test_invalid_custom_values_and_unauthorized_users_are_rejected(): void
    {
        $this->actor();
        $job = $this->job(null, ['status' => JobStatus::RepairCompleted->value]);
        $data = $this->payload();
        $data['charges'][0]['unit_price'] = '-1';
        $this->postJson(route('repair-jobs.billing.update', $job), $data)->assertUnprocessable();
        $this->assertDatabaseCount('repair_job_charges', 0);
        $this->actingAs(User::factory()->create())->get(route('repair-jobs.billing', $job))->assertForbidden();
    }
}
