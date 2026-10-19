<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_bootstrap_creates_a_linked_administrator_with_a_hashed_password(): void
    {
        $this->seed(RoleSeeder::class);
        $password = 'Private-Password-123';

        $this->artisan('app:create-user')
            ->expectsQuestion('Name', '  First Employee  ')
            ->expectsQuestion('Username', '  FIRST.employee  ')
            ->expectsQuestion('Email (optional)', '  EMPLOYEE@example.test  ')
            ->expectsQuestion('Password (minimum 12 characters)', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutput('Initial administrator account created for first.employee.')
            ->assertSuccessful();

        $user = User::sole();

        $this->assertSame('First Employee', $user->name);
        $this->assertSame('employee@example.test', $user->email);
        $this->assertSame('first.employee', $user->username);
        $this->assertTrue($user->hasRole('administrator'));
        $this->assertNotNull($user->employee);
        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_duplicate_usernames_are_rejected_before_creating_an_account(): void
    {
        $this->seed(RoleSeeder::class);
        User::factory()->create(['username' => 'employee']);

        $this->artisan('app:create-user')
            ->expectsQuestion('Name', 'Duplicate Employee')
            ->expectsQuestion('Username', 'EMPLOYEE')
            ->expectsQuestion('Email (optional)', '')
            ->expectsQuestion('Password (minimum 12 characters)', 'Private-Password-123')
            ->expectsQuestion('Confirm password', 'Private-Password-123')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_unconfirmed_passwords_are_rejected(): void
    {
        $this->seed(RoleSeeder::class);
        $this->artisan('app:create-user')
            ->expectsQuestion('Name', 'First Employee')
            ->expectsQuestion('Username', 'first.employee')
            ->expectsQuestion('Email (optional)', '')
            ->expectsQuestion('Password (minimum 12 characters)', 'Private-Password-123')
            ->expectsQuestion('Confirm password', 'a-different-password')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_passwords_shorter_than_twelve_characters_are_rejected(): void
    {
        $this->seed(RoleSeeder::class);
        $this->artisan('app:create-user')
            ->expectsQuestion('Name', 'First Employee')
            ->expectsQuestion('Username', 'first.employee')
            ->expectsQuestion('Email (optional)', '')
            ->expectsQuestion('Password (minimum 12 characters)', 'short')
            ->expectsQuestion('Confirm password', 'short')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_identity_fields_are_rejected(): void
    {
        $this->seed(RoleSeeder::class);
        $this->artisan('app:create-user')
            ->expectsQuestion('Name', '  ')
            ->expectsQuestion('Username', 'x')
            ->expectsQuestion('Email (optional)', 'invalid-email')
            ->expectsQuestion('Password (minimum 12 characters)', 'Private-Password-123')
            ->expectsQuestion('Confirm password', 'Private-Password-123')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_default_seeder_does_not_create_demo_accounts(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
    }
}
