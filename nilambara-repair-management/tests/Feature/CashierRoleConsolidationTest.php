<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\RolesPermissions\Models\Permission;
use App\Modules\RolesPermissions\Models\Role;
use App\Modules\RolesPermissions\Services\MergeReceptionistIntoCashier;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashierRoleConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private function legacyRole(): Role
    {
        return Role::create(['name' => 'receptionist', 'code' => 'receptionist', 'label' => 'Receptionist', 'guard_name' => 'web', 'is_active' => true]);
    }

    public function test_cashier_is_the_only_front_desk_role_and_has_both_workflows_without_management_powers(): void
    {
        $this->seed(RoleSeeder::class);
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->assertSame(['administrator', 'cashier'], Role::orderBy('name')->pluck('name')->all());
        foreach (['customers.create', 'customers.update', 'receipts.create', 'receipts.print', 'jobs.view', 'job-cards.print', 'quotations.create', 'quotations.update', 'quotations.issue', 'reports.repair', 'invoices.create', 'invoices.issue', 'payments.receive', 'credit.collect', 'customers.statement', 'jobs.assign', 'quotations.approve', 'invoices.cancel', 'payments.reverse', 'purchases.approve', 'inventory.adjust'] as $permission) {
            $this->assertTrue($cashier->can($permission), $permission);
        }
        foreach (['roles.manage', 'employees.create', 'customers.deactivate', 'settings.update'] as $permission) {
            $this->assertFalse($cashier->can($permission), $permission);
        }
        foreach (['customers.create', 'repair-receipts.create', 'quotations.create', 'invoices.create', 'payments.create'] as $route) {
            $this->actingAs($cashier)->get(route($route))->assertOk();
        }
    }

    public function test_merge_preserves_custom_permissions_accounts_other_roles_and_individual_denials(): void
    {
        $this->seed(RoleSeeder::class);
        $legacy = $this->legacyRole();
        $legacy->givePermissionTo(['reports.financial', 'audit.view']);
        $user = User::factory()->create(['username' => 'existing.frontdesk']);
        $user->assignRole([$legacy, 'cashier', 'administrator']);
        $passwordHash = $user->password;
        $permission = Permission::findByName('audit.view');
        DB::table('permission_user')->insert(['user_id' => $user->id, 'permission_id' => $permission->id, 'model_type' => User::class, 'effect' => 'deny']);
        $migration = require database_path('migrations/2026_10_21_100000_merge_receptionist_into_cashier.php');

        $migration->up();

        $user = $user->fresh();
        $this->assertSame('existing.frontdesk', $user->username);
        $this->assertSame($passwordHash, $user->password);
        $this->assertSame(['administrator', 'cashier'], $user->roles->pluck('name')->sort()->values()->all());
        $this->assertTrue($user->can('reports.financial'));
        $this->assertFalse($user->can('audit.view'));
        $this->assertSame(1, DB::table('role_user')->where('role_id', Role::findByName('cashier')->id)->where('user_id', $user->id)->count());
        $this->assertDatabaseMissing('roles', ['name' => 'receptionist']);
        $this->assertDatabaseMissing('role_user', ['role_id' => $legacy->id]);
        $this->assertDatabaseMissing('permission_role', ['role_id' => $legacy->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'roles.receptionist_merged_into_cashier']);
        $this->assertSame(1, User::count());
        $this->assertSame(1, DB::table('permission_user')->count());
        $this->seed(RoleSeeder::class);
        $this->assertFalse(app(MergeReceptionistIntoCashier::class)->run());
        $this->assertSame(1, AuditLog::where('action', 'roles.receptionist_merged_into_cashier')->count());
        $this->assertTrue($user->fresh()->can('reports.financial'));
    }

    public function test_seeding_a_legacy_install_transfers_old_only_accounts_and_never_recreates_receptionist(): void
    {
        $this->seed(RoleSeeder::class);
        $legacy = $this->legacyRole();
        $legacy->givePermissionTo(['customers.create', 'receipts.create']);
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole($legacy);

        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(['cashier'], $user->fresh()->roles->pluck('name')->all());
        $this->assertFalse($user->fresh()->is_active);
        $this->assertDatabaseCount('roles', 2);
        $this->assertDatabaseMissing('roles', ['name' => 'receptionist']);
    }

    public function test_legacy_only_install_creates_cashier_and_retains_legacy_grants(): void
    {
        $this->seed(RoleSeeder::class);
        Role::findByName('cashier')->delete();
        $legacy = $this->legacyRole();
        $legacy->givePermissionTo('receipts.create');
        $user = User::factory()->create();
        $user->assignRole($legacy);

        $this->assertTrue(app(MergeReceptionistIntoCashier::class)->run());

        $this->assertSame(['cashier'], $user->fresh()->roles->pluck('name')->all());
        $this->assertTrue($user->fresh()->can('receipts.create'));
    }
}
