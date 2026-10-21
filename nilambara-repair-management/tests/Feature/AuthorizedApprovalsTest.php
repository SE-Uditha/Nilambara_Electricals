<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\StockWorkflowService;
use App\Modules\POS\Models\PosSale;
use App\Modules\POS\Services\POSCheckoutService;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Purchases\Services\PurchaseWorkflowService;
use App\Modules\Suppliers\Models\Supplier;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class AuthorizedApprovalsTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->employee(['user_id' => $user->id, 'is_active' => true]);

        return $user;
    }

    private function stock(): array
    {
        $location = $this->location();
        $part = $this->part(['name' => 'Approval test motor brush', 'selling_price' => '10.0000', 'default_location_id' => $location->id, 'is_active' => true]);
        $this->movement($part, $location, ['quantity_delta' => '5.000']);

        return [$part, $location];
    }

    private function draft(User $creator): Purchase
    {
        [$part] = $this->stock();
        $supplier = Supplier::create(['supplier_code' => $this->code('SUP'), 'name' => 'Approval test supplier', 'is_active' => true]);

        return app(PurchaseWorkflowService::class)->create(['supplier_id' => $supplier->id, 'purchase_date' => now()->toDateString(), 'payment_terms' => 'credit', 'discount_amount' => '0.00', 'other_costs' => '0.00', 'idempotency_key' => (string) Str::uuid(), 'items' => [['spare_part_id' => $part->id, 'quantity' => '2.000', 'unit_cost' => '5.0000']]], $creator);
    }

    private function requestStock(User $user, string $kind = 'stock_adjustment'): ApprovalRequest
    {
        [$part, $location] = $this->stock();
        $this->actingAs($user)->post(route('approval-requests.store'), ['kind' => $kind, 'spare_part_id' => $part->id, 'location_id' => $location->id, 'quantity' => $kind === 'stock_count' ? '4.000' : '-1.000', 'adjustment_type' => 'correction', 'reason' => 'Reconcile the physical stock.', 'idempotency_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();

        return ApprovalRequest::latest('id')->firstOrFail();
    }

    public function test_inbox_is_restricted_and_manual_requests_are_private(): void
    {
        $cashier = $this->actor('cashier');
        $manager = $this->actor('manager');
        $this->actingAs($cashier)->get(route('approvals.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('approvals.index'))->assertOk()->assertSee('Authorized Approvals');
        $storekeeper = $this->actor('storekeeper');
        $approval = $this->requestStock($storekeeper);
        $this->actingAs($cashier)->get(route('approval-requests.show', $approval))->assertForbidden();
        $this->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Looks correct.'])->assertForbidden();
        $this->actingAs($storekeeper)->get(route('approval-requests.index'))->assertOk()->assertSee('Stock correction');
        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_purchase_approval_posts_payable_once_and_retains_decision(): void
    {
        $purchase = $this->draft($this->actor('storekeeper'));
        $manager = $this->actor('manager');
        $this->actingAs($manager)->get(route('approvals.index'))->assertOk()->assertSee($purchase->purchase_number);
        $url = route('approvals.document.decide', ['kind' => 'purchase', 'id' => $purchase->id]);
        $this->get(route('approvals.document', ['kind' => 'purchase', 'id' => $purchase->id]))->assertOk()->assertSee('Approval test supplier');
        for ($i = 0; $i < 2; $i++) {
            $this->post($url, ['decision' => 'approved', 'comment' => 'Purchase prices checked.'])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame('finalized', $purchase->fresh()->status->value);
        $this->assertDatabaseCount('supplier_account_entries', 1);
        $this->assertDatabaseCount('authorized_approval_requests', 1);
        $approval = ApprovalRequest::sole();
        $this->assertSame($manager->id, $approval->decided_by_user_id);
        $this->get(route('approval-requests.show', $approval))->assertOk()->assertSee('Purchase prices checked.');
        $this->postJson($url, ['decision' => 'rejected', 'comment' => 'Changed my decision.'])->assertUnprocessable();
        $this->assertSame(1, AuditLog::where('action', 'approval.approved')->count());
    }

    public function test_rejected_purchase_is_cancelled_without_payable_or_goods(): void
    {
        $purchase = $this->draft($this->actor('storekeeper'));
        $this->actingAs($this->actor('manager'))->post(route('approvals.document.decide', ['kind' => 'purchase', 'id' => $purchase->id]), ['decision' => 'rejected', 'comment' => 'Supplier prices are incorrect.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $purchase->fresh()->status->value);
        $this->assertDatabaseCount('supplier_account_entries', 0);
        $this->assertDatabaseCount('goods_received_notes', 0);
    }

    public function test_pending_pos_payment_can_be_verified_or_rejected_without_duplicate_posting(): void
    {
        [$part] = $this->stock();
        $cashier = $this->actor('cashier');
        $sale = app(POSCheckoutService::class)->checkout(['payment_method' => 'bank_transfer', 'payment_reference' => 'APPROVE-BANK-01', 'idempotency_key' => (string) Str::uuid()], [$part->id => '1.000'], $cashier);
        $this->assertDatabaseCount('payments', 0);
        $this->actingAs($this->actor('manager'))->get(route('approvals.index'))->assertOk()->assertSee($sale->sale_number);
        $url = route('approvals.document.decide', ['kind' => 'pos_payment', 'id' => $sale->id]);
        $this->post($url, ['decision' => 'approved', 'comment' => 'Transfer confirmed by bank.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, ['decision' => 'approved', 'comment' => 'Transfer confirmed by bank.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('finalized', $sale->fresh()->status->value);
        $this->assertSame('4.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
        $other = app(POSCheckoutService::class)->checkout(['payment_method' => 'bank_transfer', 'payment_reference' => 'APPROVE-BANK-02', 'idempotency_key' => (string) Str::uuid()], [$part->id => '1.000'], $cashier);
        $this->post(route('approvals.document.decide', ['kind' => 'pos_payment', 'id' => $other->id]), ['decision' => 'rejected', 'comment' => 'Transfer was not received.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $other->fresh()->status->value);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('4.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
    }

    public function test_failed_pos_verification_rolls_back_decision_when_stock_has_changed(): void
    {
        [$part, $location] = $this->stock();
        $sale = app(POSCheckoutService::class)->checkout(['payment_method' => 'bank_transfer', 'payment_reference' => 'APPROVE-NOSTOCK', 'idempotency_key' => (string) Str::uuid()], [$part->id => '5.000'], $this->actor('cashier'));
        app(StockWorkflowService::class)->adjust(['spare_part_id' => $part->id, 'location_id' => $location->id, 'quantity' => '-1.000', 'adjustment_type' => 'correction', 'reason' => 'Stock used after sale submission.', 'idempotency_key' => (string) Str::uuid()], $this->actor('administrator'));
        $this->actingAs($this->actor('manager'))->postJson(route('approvals.document.decide', ['kind' => 'pos_payment', 'id' => $sale->id]), ['decision' => 'approved', 'comment' => 'Bank transfer checked.'])->assertUnprocessable();
        $this->assertDatabaseCount('authorized_approval_requests', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('draft', $sale->fresh()->status->value);
    }

    public function test_stock_requests_wait_then_execute_once_and_rejections_do_not_post(): void
    {
        $storekeeper = $this->actor('storekeeper');
        $approval = $this->requestStock($storekeeper);
        $this->assertDatabaseCount('stock_adjustments', 0);
        $this->actingAs($this->actor('administrator'))->get(route('approval-requests.show', $approval))->assertOk()->assertSee('Reconcile the physical stock.');
        foreach ([1, 2] as $i) {
            $this->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Physical stock discrepancy checked.'])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('stock_adjustments', 1);
        $count = $this->requestStock($storekeeper, 'stock_count');
        $this->actingAs($this->actor('administrator'))->post(route('approval-requests.decide', $count), ['decision' => 'rejected', 'comment' => 'Please recount with supervisor.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('stock_counts', 0);
    }

    public function test_self_approval_and_changed_or_missing_decisions_are_blocked(): void
    {
        $manager = $this->actor('administrator');
        $approval = $this->requestStock($manager);
        $this->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'I approve my own request.'])->assertForbidden();
        $this->actingAs($this->actor('administrator'))->postJson(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => ' '])->assertUnprocessable()->assertJsonValidationErrors('comment');
        $this->assertSame('pending', $approval->fresh()->status);
        $this->assertDatabaseCount('stock_adjustments', 0);
    }

    public function test_request_replay_is_safe_and_different_payload_is_rejected(): void
    {
        $user = $this->actor('cashier');
        $payment = $this->payment();
        $data = ['kind' => 'payment_refund', 'payment_id' => $payment->id, 'amount' => '10.00', 'method' => 'cash', 'reason' => 'Customer requested refund.', 'idempotency_key' => (string) Str::uuid()];
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($user)->post(route('approval-requests.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('authorized_approval_requests', 1);
        $this->postJson(route('approval-requests.store'), array_replace($data, ['amount' => '11.00']))->assertUnprocessable();
        $approval = ApprovalRequest::sole();
        $this->actingAs($this->actor('manager'))->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Refund checked against payment.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payment_reversals', 1);
        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_pos_return_approval_restores_stock_and_records_refund(): void
    {
        [$part] = $this->stock();
        $cashier = $this->actor('cashier');
        $sale = app(POSCheckoutService::class)->checkout(['payment_method' => 'cash', 'amount_tendered' => '10.00', 'idempotency_key' => (string) Str::uuid()], [$part->id => '1.000'], $cashier);
        $this->actingAs($cashier)->post(route('approval-requests.store'), ['kind' => 'pos_return', 'pos_sale_item_id' => $sale->items()->sole()->id, 'quantity' => '1.000', 'condition' => 'resalable', 'amount' => '10.00', 'method' => 'cash', 'reason' => 'Customer returned unused item.', 'idempotency_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $approval = ApprovalRequest::sole();
        $this->assertDatabaseCount('stock_returns', 0);
        $this->actingAs($this->actor('manager'))->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Returned item checked and accepted.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('stock_returns', 1);
        $this->assertDatabaseCount('payment_reversals', 1);
        $this->assertSame('5.000', (string) app(InventoryService::class)->availableAtDefaultLocation($part));
    }

    public function test_discount_approval_is_bound_to_requester_cart_prices_amount_and_is_single_use(): void
    {
        [$part] = $this->stock();
        $cashier = $this->actor('cashier');
        $cart = [$part->id => '1.000'];
        $this->actingAs($cashier)->withSession(['pos_cart' => $cart])->post(route('approval-requests.store'), ['kind' => 'pos_discount', 'amount' => '1.00', 'reason' => 'Loyal customer discount requested.', 'idempotency_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $approval = ApprovalRequest::sole();
        $checkout = ['payment_method' => 'cash', 'amount_tendered' => '10.00', 'discount_amount' => '1.00', 'discount_approval_id' => $approval->id, 'idempotency_key' => (string) Str::uuid()];
        $this->postJson(route('pos.checkout'), $checkout)->assertUnprocessable();
        $this->assertNull($approval->fresh()->consumed_at);
        $this->actingAs($this->actor('manager'))->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Loyal customer discount approved.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('approval-requests.show', $approval))->assertOk()->assertSee('valid once');
        $this->actingAs($cashier)->withSession(['pos_cart' => [$part->id => '2.000']])->postJson(route('pos.checkout'), $checkout)->assertUnprocessable();
        $this->withSession(['pos_cart' => $cart])->postJson(route('pos.checkout'), array_replace($checkout, ['discount_amount' => '2.00']))->assertUnprocessable();
        $other = $this->actor('cashier');
        $this->actingAs($other)->withSession(['pos_cart' => $cart])->postJson(route('pos.checkout'), $checkout)->assertForbidden();
        $this->actingAs($cashier)->withSession(['pos_cart' => $cart])->post(route('pos.checkout'), $checkout)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('9.00', PosSale::sole()->total);
        $this->assertNotNull($approval->fresh()->consumed_at);
        $this->withSession(['pos_cart' => $cart])->postJson(route('pos.checkout'), array_replace($checkout, ['idempotency_key' => (string) Str::uuid()]))->assertUnprocessable();
        $this->assertDatabaseCount('pos_sales', 1);
    }

    public function test_stock_count_rechecks_movements_and_keeps_failed_request_pending(): void
    {
        $storekeeper = $this->actor('storekeeper');
        $approval = $this->requestStock($storekeeper, 'stock_count');
        $reviewer = $this->actor('administrator');
        $stock = app(StockWorkflowService::class);
        $stock->adjust(['spare_part_id' => $approval->spare_part_id, 'location_id' => $approval->payload['location_id'], 'quantity' => '1.000', 'adjustment_type' => 'correction', 'reason' => 'Stock changed after count request.', 'idempotency_key' => (string) Str::uuid()], $reviewer);
        $this->actingAs($reviewer)->postJson(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Physical count checked.'])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame('pending', $approval->fresh()->status);
        $this->assertDatabaseCount('stock_counts', 0);
        $fresh = $this->requestStock($storekeeper, 'stock_count');
        $this->actingAs($reviewer)->post(route('approval-requests.decide', $fresh), ['decision' => 'approved', 'comment' => 'Fresh count checked.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('stock_counts', 1);
    }

    public function test_manager_cannot_review_stock_without_existing_stock_permissions(): void
    {
        $approval = $this->requestStock($this->actor('storekeeper'));
        $this->actingAs($this->actor('manager'))->get(route('approval-requests.show', $approval))->assertForbidden();
        $this->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Try to approve stock.'])->assertForbidden();
        $this->assertSame('pending', $approval->fresh()->status);
        $this->get(route('approvals.index'))->assertOk()->assertDontSee($approval->reason);
    }

    public function test_changed_prices_invalidate_discount_and_failed_checkout_does_not_consume_it(): void
    {
        [$part] = $this->stock();
        $cashier = $this->actor('cashier');
        $cart = [$part->id => '1.000'];
        $this->actingAs($cashier)->withSession(['pos_cart' => $cart])->post(route('approval-requests.store'), ['kind' => 'pos_discount', 'amount' => '1.00', 'reason' => 'Loyal customer discount.', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $approval = ApprovalRequest::sole();
        $this->actingAs($this->actor('manager'))->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Discount is approved.'])->assertSessionHasNoErrors();
        $data = ['payment_method' => 'cash', 'amount_tendered' => '1.00', 'discount_amount' => '1.00', 'discount_approval_id' => $approval->id, 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($cashier)->withSession(['pos_cart' => $cart])->postJson(route('pos.checkout'), $data)->assertUnprocessable()->assertJsonValidationErrors('amount_tendered');
        $this->assertNull($approval->fresh()->consumed_at);
        $part->update(['selling_price' => '11.0000']);
        $this->postJson(route('pos.checkout'), array_replace($data, ['amount_tendered' => '20.00']))->assertUnprocessable()->assertJsonValidationErrors('discount_approval_id');
        $this->assertNull($approval->fresh()->consumed_at);
        $this->assertDatabaseCount('pos_sales', 0);
    }

    public function test_request_forms_and_status_filters_render_for_authorized_roles(): void
    {
        $this->actingAs($this->actor('administrator'));
        foreach (['payment_refund', 'payment_reversal', 'pos_return', 'stock_adjustment', 'stock_count', 'pos_discount'] as $kind) {
            $this->get(route('approval-requests.create', ['kind' => $kind]))->assertOk()->assertSee('Submit request');
        }
        $this->get(route('approvals.index', ['status' => 'approved']))->assertOk();
        $this->getJson(route('approvals.index', ['status' => 'fake']))->assertUnprocessable();
    }
}
