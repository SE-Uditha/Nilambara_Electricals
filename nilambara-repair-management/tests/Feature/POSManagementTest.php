<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Models\User;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReturn;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Payments\Models\Payment;
use App\Modules\POS\Models\PosSale;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class POSManagementTest extends TestCase
{
    use CreatesDatabaseRecords;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_cashier_scans_repeated_barcodes_and_completes_sale_with_shared_stock_and_payment(): void
    {
        $cashier = $this->userWithRole('cashier');
        [$part, $location] = $this->saleablePart('BAR-100', '12.0000', '5.000');

        $this->actingAs($cashier)->get(route('pos.index', ['q' => 'BAR-100']))
            ->assertOk()->assertSee($part->sku)->assertSee('5.000');
        $this->actingAs($cashier)->post(route('pos.cart.add'), ['barcode' => 'BAR-100', 'quantity' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($cashier)->post(route('pos.cart.add'), ['barcode' => 'BAR-100', 'quantity' => '2'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('3.000', session('pos_cart')[$part->getKey()]);

        $this->actingAs($cashier)->post(route('pos.checkout'), [
            'payment_method' => 'cash',
            'amount_tendered' => '50.00',
            'discount_amount' => '0.00',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $sale = PosSale::query()->sole();
        $this->assertSame('POS-00001', $sale->sale_number);
        $this->assertSame(DocumentStatus::Finalized, $sale->status);
        $this->assertSame('36.00', $sale->total);
        $this->assertSame('14.00', $sale->change_due);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['payment_type' => 'pos', 'amount' => '36.00']);
        $this->assertDatabaseHas('payment_allocations', [
            'target_type' => 'pos_sale',
            'pos_sale_id' => $sale->getKey(),
            'amount' => '36.00',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'pos_sale_item_id' => $sale->items()->value('id'),
            'movement_type' => 'pos_sale',
            'quantity_delta' => '-3.000',
        ]);
        $this->assertSame('2.000', app(InventoryService::class)
            ->availableAtDefaultLocation($part->fresh())->__toString());
        $this->assertSame(1, StockMovement::query()->where('movement_type', 'pos_sale')->count());
        $this->actingAs($cashier)->get(route('pos.sales.show', $sale))->assertOk()->assertSee($sale->sale_number);
        $this->actingAs($cashier)->get(route('pos.sales.print', $sale))->assertOk()->assertSee($sale->sale_number);
        $this->actingAs($cashier)->get(route('pos.sales.reprint', $sale))->assertOk()->assertSee(__('pos.duplicate_copy'));
    }

    public function test_unverified_bank_and_cheque_sales_do_not_post_payment_or_reduce_stock_until_authorized(): void
    {
        $cashier = $this->userWithRole('cashier');
        $manager = $this->userWithRole('manager');
        [$part] = $this->saleablePart('BANK-100', '20.0000', '4.000');
        $this->actingAs($cashier)->post(route('pos.cart.add'), ['part_id' => $part->getKey(), 'quantity' => '1'])
            ->assertRedirect();

        $this->actingAs($cashier)->post(route('pos.checkout'), [
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'TRF-VERIFY-1',
            'discount_amount' => '0.00',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();

        $sale = PosSale::query()->sole();
        $this->assertSame(DocumentStatus::Draft, $sale->status);
        $this->assertSame('pending', $sale->payment_status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('stock_movements', 1);

        $this->actingAs($manager)->post(route('pos.sales.verify', $sale))->assertRedirect();
        $this->assertSame(DocumentStatus::Finalized, $sale->fresh()->status);
        $this->assertSame('settled', $sale->fresh()->payment_status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_checkout_revalidates_stock_and_idempotency_prevents_duplicate_cash_postings(): void
    {
        $cashier = $this->userWithRole('cashier');
        [$part] = $this->saleablePart('LOW-100', '10.0000', '2.000');
        $token = (string) Str::uuid();
        $this->actingAs($cashier)->withSession(['pos_cart' => [$part->getKey() => '3.000']])
            ->post(route('pos.checkout'), [
                'payment_method' => 'cash',
                'amount_tendered' => '30.00',
                'discount_amount' => '0.00',
                'idempotency_key' => $token,
            ])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('pos_sales', 0);
        $this->assertDatabaseCount('payments', 0);

        $this->actingAs($cashier)->withSession(['pos_cart' => [$part->getKey() => '1.000']])
            ->post(route('pos.checkout'), [
                'payment_method' => 'cash',
                'amount_tendered' => '10.00',
                'discount_amount' => '0.00',
                'idempotency_key' => $token,
            ])->assertRedirect();
        $this->actingAs($cashier)->post(route('pos.checkout'), [
            'payment_method' => 'cash',
            'amount_tendered' => '10.00',
            'discount_amount' => '0.00',
            'idempotency_key' => $token,
        ])->assertRedirect();
        $this->actingAs($cashier)->post(route('pos.checkout'), [
            'payment_method' => 'cash',
            'amount_tendered' => '11.00',
            'discount_amount' => '0.00',
            'idempotency_key' => $token,
        ])->assertSessionHasErrors('idempotency_key');
        $this->assertDatabaseCount('pos_sales', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_pos_payments_appear_in_payment_history_and_show_their_sale_reference(): void
    {
        $cashier = $this->userWithRole('cashier');
        $manager = $this->userWithRole('manager');
        [$part] = $this->saleablePart('PAY-100', '12.0000', '3.000');
        $this->actingAs($cashier)->withSession(['pos_cart' => [$part->getKey() => '1.000']])
            ->post(route('pos.checkout'), [
                'payment_method' => 'cash',
                'amount_tendered' => '12.00',
                'discount_amount' => '0.00',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();
        $sale = PosSale::query()->sole();
        $payment = Payment::query()->sole();

        $this->actingAs($manager)->get(route('payments.index', ['q' => $sale->sale_number]))
            ->assertOk()->assertSee($payment->payment_number)->assertSee($sale->sale_number)->assertSee(__('pos.walk_in'));
        $this->actingAs($manager)->get(route('payments.show', $payment))
            ->assertOk()->assertSee($sale->sale_number);
        $this->actingAs($manager)->get(route('payments.print', $payment))
            ->assertOk()->assertSee($sale->sale_number);
    }

    public function test_cashier_cannot_apply_discount_or_view_pos_without_permission(): void
    {
        $cashier = $this->userWithRole('cashier');
        [$part] = $this->saleablePart('DISC-100', '10.0000', '3.000');
        $this->actingAs($cashier)->withSession(['pos_cart' => [$part->getKey() => '1.000']])
            ->post(route('pos.checkout'), [
                'payment_method' => 'cash',
                'amount_tendered' => '10.00',
                'discount_amount' => '1.00',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertForbidden();
        $this->assertDatabaseCount('pos_sales', 0);
    }

    public function test_returns_preserve_original_sale_and_restore_only_resalable_stock_with_authorized_refunds(): void
    {
        $cashier = $this->userWithRole('cashier');
        $manager = $this->userWithRole('manager');
        [$part] = $this->saleablePart('RET-100', '15.0000', '5.000');
        $this->actingAs($cashier)->withSession(['pos_cart' => [$part->getKey() => '3.000']])
            ->post(route('pos.checkout'), [
                'payment_method' => 'cash',
                'amount_tendered' => '50.00',
                'discount_amount' => '0.00',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect();
        $sale = PosSale::query()->sole();
        $itemId = $sale->items()->value('id');

        $returnToken = (string) Str::uuid();
        $returnData = [
            'pos_sale_item_id' => $itemId,
            'quantity' => '1.000',
            'condition' => 'resalable',
            'refund_amount' => '15.00',
            'refund_method' => 'cash',
            'reason' => 'Customer returned an unopened item.',
            'idempotency_key' => $returnToken,
        ];
        $this->actingAs($manager)->post(route('pos.returns.store'), $returnData)->assertRedirect();
        $this->actingAs($manager)->post(route('pos.returns.store'), $returnData)->assertRedirect();
        $this->actingAs($manager)->post(route('pos.returns.store'), array_merge($returnData, ['condition' => 'damaged']))
            ->assertSessionHasErrors('idempotency_key');
        $this->assertDatabaseCount('payment_reversals', 1);
        $this->assertSame('partially_refunded', $sale->fresh()->payment_status);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'customer_return', 'quantity_delta' => '1.000']);
        $this->assertDatabaseCount('stock_returns', 1);

        $this->actingAs($manager)->post(route('pos.returns.store'), [
            'pos_sale_item_id' => $itemId,
            'quantity' => '2.000',
            'condition' => 'damaged',
            'refund_amount' => '0.00',
            'reason' => 'Customer returned damaged items.',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect();
        $this->assertSame(2, StockReturn::query()->count());
        $this->assertDatabaseCount('stock_movements', 3);
        $this->actingAs($manager)->get(route('pos.reports'))->assertOk()->assertSee('30.00');

        $this->actingAs($manager)->post(route('pos.returns.store'), [
            'pos_sale_item_id' => $itemId,
            'quantity' => '1.000',
            'condition' => 'resalable',
            'refund_amount' => '0.00',
            'reason' => 'Attempt to return beyond remaining quantity.',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('stock_returns', 2);
    }

    public function test_cashier_may_not_record_a_pos_return_or_manager_verification_without_role_permission(): void
    {
        $cashier = $this->userWithRole('cashier');
        [$part] = $this->saleablePart('AUTH-100', '10.0000', '3.000');
        $this->actingAs($cashier)->get(route('pos.returns.index'))->assertForbidden();
        $this->actingAs($cashier)->post(route('pos.returns.store'), [])->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->employee(['user_id' => $user->getKey(), 'is_active' => true]);

        return $user;
    }

    /**
     * @return array{0: SparePart, 1: StockLocation}
     */
    private function saleablePart(string $barcode, string $price, string $quantity): array
    {
        $location = $this->location();
        $part = $this->part([
            'sku' => 'SKU-'.$barcode,
            'barcode' => $barcode,
            'name' => 'POS test product '.$barcode,
            'selling_price' => $price,
            'default_location_id' => $location->getKey(),
            'is_active' => true,
        ]);
        $this->movement($part, $location, ['quantity_delta' => $quantity]);

        return [$part, $location];
    }
}
