<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\Step11TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PosRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_tables_links_permissions_and_templates_are_retired(): void
    {
        $this->seed([RoleSeeder::class, Step11TemplateSeeder::class]);
        $this->assertFalse(Schema::hasTable('pos_sales'));
        $this->assertFalse(Schema::hasTable('pos_sale_items'));
        foreach (['payment_allocations', 'customer_account_entries', 'stock_returns', 'document_print_histories', 'authorized_approval_requests'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'pos_sale_id'), $table);
        }
        foreach (['stock_movements', 'stock_return_items', 'authorized_approval_requests'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'pos_sale_item_id'), $table);
        }
        $this->assertSame(0, DB::table('permissions')->where('name', 'like', 'pos.%')->count());
        $this->assertSame(0, DB::table('document_sequences')->whereIn('scope', ['pos_sale', 'pos_return'])->count());
        $this->assertSame(0, DB::table('print_templates')->where('document_type', 'pos_receipt')->count());
    }

    public function test_removed_pos_endpoints_are_not_available_and_shared_pages_still_render(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrator');
        $this->actingAs($user);
        foreach (['/pos', '/pos/sales', '/pos/returns', '/pos/reports'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->post('/pos/checkout', [])->assertNotFound();
        $this->get('/authorized-approvals/pos_payment/1')->assertNotFound();
        foreach (['dashboard', 'payments.index', 'inventory.index', 'approvals.index', 'print-templates.index', 'backups.index'] as $route) {
            $response = $this->get(route($route))->assertOk();
            $response->assertDontSee('href="'.url('/pos').'"', false);
        }
        foreach (['sales', 'profit-estimate', 'valuation'] as $report) {
            $this->get(route('reports.show', $report))->assertOk();
        }
        $this->get(route('reports.show', 'pos-parts'))->assertForbidden();
    }
}
