<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Customers\Models\Customer;
use App\Modules\RolesPermissions\Models\Permission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_cashier_can_register_individual_and_company_customers(): void
    {
        $user = $this->userWithRole('cashier');

        $this->actingAs($user)->post(route('customers.store'), $this->validIndividual())
            ->assertRedirect();

        $individual = Customer::query()->sole();
        $this->assertSame('CUS-', substr($individual->customer_code, 0, 4));
        $this->assertSame(30, strlen($individual->customer_code));
        $this->assertSame('Nalaka Perera', $individual->display_name);
        $this->assertSame('0.00', $individual->opening_balance);
        $this->assertDatabaseCount('customer_account_entries', 0);

        $this->actingAs($user)->post(route('customers.store'), $this->validCompany())
            ->assertRedirect();

        $company = Customer::query()->where('customer_type', 'company')->sole();
        $this->assertSame('Lanka Motors (Pvt) Ltd', $company->display_name);
        $this->assertSame('Kasun Fernando', $company->contact_person);
        $this->assertNotSame($individual->customer_code, $company->customer_code);
        $this->assertSame(2, AuditLog::query()->where('action', 'customer.created')->count());
    }

    public function test_authorized_financial_user_records_opening_balance_once(): void
    {
        $administrator = $this->userWithRole('administrator');
        $this->actingAs($administrator)->post(route('customers.store'), $this->validIndividual([
            'opening_balance' => '1250.50',
            'is_active' => '1',
        ]))->assertRedirect();

        $customer = Customer::query()->sole();
        $this->assertSame('1250.50', $customer->opening_balance);
        $this->assertDatabaseHas('customer_account_entries', [
            'customer_id' => $customer->id,
            'entry_type' => 'opening',
            'amount' => '1250.50',
            'recorded_by_user_id' => $administrator->id,
        ]);
    }

    public function test_zero_opening_balance_does_not_create_a_ledger_entry(): void
    {
        $user = $this->userWithRole('cashier');

        $this->actingAs($user)->post(route('customers.store'), $this->validIndividual())
            ->assertRedirect();

        $this->assertDatabaseCount('customer_account_entries', 0);
    }

    public function test_customer_forms_show_only_fields_allowed_by_the_users_permissions(): void
    {
        $cashier = $this->userWithRole('cashier');
        DB::table('permission_user')->insert(['user_id' => $cashier->id, 'permission_id' => Permission::findByName('customers.statement')->id, 'model_type' => User::class, 'effect' => 'deny']);
        $this->actingAs($cashier)->get(route('customers.create'))
            ->assertOk()
            ->assertDontSee('name="opening_balance"', false)
            ->assertDontSee('name="is_active"', false);

        $administrator = $this->userWithRole('administrator');
        $this->actingAs($administrator)->get(route('customers.create'))
            ->assertOk()
            ->assertSee('name="opening_balance"', false)
            ->assertSee('name="is_active"', false);
    }

    public function test_cashier_cannot_submit_financial_balance_or_inactive_status(): void
    {
        $user = $this->userWithRole('cashier');
        DB::table('permission_user')->insert(['user_id' => $user->id, 'permission_id' => Permission::findByName('customers.statement')->id, 'model_type' => User::class, 'effect' => 'deny']);

        $this->actingAs($user)->post(route('customers.store'), $this->validIndividual([
            'opening_balance' => '25.00',
            'is_active' => '0',
        ]))->assertSessionHasErrors(['opening_balance', 'is_active']);

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_validation_requires_type_name_address_and_valid_contact_data(): void
    {
        $user = $this->userWithRole('cashier');
        $invalid = $this->validIndividual([
            'customer_type' => 'individual',
            'customer_name' => '',
            'address' => '',
            'mobile_number' => 'not a phone',
            'email' => 'not-an-email',
            'registration_date' => 'not-a-date',
        ]);

        $this->actingAs($user)->from(route('customers.create'))
            ->post(route('customers.store'), $invalid)
            ->assertRedirect(route('customers.create'))
            ->assertSessionHasErrors([
                'customer_name', 'address', 'mobile_number', 'email', 'registration_date',
            ]);

        $this->actingAs($user)->post(route('customers.store'), $this->validIndividual(['customer_type' => 'invalid']))
            ->assertSessionHasErrors(['customer_type']);
        $this->actingAs($user)->post(route('customers.store'), $this->validIndividual(['customer_name' => '']))
            ->assertSessionHasErrors(['customer_name']);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_company_type_requires_company_name_but_email_and_contact_person_are_optional(): void
    {
        $user = $this->userWithRole('cashier');
        $invalid = $this->validCompany(['company_name' => '']);

        $this->actingAs($user)->post(route('customers.store'), $invalid)
            ->assertSessionHasErrors(['company_name']);

        $this->actingAs($user)->post(route('customers.store'), $this->validCompany([
            'email' => '',
            'contact_person' => '',
        ]))->assertRedirect();

        $customer = Customer::query()->sole();
        $this->assertNull($customer->email);
        $this->assertNull($customer->contact_person);
    }

    public function test_customer_search_matches_customer_code_name_and_mobile_and_excludes_inactive(): void
    {
        $user = $this->userWithRole('cashier');
        $customer = $this->registerCustomer($user, $this->validIndividual());

        $this->actingAs($user)->getJson(route('customers.search', ['q' => substr($customer->customer_code, 0, 8)]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $customer->id);
        $this->actingAs($user)->getJson(route('customers.search', ['q' => 'Nalaka']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Nalaka Perera');
        $this->actingAs($user)->getJson(route('customers.search', ['q' => '0771234567']))
            ->assertOk()
            ->assertJsonPath('data.0.mobile_number', '0771234567');

        $customer->forceFill(['is_active' => false])->save();
        $this->actingAs($user)->getJson(route('customers.search', ['q' => 'Nalaka']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_customer_list_searches_company_name_filters_and_paginates_in_database(): void
    {
        $user = $this->userWithRole('administrator');
        $company = $this->registerCustomer($user, $this->validCompany());

        $this->actingAs($user)->get(route('customers.index', ['q' => 'Lanka Motors', 'type' => 'company']))
            ->assertOk()
            ->assertSee($company->customer_code)
            ->assertSee('Lanka Motors (Pvt) Ltd');

        for ($index = 0; $index < 21; $index++) {
            $this->registerCustomer($user, $this->validIndividual([
                'customer_name' => "Customer {$index}",
                'email' => "customer{$index}@example.test",
            ]));
        }

        $this->actingAs($user)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('Next');
    }

    public function test_customer_can_be_updated_without_changing_id_or_opening_balance(): void
    {
        $user = $this->userWithRole('cashier');
        $customer = $this->registerCustomer($this->userWithRole('administrator'), $this->validIndividual([
            'opening_balance' => '75.00',
            'is_active' => '1',
        ]));
        $originalCode = $customer->customer_code;

        $this->actingAs($user)->put(route('customers.update', $customer), [
            'customer_code' => 'CUS-MANUALLY-CHANGED',
            'customer_type' => 'company',
            'company_name' => 'Updated Company Name',
            'contact_person' => 'Amali Silva',
            'address' => 'Updated address',
            'mobile_number' => '+94 77 555 1234',
            'alternate_phone' => '',
            'email' => '',
            'special_notes' => 'Updated notes',
        ])->assertRedirect(route('customers.show', $customer));

        $customer->refresh();
        $this->assertSame($originalCode, $customer->customer_code);
        $this->assertSame('company', $customer->customer_type->value);
        $this->assertSame('Updated Company Name', $customer->display_name);
        $this->assertSame('75.00', $customer->opening_balance);
        $this->assertDatabaseCount('customer_account_entries', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.updated', 'entity_id' => $customer->id]);
    }

    public function test_customer_model_prevents_id_code_and_opening_balance_changes(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->registerCustomer($user, $this->validIndividual());

        $this->expectException(\LogicException::class);
        $customer->fill(['customer_code' => 'CUS-CHANGED', 'opening_balance' => '1.00'])->save();
    }

    public function test_deactivation_requires_permission_preserves_history_and_activation_uses_update_permission(): void
    {
        $cashier = $this->userWithRole('cashier');
        $customer = $this->registerCustomer($cashier, $this->validIndividual());

        $this->actingAs($cashier)->post(route('customers.deactivate', $customer))
            ->assertForbidden();
        $this->assertTrue($customer->fresh()->is_active);

        $administrator = $this->userWithRole('administrator');
        $this->actingAs($administrator)->post(route('customers.deactivate', $customer))
            ->assertRedirect();
        $this->assertFalse($customer->fresh()->is_active);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.deactivated', 'entity_id' => $customer->id]);

        $this->actingAs($administrator)->post(route('customers.activate', $customer))
            ->assertRedirect();
        $this->assertTrue($customer->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customer.activated', 'entity_id' => $customer->id]);
    }

    public function test_customer_statement_and_financial_fields_require_statement_permission(): void
    {
        $cashier = $this->userWithRole('cashier');
        DB::table('permission_user')->insert(['user_id' => $cashier->id, 'permission_id' => Permission::findByName('customers.statement')->id, 'model_type' => User::class, 'effect' => 'deny']);
        $customer = $this->registerCustomer($this->userWithRole('administrator'), $this->validIndividual([
            'opening_balance' => '42.00',
            'is_active' => '1',
        ]));

        $this->actingAs($cashier)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertDontSee('42.00');
        $this->actingAs($cashier)->get(route('customers.statement', $customer))
            ->assertForbidden();

        $manager = $this->userWithRole('cashier');
        $this->actingAs($manager)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('42.00');
        $this->actingAs($manager)->get(route('customers.statement', $customer))
            ->assertOk()
            ->assertSee($customer->customer_code);
    }

    public function test_unauthorized_registration_and_updates_are_blocked(): void
    {
        $user = User::factory()->create();
        $customer = $this->registerCustomer($this->userWithRole('administrator'), $this->validIndividual());

        $this->actingAs($user)->get(route('customers.index'))->assertForbidden();
        $this->actingAs($user)->post(route('customers.store'), $this->validIndividual())->assertForbidden();
        $this->actingAs($user)->put(route('customers.update', $customer), $this->validIndividual())->assertForbidden();
        $this->assertDatabaseCount('customers', 1);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function registerCustomer(User $actor, array $data): Customer
    {
        $response = $this->actingAs($actor)->post(route('customers.store'), $data);
        $response->assertRedirect()->assertSessionHasNoErrors();

        return Customer::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validIndividual(array $overrides = []): array
    {
        return array_replace([
            'customer_type' => 'individual',
            'customer_name' => 'Nalaka Perera',
            'company_name' => '',
            'contact_person' => '',
            'address' => '12 Main Street, Colombo',
            'mobile_number' => '0771234567',
            'alternate_phone' => '',
            'email' => 'nalaka@example.test',
            'special_notes' => 'Call before delivery.',
            'registration_date' => now()->toDateString(),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validCompany(array $overrides = []): array
    {
        return array_replace([
            'customer_type' => 'company',
            'customer_name' => '',
            'company_name' => 'Lanka Motors (Pvt) Ltd',
            'contact_person' => 'Kasun Fernando',
            'address' => '45 Industrial Road, Colombo',
            'mobile_number' => '+94 77 123 4567',
            'alternate_phone' => '+94 11 234 5678',
            'email' => 'service@lankamotors.example',
            'special_notes' => '',
            'registration_date' => now()->toDateString(),
        ], $overrides);
    }
}
