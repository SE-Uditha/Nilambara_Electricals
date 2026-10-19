<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_destination_is_disabled_until_private_mysql_tools_are_configured(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $this->actingAs($user)->get(route('backups.index'))
            ->assertOk()
            ->assertSee(__('backups.destination_unavailable'));
        $this->post(route('backups.create'))->assertRedirect()->assertSessionHasErrors('backup');
        $this->assertDatabaseCount('backup_histories', 0);
    }

    public function test_database_restore_is_not_available_over_http(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $this->actingAs($user)->post('/backups/1/restore')->assertNotFound();
    }
}
