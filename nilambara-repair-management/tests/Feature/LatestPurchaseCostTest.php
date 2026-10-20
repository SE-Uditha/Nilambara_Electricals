<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Payments\Models\PaymentReversal;
use App\Modules\POS\Services\POSCheckoutService;
use App\Modules\POS\Services\POSReturnService;
use App\Modules\Purchases\Services\PurchaseWorkflowService;
use App\Modules\Reports\Services\ReportDataService;
use App\Modules\Suppliers\Models\Supplier;
use Brick\Math\BigDecimal;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class LatestPurchaseCostTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    public function test_correcting_a_payment_changes_collections_without_reducing_the_issued_sale(): void
    {
        $this->seed(RoleSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('administrator');
        $customer = $this->customer();
        $this->invoice($customer, ['subtotal' => '100.00', 'total' => '100.00', 'status' => 'finalized', 'finalized_at' => now()]);
        $payment = $this->payment($customer, ['amount' => '30.00', 'paid_at' => now()]);
        PaymentReversal::create(['payment_id' => $payment->id, 'customer_id' => $customer->id, 'reversal_type' => 'reversal', 'amount' => '10.00', 'method' => 'cash', 'reversed_at' => now(), 'reversed_by_user_id' => $actor->id, 'reason' => 'Correct an incorrectly entered tender', 'idempotency_key' => (string) Str::uuid(), 'customer_scope_id' => $customer->id]);
        $reports = app(ReportDataService::class);
        $sales = $reports->build('daily-sales', [], $actor)['query']->sole();
        $this->assertSame('100.00', (string) BigDecimal::of((string) $sales->net_sales)->toScale(2));
        $collections = $reports->build('collections', [], $actor);
        $this->assertSame('20.00', (string) BigDecimal::of((string) $collections['query']->sum('net_amount'))->toScale(2));
    }

    public function test_old_customer_return_does_not_replace_latest_purchase_cost_for_pos_or_valuation(): void
    {
        $this->seed(RoleSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('administrator');
        $this->employee(['user_id' => $actor->id, 'is_active' => true]);
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id, 'selling_price' => '10.0000']);
        $supplier = Supplier::create(['supplier_code' => $this->code('SUP'), 'name' => 'Costing supplier']);
        $purchases = app(PurchaseWorkflowService::class);
        $receive = function (string $cost) use ($actor, $part, $supplier, $location, $purchases): void {
            $purchase = $purchases->create(['supplier_id' => $supplier->id, 'purchase_date' => now()->toDateString(), 'payment_terms' => 'credit', 'discount_amount' => '0.00', 'other_costs' => '0.00', 'idempotency_key' => (string) Str::uuid(), 'items' => [['spare_part_id' => $part->id, 'quantity' => '5.000', 'unit_cost' => $cost]]], $actor);
            $purchases->finalize($purchase, $actor);
            $purchases->receive($purchase, ['purchase_item_id' => $purchase->items()->sole()->id, 'location_id' => $location->id, 'quantity' => '5.000', 'idempotency_key' => (string) Str::uuid()], $actor);
        };
        $checkout = fn () => app(POSCheckoutService::class)->checkout(['payment_method' => 'cash', 'amount_tendered' => '10.00', 'discount_amount' => '0.00', 'idempotency_key' => (string) Str::uuid()], [$part->id => '1.000'], $actor);
        $receive('3.0000');
        $oldSale = $checkout();
        $this->assertSame('3.0000', $oldSale->items()->sole()->unit_cost);
        $receive('7.0000');
        app(POSReturnService::class)->record(['pos_sale_item_id' => $oldSale->items()->sole()->id, 'quantity' => '1.000', 'condition' => 'resalable', 'reason' => 'Return from earlier purchase batch', 'refund_amount' => '10.00', 'refund_method' => 'cash', 'idempotency_key' => (string) Str::uuid()], $actor);
        $this->assertSame('7.0000', app(InventoryService::class)->latestUnitCost($part->id, $location->id));
        $newSale = $checkout();
        $this->assertSame('7.0000', $newSale->items()->sole()->unit_cost);
        $report = app(ReportDataService::class)->build('valuation', [], $actor);
        $row = $report['query']->sole();
        $this->assertSame('7.0000', (string) BigDecimal::of((string) $row->unit_cost)->toScale(4));
        $this->assertSame('63.00', (string) BigDecimal::of((string) $row->stock_value)->toScale(2));
        $this->assertSame('63.00', $report['summary']['known_stock_value']);
        $profit = app(ReportDataService::class)->build('profit-estimate', [], $actor);
        $this->assertSame('3.00', $profit['summary']['estimated_profit']);
        $this->actingAs($actor)->get(route('reports.show', 'valuation'))->assertOk();
    }
}
