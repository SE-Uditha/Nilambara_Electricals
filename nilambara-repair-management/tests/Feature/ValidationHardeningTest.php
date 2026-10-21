<?php

namespace Tests\Feature;

use App\Models\User;
use App\Rules\DecimalValue;
use App\Rules\PhoneNumber;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class ValidationHardeningTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    private function administrator(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrator');
        $this->actingAs($user);

        return $user;
    }

    public function test_phone_rule_accepts_display_formats_and_rejects_malformed_values(): void
    {
        foreach (['0771234567', '+94 77 123 4567', '011-234-5678', '+1 (212) 555-0123'] as $value) {
            $this->assertTrue(Validator::make(['phone' => $value], ['phone' => [new PhoneNumber]])->passes(), $value);
        }
        foreach (['-------', '1------2', '077abc1234', '123456', '1234567890123456', '++94771234567', "0771234567\n", ['0771234567']] as $value) {
            $validator = Validator::make(['phone' => $value], ['phone' => [new PhoneNumber]]);
            $this->assertTrue($validator->fails());
            $this->assertStringContainsString('7 to 15 digits', $validator->errors()->first('phone'));
        }
    }

    public function test_money_validation_preserves_exact_decimal_boundaries(): void
    {
        foreach (['99999999999999999.99', '-99999999999999999.99', '0', '0.01', '125.5'] as $value) {
            $this->assertTrue(Validator::make(['amount' => $value], ['amount' => [new DecimalValue]])->passes(), $value);
        }
        foreach (['100000000000000000.00', '-100000000000000000.00', '1.001', '1e3', 'NaN', ['12.00']] as $value) {
            $this->assertTrue(Validator::make(['amount' => $value], ['amount' => [new DecimalValue]])->fails());
        }
    }

    public function test_invalid_customer_input_returns_errors_without_creating_records(): void
    {
        $this->administrator();
        $this->postJson(route('customers.store'), [
            'customer_type' => 'individual', 'customer_name' => '   ', 'address' => 'Colombo',
            'mobile_number' => '1------2', 'opening_balance' => '100000000000000000.00',
            'registration_date' => now()->addDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['customer_name', 'mobile_number', 'opening_balance', 'registration_date']);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('customer_account_entries', 0);
    }

    public function test_supplier_validation_checks_both_phone_fields_and_preserves_input(): void
    {
        $this->administrator();
        $this->from(route('suppliers.create'))->post(route('suppliers.store'), [
            'name' => 'Test supplier', 'address' => 'Colombo', 'mobile_number' => 'bad phone',
            'telephone_number' => '-------', 'email' => 'invalid-email', 'opening_balance' => '0.00',
            'credit_days' => '-1', 'is_active' => '1',
        ])->assertRedirect(route('suppliers.create'))->assertSessionHasErrors(['mobile_number', 'telephone_number', 'email', 'credit_days'])->assertSessionHasInput('name', 'Test supplier');
        $this->assertDatabaseCount('suppliers', 0);
        $this->get(route('suppliers.create'))->assertOk()->assertSee('aria-invalid')->assertSee('type="email"', false)->assertSee('max="3650"', false);
    }

    public function test_array_ids_are_validation_errors_instead_of_server_errors(): void
    {
        $this->administrator();
        $job = $this->job();
        $this->postJson(route('repair-jobs.assign', $job), ['technician_id' => [1, 2]])
            ->assertUnprocessable()->assertJsonValidationErrors('technician_id');
        $this->postJson(route('payments.store'), [
            'payment_type' => 'advance', 'customer_id' => $job->customer_id,
            'received_by_employee_id' => [1, 2], 'payment_date' => now()->toDateString(),
            'amount' => '100.00', 'method' => 'cash',
        ])->assertUnprocessable()->assertJsonValidationErrors('received_by_employee_id');
        $this->postJson(route('repair-receipts.store'), [
            'customer_id' => [$job->customer_id], 'received_by_employee_id' => [$job->received_by_employee_id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['customer_id', 'received_by_employee_id']);
        $this->assertDatabaseCount('payments', 0);
        $this->assertNull($job->fresh()->technician_id);
    }
}
