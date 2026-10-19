<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_accounts_are_provisioned_with_normalized_email_and_hashed_password(): void
    {
        $password = 'private-password-123';

        $this->artisan('app:create-user')
            ->expectsQuestion('Name', '  First Employee  ')
            ->expectsQuestion('Email', '  EMPLOYEE@example.test  ')
            ->expectsQuestion('Password (minimum 12 characters)', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutput('Account created for employee@example.test.')
            ->assertSuccessful();

        $user = User::sole();

        $this->assertSame('First Employee', $user->name);
        $this->assertSame('employee@example.test', $user->email);
        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_duplicate_email_addresses_are_rejected_before_creating_an_account(): void
    {
        User::factory()->create(['email' => 'employee@example.test']);

        $this->artisan('app:create-user')
            ->expectsQuestion('Name', 'Duplicate Employee')
            ->expectsQuestion('Email', 'EMPLOYEE@example.test')
            ->expectsQuestion('Password (minimum 12 characters)', 'private-password-123')
            ->expectsQuestion('Confirm password', 'private-password-123')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_unconfirmed_passwords_are_rejected(): void
    {
        $this->artisan('app:create-user')
            ->expectsQuestion('Name', 'First Employee')
            ->expectsQuestion('Email', 'employee@example.test')
            ->expectsQuestion('Password (minimum 12 characters)', 'private-password-123')
            ->expectsQuestion('Confirm password', 'a-different-password')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_passwords_shorter_than_twelve_characters_are_rejected(): void
    {
        $this->artisan('app:create-user')
            ->expectsQuestion('Name', 'First Employee')
            ->expectsQuestion('Email', 'employee@example.test')
            ->expectsQuestion('Password (minimum 12 characters)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_identity_fields_are_rejected(): void
    {
        $this->artisan('app:create-user')
            ->expectsQuestion('Name', '  ')
            ->expectsQuestion('Email', 'invalid-email')
            ->expectsQuestion('Password (minimum 12 characters)', 'private-password-123')
            ->expectsQuestion('Confirm password', 'private-password-123')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_default_seeder_does_not_create_demo_accounts(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
    }
}
