<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\Settings\Models\SystemSetting;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_update_safe_business_settings_and_values_apply_on_requests(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $this->actingAs($user)->put(route('settings.update'), [
            'name' => 'Updated Electricals',
            'address' => 'Main Street',
            'contact_number' => '0112345678',
            'currency' => 'LKR',
            'timezone' => 'Asia/Colombo',
            'date_format' => 'd M Y',
            'time_format' => 'h:i A',
        ])->assertRedirect();

        $this->assertSame('Updated Electricals', SystemSetting::query()->where('key', 'name')->firstOrFail()->value);
        $this->assertSame('Updated Electricals', config('business.name'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.business.updated']);
        $this->get(route('settings.index'))->assertOk()->assertSee('Updated Electricals');
    }

    public function test_common_faults_are_saved_normalized_and_offered_on_new_repair_job(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $base = ['name' => 'Nilambara', 'currency' => 'LKR', 'timezone' => 'Asia/Colombo', 'date_format' => 'd M Y', 'time_format' => 'h:i A'];

        $this->actingAs($admin)->put(route('settings.update'), $base + [
            'common_faults' => "  Screen not turning on \r\n\r\nNo power\nno POWER\nOverheating",
            'common_physical_conditions' => "Good condition\nScratched body",
            'common_visible_damages' => 'Cracked screen',
        ])->assertSessionHasNoErrors();
        $this->assertSame("Screen not turning on\nNo power\nOverheating", SystemSetting::query()->where('key', 'common_faults')->firstOrFail()->value);

        $this->get(route('repair-jobs.create'))->assertOk()
            ->assertSee('<option value="Screen not turning on">', false)
            ->assertSee('<option value="Overheating">', false)
            ->assertSee('<option value="Scratched body">', false)
            ->assertSee('<option value="Cracked screen">', false)
            ->assertSee('name="reported_fault"', false);
        $this->get(route('settings.index'))->assertOk()->assertSee('name="common_visible_damages"', false);

        $this->put(route('settings.update'), $base + ['common_faults' => str_repeat('x', 151)])->assertSessionHasErrors('common_faults');
        $this->put(route('settings.update'), $base + ['common_visible_damages' => str_repeat('x', 151)])->assertSessionHasErrors('common_visible_damages');
    }

    public function test_business_settings_reject_unknown_and_secret_fields(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrator');

        $this->actingAs($user)->put(route('settings.update'), [
            'name' => 'Nilambara',
            'address' => '',
            'contact_number' => '',
            'currency' => 'LKR',
            'timezone' => 'Asia/Colombo',
            'date_format' => 'd M Y',
            'time_format' => 'h:i A',
            'database_password' => 'do-not-store',
        ])->assertRedirect();

        $this->assertDatabaseMissing('system_settings', ['key' => 'database_password']);
    }

    public function test_administrator_can_browse_redacted_audit_history(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrator');
        AuditLog::query()->create([
            'user_id' => $user->getKey(),
            'action' => 'test.redaction',
            'entity_type' => 'user',
            'entity_id' => $user->getKey(),
            'occurred_at' => now(),
            'new_values' => ['password' => 'hidden', 'display_name' => 'Visible'],
        ]);

        $this->actingAs($user)->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('test.redaction')
            ->assertSee('[REDACTED]')
            ->assertDontSee('&quot;password&quot;: &quot;hidden&quot;');
    }
}
