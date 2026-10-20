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
use Brick\Math\BigDecimal;
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

    public function test_whole_unit_products_reject_fractional_cart_checkout_and_returns_without_server_errors(): void
    {
        $manager = $this->userWithRole('manager');
        $manager->assignRole('cashier');
        [$part] = $this->saleablePart('WHOLE-100', '10.0000', '5.000');
        $part->unit->update(['quantity_scale' => 0]);
        $this->actingAs($manager)->post(route('pos.cart.add'), ['part_id' => $part->id, 'quantity' => '0.5'])
            ->assertSessionHasErrors('barcode');
        $this->actingAs($manager)->put(route('pos.cart.update', $part), ['quantity' => '0.5'])
            ->assertSessionHasErrors('quantity');
        $this->actingAs($manager)->withSession(['pos_cart' => [$part->id => '0.500']])
            ->post(route('pos.checkout'), $this->cashCheckoutData('10.00'))->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('pos_sales', 0);
        $this->actingAs($manager)->withSession(['pos_cart' => [$part->id => '1.000']])
            ->post(route('pos.checkout'), $this->cashCheckoutData('10.00'))->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('pos.returns.store'), [
            'pos_sale_item_id' => PosSale::query()->sole()->items()->value('id'),
            'quantity' => '0.500', 'condition' => 'resalable', 'reason' => 'Fraction is not a whole item.',
            'refund_amount' => '0.00', 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('quantity');
        $this->assertDatabaseCount('stock_returns', 0);
    }

    public function test_discounted_returns_cannot_refund_the_undiscounted_item_value(): void
    {
        $manager = $this->userWithRole('manager');
        $manager->assignRole('cashier');
        [$part] = $this->saleablePart('DISCOUNT-RETURN', '10.0000', '5.000');
        $data = $this->cashCheckoutData('20.00');
        $data['discount_amount'] = '10.00';
        $this->actingAs($manager)->withSession(['pos_cart' => [$part->id => '2.000']])
            ->post(route('pos.checkout'), $data)->assertSessionHasNoErrors();
        $return = [
            'pos_sale_item_id' => PosSale::query()->sole()->items()->value('id'),
            'quantity' => '1.000', 'condition' => 'resalable', 'reason' => 'Return one discounted item.',
            'refund_amount' => '10.00', 'refund_method' => 'cash', 'idempotency_key' => (string) Str::uuid(),
        ];
        $this->actingAs($manager)->post(route('pos.returns.store'), $return)->assertSessionHasErrors('refund_amount');
        $this->assertDatabaseCount('stock_returns', 0);
        $return['refund_amount'] = '5.00';
        $this->actingAs($manager)->post(route('pos.returns.store'), $return)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payment_reversals', ['amount' => '5.00']);
    }

    public function test_receipt_uses_sale_snapshots_and_all_paper_formats_generate_pdfs(): void
    {
        $cashier = $this->userWithRole('cashier');
        [$part] = $this->saleablePart('SNAPSHOT-100', '12.3456', '5.000');
        $originalName = $part->name;
        $this->actingAs($cashier)->withSession(['pos_cart' => [$part->id => '1.000']])
            ->post(route('pos.checkout'), $this->cashCheckoutData('20.00'))->assertSessionHasNoErrors();
        $sale = PosSale::query()->sole();
        $part->update(['name' => 'Changed catalog name', 'selling_price' => '99.0000', 'is_active' => false]);
        $this->actingAs($cashier)->get(route('pos.sales.print', $sale))
            ->assertOk()->assertSee($originalName)->assertDontSee('Changed catalog name')->assertSee('12.35');
        foreach (['thermal', 'A4', 'A5'] as $paper) {
            $response = $this->actingAs($cashier)->get(route('pos.sales.pdf', ['sale' => $sale, 'paper' => $paper]));
            $response->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $response->getContent());
        }
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_pending_sale_cancellation_has_no_stock_or_payment_effects_and_cannot_be_verified(): void
    {
        $manager = $this->userWithRole('manager');
        $manager->assignRole('cashier');
        [$part] = $this->saleablePart('CANCEL-100', '10.0000', '5.000');
        $this->actingAs($manager)->withSession(['pos_cart' => [$part->id => '1.000']])
            ->post(route('pos.checkout'), [
                'payment_method' => 'cheque', 'payment_reference' => 'CHQ-CANCEL-100', 'cheque_date' => '2026-10-20',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertSessionHasNoErrors();
        $sale = PosSale::query()->sole();
        $this->actingAs($manager)->get(route('pos.sales.print', $sale))->assertNotFound();
        $this->actingAs($manager)->post(route('pos.sales.cancel', $sale), ['reason' => 'Customer cancelled the pending sale.'])
            ->assertSessionHasNoErrors();
        $this->actingAs($manager)->post(route('pos.sales.verify', $sale))->assertSessionHasErrors();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_client_totals_are_ignored_and_inactive_customers_are_rejected(): void
    {
        $cashier = $this->userWithRole('cashier');
        [$part] = $this->saleablePart('SERVER-PRICE', '12.5000', '5.000');
        $customer = $this->customer(['is_active' => false]);
        $data = $this->cashCheckoutData('20.00') + ['customer_id' => $customer->id, 'total' => '0.01', 'unit_price' => '0.01'];
        $this->actingAs($cashier)->withSession(['pos_cart' => [$part->id => '1.000']])
            ->post(route('pos.checkout'), $data)->assertSessionHasErrors('customer_id');
        $this->assertDatabaseCount('payments', 0);
        $customer->update(['is_active' => true]);
        $this->actingAs($cashier)->post(route('pos.checkout'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pos_sales', ['customer_id' => $customer->id, 'total' => '12.50', 'change_due' => '7.50']);
    }

    public function test_cart_refresh_update_remove_and_clear_never_change_inventory(): void
    {
        $cashier = $this->userWithRole('cashier');
        [$part] = $this->saleablePart('CART-100', '10.0000', '5.000');
        $this->actingAs($cashier)->post(route('pos.cart.add'), ['part_id' => $part->id, 'quantity' => '1'])->assertRedirect();
        $this->get(route('pos.index'))->assertOk();
        $this->assertSame('1.000', session('pos_cart')[$part->id]);
        $this->put(route('pos.cart.update', $part), ['quantity' => '2'])->assertSessionHasNoErrors();
        $this->assertSame('2.000', session('pos_cart')[$part->id]);
        $this->delete(route('pos.cart.remove', $part))->assertRedirect();
        $this->assertSame([], session('pos_cart'));
        $this->post(route('pos.cart.add'), ['part_id' => $part->id, 'quantity' => '1'])->assertRedirect();
        $this->delete(route('pos.cart.clear'))->assertRedirect();
        $this->assertNull(session('pos_cart'));
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('pos_sales', 0);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_stock_posting_failure_rolls_back_sale_payment_allocation_and_number(): void
    {
        $cashier = $this->userWithRole('cashier');
        [$part] = $this->saleablePart('ROLLBACK-100', '10.0000', '5.000');
        $this->partialMock(InventoryService::class, function ($mock): void {
            $mock->shouldReceive('recordPosSale')->once()->andThrow(new \RuntimeException('Injected stock posting failure.'));
        });
        $this->actingAs($cashier)->withSession(['pos_cart' => [$part->id => '1.000']])
            ->post(route('pos.checkout'), $this->cashCheckoutData('10.00'))->assertStatus(500);
        $this->assertDatabaseCount('pos_sales', 0);
        $this->assertDatabaseCount('pos_sale_items', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('document_sequences', ['scope' => 'pos_sale', 'next_value' => 1]);
    }

    public function test_refund_only_reporting_period_includes_negative_net_sales_and_collections(): void
    {
        $actor = $this->userWithRole('administrator');
        [$part] = $this->saleablePart('REPORT-100', '10.0000', '5.000');
        $this->travelTo(now()->setDate(2026, 10, 20));
        $this->actingAs($actor)->withSession(['pos_cart' => [$part->id => '1.000']])
            ->post(route('pos.checkout'), $this->cashCheckoutData('10.00'))->assertSessionHasNoErrors();
        $sale = PosSale::query()->sole();
        $this->travelTo(now()->addDay());
        $this->post(route('pos.returns.store'), [
            'pos_sale_item_id' => $sale->items()->value('id'), 'quantity' => '1.000', 'condition' => 'resalable',
            'reason' => 'Refund on the following day.', 'refund_amount' => '10.00', 'refund_method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->get(route('pos.reports', ['date_from' => '2026-10-21', 'date_to' => '2026-10-21']))
            ->assertOk()->assertViewHas('netCollected', '-10.00')
            ->assertViewHas('periods', fn ($periods) => $periods->count() === 1 && $periods->first()->net_sales === '-10.00');
        $this->travelBack();
    }

    public function test_final_partial_return_can_refund_the_remaining_rounding_cent(): void
    {
        $actor = $this->userWithRole('administrator');
        [$part] = $this->saleablePart('ROUND-RETURN', '0.5000', '3.000');
        $this->actingAs($actor)->withSession(['pos_cart' => [$part->id => '3.000']])
            ->post(route('pos.checkout'), array_replace($this->cashCheckoutData('1.00'), ['discount_amount' => '0.50']))
            ->assertSessionHasNoErrors();
        $sale = PosSale::query()->sole();
        foreach (['0.33', '0.33', '0.34'] as $refund) {
            $this->post(route('pos.returns.store'), [
                'pos_sale_item_id' => $sale->items()->value('id'), 'quantity' => '1.000', 'condition' => 'resalable',
                'reason' => 'Partial return rounding regression.', 'refund_amount' => $refund, 'refund_method' => 'cash',
                'idempotency_key' => (string) Str::uuid(),
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame('1.00', (string) BigDecimal::of((string) $sale->stockReturns()->sum('refund_amount'))->toScale(2));
        $this->assertSame('refunded', $sale->fresh()->payment_status);
        $this->assertSame('3.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
    }

    public function test_discount_rounding_across_lines_allows_exact_full_refund(): void
    {
        $actor = $this->userWithRole('administrator');
        [$first] = $this->saleablePart('ROUND-LINE-A', '0.5000', '1.000');
        [$second] = $this->saleablePart('ROUND-LINE-B', '0.5000', '1.000');
        $this->actingAs($actor)->withSession(['pos_cart' => [$first->id => '1.000', $second->id => '1.000']])
            ->post(route('pos.checkout'), array_replace($this->cashCheckoutData('1.00'), ['discount_amount' => '0.01']))
            ->assertSessionHasNoErrors();
        $sale = PosSale::query()->sole();
        foreach ($sale->items()->orderBy('id')->get() as $index => $item) {
            $this->post(route('pos.returns.store'), [
                'pos_sale_item_id' => $item->id, 'quantity' => '1.000', 'condition' => 'resalable',
                'reason' => 'Full return of discounted lines.', 'refund_amount' => $index === 0 ? '0.49' : '0.50',
                'refund_method' => 'cash', 'idempotency_key' => (string) Str::uuid(),
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame('refunded', $sale->fresh()->payment_status);
        $this->assertSame('0.99', (string) BigDecimal::of((string) $sale->stockReturns()->sum('refund_amount'))->toScale(2));
    }

    private function cashCheckoutData(string $tendered): array
    {
        return ['payment_method' => 'cash', 'amount_tendered' => $tendered,
            'discount_amount' => '0.00', 'idempotency_key' => (string) Str::uuid()];
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
