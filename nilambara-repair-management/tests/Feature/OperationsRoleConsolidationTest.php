<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RolesPermissions\Models\Permission;
use App\Modules\RolesPermissions\Models\Role;
use App\Modules\RolesPermissions\Services\MergeOperationsIntoCashier;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationsRoleConsolidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_unions_grants_disables_extra_logins_and_preserves_history_and_denials(): void
    {
        $this->seed(RoleSeeder::class);
        $cashier = Role::findByName('cashier');
        $cashier->revokePermissionTo('audit.view');
        $originalCashier = User::factory()->create();
        $originalCashier->assignRole($cashier);
        $protected = User::factory()->create();
        $protected->assignRole('administrator');
        $ids = [];
        foreach (['manager', 'storekeeper'] as $name) {
            $role = Role::create(['name' => $name, 'code' => $name, 'label' => $name, 'guard_name' => 'web', 'is_active' => true]);
            $role->givePermissionTo('audit.view');
            $extra = User::factory()->create(['username' => $name.'.demo']);
            $extra->assignRole($role);
            $ids[$extra->id] = $extra->password;
            $protected->assignRole($role);
            $originalCashier->assignRole($role);
        }
        DB::table('permission_user')->insert(['user_id' => $originalCashier->id, 'permission_id' => Permission::findByName('audit.view')->id, 'model_type' => User::class, 'effect' => 'deny']);
        $migration = require database_path('migrations/2026_10_21_190000_merge_operations_into_cashier.php');
        $migration->up();
        foreach ($ids as $id => $password) {
            $user = User::findOrFail($id);
            $this->assertFalse($user->is_active);
            $this->assertSame($password, $user->password);
            $this->assertSame(['cashier'], $user->roles->pluck('name')->all());
            $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        }
        $this->assertTrue($originalCashier->fresh()->is_active);
        $this->assertTrue($protected->fresh()->is_active);
        $this->assertTrue($protected->fresh()->hasRole('administrator'));
        $this->assertFalse($originalCashier->fresh()->can('audit.view'));
        $this->assertTrue(Role::findByName('cashier')->hasPermissionTo('audit.view'));
        $this->assertSame(['administrator', 'cashier'], Role::orderBy('name')->pluck('name')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'roles.manager_merged_into_cashier']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'roles.storekeeper_merged_into_cashier']);
        $this->assertFalse(app(MergeOperationsIntoCashier::class)->run());
        $this->seed(RoleSeeder::class);
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('roles', 2);
        foreach (array_keys($ids) as $id) {
            $this->assertFalse(User::findOrFail($id)->is_active);
        }
    }
}
