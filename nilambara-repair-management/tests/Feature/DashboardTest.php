<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_dashboard_without_email_verification(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['email_verified_at' => null]);
        $user->assignRole('administrator');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.title'))
            ->assertSee(__('dashboard.received_jobs'))
            ->assertSee(__('dashboard.active_jobs'))
            ->assertSee(__('dashboard.repair_sales'))
            ->assertSee(__('dashboard.outstanding_credit'))
            ->assertSee(__('dashboard.low_stock_alerts'))
            ->assertSee($user->name);
    }

    public function test_unimplemented_business_modules_do_not_have_working_crud_endpoints(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            'credit-management',
            'roles-permissions',
            'backup-restore',
        ] as $module) {
            $this->get('/'.$module)->assertNotFound();
            $this->post('/'.$module)->assertNotFound();
        }
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_reports_require_the_reports_view_permission(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('receptionist');

        $this->actingAs($user)->get('/reports')->assertOk();
        $this->get(route('reports.show', 'sales'))->assertForbidden();
    }
}
