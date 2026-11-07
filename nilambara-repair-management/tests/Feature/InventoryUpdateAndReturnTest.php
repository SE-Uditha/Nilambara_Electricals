<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class InventoryUpdateAndReturnTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    public function test_inventory_list_shows_action_links_for_existing_parts(): void
    {
        $this->seed(RoleSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('administrator');
        $this->employee(['user_id' => $actor->id, 'is_active' => true]);
        $part = $this->part(['default_location_id' => $this->location()->id]);

        $this->actingAs($actor)->get(route('inventory.index'))
            ->assertOk()
            ->assertSee($part->sku)
            ->assertSee(route('inventory.show', $part->id))
            ->assertSee(route('inventory.edit', $part->id));
    }

    public function test_stock_update_and_return_are_authorized_idempotent_and_traceable(): void
    {
        $this->seed(RoleSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('administrator');
        $this->employee(['user_id' => $actor->id, 'is_active' => true]);
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id, 'selling_price' => '5.0000']);
        $this->movement($part, $location, ['quantity_delta' => '10.000', 'unit_cost' => '2.0000']);

        $this->actingAs($actor)->get(route('inventory.stock-update'))
            ->assertOk()
            ->assertSee(__('inventory.quantity_change'))
            ->assertSee('data-searchable', false);
        $stockUpdate = [
            'spare_part_id' => $part->id,
            'location_id' => $location->id,
            'quantity' => '2.000',
            'selling_price' => '7.5000',
            'idempotency_key' => (string) Str::uuid(),
        ];
        $this->post(route('inventory.stock-update.store'), $stockUpdate)->assertRedirect(route('inventory.index'));
        $this->post(route('inventory.stock-update.store'), $stockUpdate)->assertRedirect(route('inventory.index'));
        $this->assertSame('7.5000', (string) $part->fresh()->selling_price);
        $this->assertDatabaseCount('stock_adjustments', 1);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseHas('stock_movements', [
            'spare_part_id' => $part->id,
            'movement_type' => 'adjustment',
            'quantity_delta' => '2.000',
            'reason' => 'Stock update; selling price 7.5000',
            'recorded_by_user_id' => $actor->id,
        ]);
        $this->assertSame('12.000', number_format((float) DB::table('stock_movements')
            ->where('spare_part_id', $part->id)
            ->where('location_id', $location->id)
            ->sum('quantity_delta'), 3, '.', ''));

        $this->get(route('stock-returns.create'))->assertOk()->assertSee(__('inventory.return_reason'));
        $stockReturn = [
            'spare_part_id' => $part->id,
            'location_id' => $location->id,
            'quantity' => '1.500',
            'reason' => 'Customer returned sealed part',
            'idempotency_key' => (string) Str::uuid(),
        ];
        $this->post(route('stock-returns.store'), $stockReturn)->assertRedirect(route('inventory.index'));
        $this->post(route('stock-returns.store'), $stockReturn)->assertRedirect(route('inventory.index'));
        $this->assertDatabaseCount('stock_adjustments', 2);
        $this->assertDatabaseCount('stock_movements', 3);
        $this->assertDatabaseHas('stock_movements', [
            'spare_part_id' => $part->id,
            'movement_type' => 'adjustment',
            'quantity_delta' => '1.500',
            'reason' => 'Stock return: Customer returned sealed part',
            'recorded_by_user_id' => $actor->id,
        ]);
        $this->assertSame('13.500', number_format((float) DB::table('stock_movements')
            ->where('spare_part_id', $part->id)
            ->where('location_id', $location->id)
            ->sum('quantity_delta'), 3, '.', ''));
        $this->assertNotNull(DB::table('stock_movements')
            ->where('reason', 'Stock return: Customer returned sealed part')
            ->value('occurred_at'));
    }

    public function test_stock_return_and_combined_stock_update_require_their_existing_permissions(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor)
            ->get(route('stock-returns.create'))
            ->assertForbidden();
        $this->get(route('inventory.stock-update'))->assertForbidden();
    }
}
