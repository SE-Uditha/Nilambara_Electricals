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

    public function test_common_item_names_are_normalized_applied_and_can_be_cleared(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $base = ['name' => 'Nilambara', 'currency' => 'LKR', 'timezone' => 'Asia/Colombo', 'date_format' => 'd M Y', 'time_format' => 'h:i A'];

        $this->actingAs($admin)->put(route('settings.update'), $base + [
            'common_item_names' => "  Table   fan \r\n\r\nWater pump\nTABLE FAN\nවිදුලි මෝටරය",
        ])->assertSessionHasNoErrors();

        $expected = "Table fan\nWater pump\nවිදුලි මෝටරය";
        $this->assertSame($expected, SystemSetting::query()->where('key', 'common_item_names')->sole()->value);
        $this->assertSame($expected, config('business.common_item_names'));
        $this->get(route('settings.index'))->assertOk()->assertSee('name="common_item_names"', false);
        $this->get(route('repair-jobs.create'))->assertOk()
            ->assertSee('<option value="Table fan">', false)
            ->assertSee('<option value="Water pump">', false)
            ->assertSee('විදුලි මෝටරය');

        $this->put(route('settings.update'), $base + ['common_item_names' => ''])->assertSessionHasNoErrors();
        $this->assertNull(SystemSetting::query()->where('key', 'common_item_names')->sole()->value);
        $this->assertNull(config('business.common_item_names'));
        $this->get(route('repair-jobs.create'))->assertOk()->assertDontSee('<option value="Table fan">', false);
    }

    public function test_common_item_names_allow_one_hundred_full_length_names_and_reject_larger_lists(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $base = ['name' => 'Nilambara', 'currency' => 'LKR', 'timezone' => 'Asia/Colombo', 'date_format' => 'd M Y', 'time_format' => 'h:i A'];
        $names = implode("\n", array_map(fn (int $number): string => str_pad((string) $number, 255, 'x'), range(1, 100)));

        $this->actingAs($admin)->put(route('settings.update'), $base + ['common_item_names' => $names])->assertSessionHasNoErrors();
        $this->assertSame($names, SystemSetting::query()->where('key', 'common_item_names')->sole()->value);

        $this->put(route('settings.update'), $base + ['common_item_names' => str_repeat('x', 256)])->assertSessionHasErrors('common_item_names');
        $this->put(route('settings.update'), $base + ['common_item_names' => implode("\n", array_map(fn (int $number): string => 'Item '.$number, range(1, 101)))])->assertSessionHasErrors('common_item_names');
        $this->assertSame($names, SystemSetting::query()->where('key', 'common_item_names')->sole()->value);
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
