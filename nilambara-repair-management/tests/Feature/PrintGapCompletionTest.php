<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PrintTemplates\Models\PrintTemplate;
use App\Modules\Purchases\Services\PurchaseWorkflowService;
use App\Modules\Suppliers\Models\Supplier;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class PrintGapCompletionTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    private function administrator(): User
    {
        $this->seed(RoleSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('administrator');
        $this->employee(['user_id' => $actor->id, 'is_active' => true]);

        return $actor;
    }

    public function test_customer_statement_uses_template_title_paper_orientation_sinhala_font_and_print_history(): void
    {
        $actor = $this->administrator();
        $customer = $this->customer(['customer_name' => 'සිංහල Customer']);
        PrintTemplate::create(['code' => 'STMT-CUSTOM', 'version' => 1, 'document_type' => 'customer_statement', 'name' => 'Statement custom', 'document_title' => 'Custom account statement', 'paper_size' => 'A5', 'orientation' => 'landscape', 'font_size_pt' => '9', 'is_active' => true]);
        $this->actingAs($actor)->get(route('customers.statement.print', $customer))->assertOk()->assertSee('Custom account statement')->assertSee('size: A5 landscape', false);
        $pdf = $this->get(route('customers.statement.pdf', ['customer' => $customer, 'duplicate' => 1]))->assertOk()->getContent();
        $this->assertStringContainsString('595.28', $pdf);
        $this->assertStringContainsString('NotoSansSinhala', $pdf);
        $this->assertDatabaseHas('document_print_histories', ['customer_id' => $customer->id, 'is_duplicate' => true]);
        file_put_contents(storage_path('app/testing/pending-statement.pdf'), $pdf);
    }

    public function test_purchase_and_grn_print_pdf_and_duplicate_output_preserve_their_source(): void
    {
        $actor = $this->administrator();
        $supplier = Supplier::create(['supplier_code' => $this->code('SUP'), 'name' => 'සිංහල Supplier']);
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id]);
        $service = app(PurchaseWorkflowService::class);
        $purchase = $service->create(['supplier_id' => $supplier->id, 'purchase_date' => now()->toDateString(), 'payment_terms' => 'credit', 'discount_amount' => '0.00', 'other_costs' => '0.00', 'idempotency_key' => (string) Str::uuid(), 'items' => [['spare_part_id' => $part->id, 'quantity' => '2.000', 'unit_cost' => '3.0000']]], $actor);
        $service->finalize($purchase, $actor);
        $grn = $service->receive($purchase, ['purchase_item_id' => $purchase->items()->sole()->id, 'location_id' => $location->id, 'quantity' => '1.000', 'idempotency_key' => (string) Str::uuid()], $actor);
        $this->actingAs($actor)->get(route('purchases.document', ['purchase' => $purchase, 'format' => 'html', 'duplicate' => 1]))->assertOk()->assertSee('DUPLICATE COPY')->assertSee($purchase->purchase_number);
        foreach (['purchases.document' => ['purchase' => $purchase], 'grns.document' => ['grn' => $grn]] as $route => $parameters) {
            $pdf = $this->get(route($route, $parameters + ['format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
            $this->assertStringStartsWith('%PDF-', $pdf);
            file_put_contents(storage_path('app/testing/'.($route === 'purchases.document' ? 'pending-purchase.pdf' : 'pending-grn.pdf')), $pdf);
        }
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('document_print_histories', 3);
        $this->assertDatabaseHas('document_print_histories', ['purchase_id' => $purchase->id, 'is_duplicate' => true]);
    }
}
