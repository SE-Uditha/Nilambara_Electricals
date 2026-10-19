<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Services\EmployeeRegistrationService;
use App\Modules\Employees\Services\UserAccountService;
use App\Modules\RolesPermissions\Models\Permission;
use App\Modules\RolesPermissions\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use CreatesDatabaseRecords;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_administrator_can_register_employee_and_link_a_hashed_login_account(): void
    {
        $administrator = $this->administrator();
        $role = Role::query()->where('name', 'technician')->firstOrFail();

        $this->actingAs($administrator)->post(route('employees.store'), [
            'name' => 'Technician One',
            'mobile_number' => '077 123 4567',
            'job_title' => 'Repairer',
            'department' => 'Workshop',
            'date_joined' => '2026-10-01',
            'is_active' => '1',
            'create_account' => '1',
            'username' => ' TECHNICIAN.ONE ',
            'email' => null,
            'role_id' => $role->id,
            'password' => 'Strong-Password-123',
            'password_confirmation' => 'Strong-Password-123',
        ])->assertRedirect();

        $employee = Employee::query()->sole();
        $user = $employee->user;

        $this->assertStringStartsWith('EMP-', $employee->employee_code);
        $this->assertSame('technician.one', $user->username);
        $this->assertTrue(Hash::check('Strong-Password-123', $user->password));
        $this->assertTrue($user->hasRole('technician'));
        $this->assertSame($employee->id, $user->employee->id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'employee.created', 'entity_id' => $employee->id]);
    }

    public function test_receptionist_cannot_register_employees_or_create_accounts(): void
    {
        $user = User::factory()->create();
        $user->assignRole('receptionist');

        $this->actingAs($user)->get(route('employees.create'))->assertForbidden();
        $this->actingAs($user)->post(route('employees.store'), [
            'name' => 'Unauthorized',
            'is_active' => '1',
        ])->assertForbidden();

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_username_uniqueness_is_case_insensitive_and_rejected_before_creation(): void
    {
        $administrator = $this->administrator();
        User::factory()->create(['username' => 'operator']);

        $this->actingAs($administrator)->from(route('employees.create'))->post(route('employees.store'), [
            'name' => 'Duplicate User',
            'is_active' => '1',
            'create_account' => '1',
            'username' => ' OPERATOR ',
            'role_id' => Role::query()->where('name', 'cashier')->value('id'),
            'password' => 'Strong-Password-123',
            'password_confirmation' => 'Strong-Password-123',
        ])->assertRedirect(route('employees.create'))->assertSessionHasErrors('username');

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_employee_ids_are_unique_for_multiple_registrations(): void
    {
        $administrator = $this->administrator();
        $registration = app(EmployeeRegistrationService::class);

        $first = $registration->register(['name' => 'First', 'is_active' => true], $administrator);
        $second = $registration->register(['name' => 'Second', 'is_active' => true], $administrator);

        $this->assertNotSame($first->employee_code, $second->employee_code);
        $this->assertSame(2, Employee::query()->count());
    }

    public function test_individual_denial_overrides_role_permission(): void
    {
        $administrator = $this->administrator();
        $target = User::factory()->create();
        $target->assignRole('manager');
        $dashboardPermission = Permission::query()->where('name', 'dashboard.view')->firstOrFail();

        $this->actingAs($administrator)->put(route('admin.users.permissions', $target), [
            'permissions' => [$dashboardPermission->id => 'deny'],
        ])->assertRedirect();

        $this->actingAs($target)->get(route('dashboard'))->assertForbidden();
    }

    public function test_administrator_management_pages_render(): void
    {
        $administrator = $this->administrator();
        $employee = $this->employee(['user_id' => User::factory()->create()->id]);
        $role = Role::query()->where('name', 'technician')->firstOrFail();

        $this->actingAs($administrator);
        $this->get(route('employees.index'))->assertOk();
        $this->get(route('employees.create'))->assertOk();
        $this->get(route('employees.show', $employee))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.users.show', $employee->user))->assertOk();
        $this->get(route('admin.roles.index'))->assertOk();
        $this->get(route('admin.roles.edit', $role))->assertOk();
        $this->get(route('admin.permissions.index'))->assertOk();
    }

    public function test_technician_policy_only_allows_assigned_jobs(): void
    {
        $user = User::factory()->create();
        $employee = $this->employee(['user_id' => $user->id]);
        $otherEmployee = $this->employee();
        $user->assignRole('technician');

        $assignedJob = $this->job(null, ['technician_id' => $employee->id]);
        $unassignedJob = $this->job(null, ['technician_id' => $otherEmployee->id]);

        $this->assertTrue(Gate::forUser($user)->allows('view', $assignedJob));
        $this->assertFalse(Gate::forUser($user)->allows('view', $unassignedJob));
    }

    public function test_employee_deactivation_disables_linked_login_without_deleting_history(): void
    {
        $actor = $this->administrator();
        $target = $this->administrator();
        $employee = $this->employee(['user_id' => $target->id]);

        $this->actingAs($actor)->put(route('employees.update', $employee), [
            'name' => $employee->name,
            'is_active' => '0',
        ])->assertRedirect(route('employees.show', $employee));

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'is_active' => 0]);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => 0]);
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
    }

    public function test_failed_employee_account_transaction_leaves_no_partial_records(): void
    {
        $administrator = $this->administrator();

        try {
            app(EmployeeRegistrationService::class)->register([
                'name' => 'Transaction Failure',
                'is_active' => true,
                'create_account' => true,
                'username' => 'rollback-user',
                'password' => 'Strong-Password-123',
                'role_id' => 999999,
            ], $administrator);

            $this->fail('An invalid role must abort employee/account creation.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('employees', 0);
            $this->assertDatabaseCount('users', 1);
        }
    }

    public function test_role_seeder_is_idempotent_and_administrator_receives_all_permissions(): void
    {
        $roleCount = Role::query()->count();
        $permissionCount = Permission::query()->count();
        $administrator = Role::query()->where('name', 'administrator')->firstOrFail();

        $this->seed(RoleSeeder::class);

        $this->assertSame(6, $roleCount);
        $this->assertSame($roleCount, Role::query()->count());
        $this->assertSame($permissionCount, Permission::query()->count());
        $this->assertSame($permissionCount, $administrator->fresh()->permissions()->count());
    }

    public function test_default_role_permissions_follow_the_restricted_matrix(): void
    {
        $checks = [
            'receptionist' => [
                'customers.create' => true,
                'receipts.create' => true,
                'employees.create' => false,
                'payments.reverse' => false,
            ],
            'technician' => [
                'jobs.inspect' => true,
                'jobs.complete' => true,
                'settings.update' => false,
            ],
            'cashier' => [
                'invoices.create' => true,
                'payments.receive' => true,
                'invoices.cancel' => false,
                'payments.reverse' => false,
            ],
            'storekeeper' => [
                'inventory.adjust' => true,
                'purchases.receive' => true,
                'suppliers.create' => false,
                'purchases.approve' => false,
                'backups.restore' => false,
            ],
            'manager' => [
                'reports.financial' => true,
                'purchases.approve' => true,
                'roles.manage' => false,
                'settings.update' => false,
            ],
        ];

        foreach ($checks as $roleName => $permissions) {
            $user = User::factory()->create();
            $user->assignRole($roleName);

            foreach ($permissions as $permission => $expected) {
                $this->assertSame($expected, $user->can($permission), "{$roleName} access to {$permission}");
            }
        }
    }

    public function test_disabled_user_is_logged_out_on_the_next_request(): void
    {
        $actor = $this->administrator();
        $disabledUser = User::factory()->create();
        $disabledUser->assignRole('manager');
        app(UserAccountService::class)->setEnabled($disabledUser, false, $actor);

        $this->actingAs($disabledUser->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_last_active_administrator_cannot_be_disabled(): void
    {
        $administrator = $this->administrator();
        $actor = User::factory()->create();

        try {
            app(UserAccountService::class)->setEnabled($administrator, false, $actor);
            $this->fail('The last active administrator must remain enabled.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role_id', $exception->errors());
            $this->assertTrue($administrator->fresh()->is_active);
        }
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrator');

        return $user;
    }
}
