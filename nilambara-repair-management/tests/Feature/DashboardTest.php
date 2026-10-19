<?php

namespace Tests\Feature;

use App\Models\User;
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
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome to Nilambara Electricals Repair Shop Management System')
            ->assertSee('Total Repair Jobs')
            ->assertSee('Pending Repairs')
            ->assertSee('Completed Repairs')
            ->assertSee("Today's Sales")
            ->assertSee('Outstanding Credit')
            ->assertSee('Low Stock Items')
            ->assertSee($user->name);
    }

    public function test_business_modules_do_not_have_working_crud_endpoints(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            'customers', 'repair-receipts', 'repair-jobs', 'job-cards',
            'quotations', 'job-status', 'invoices', 'payments',
            'credit-management', 'inventory', 'suppliers', 'purchases',
            'pos', 'employees', 'roles-permissions', 'reports',
            'backup-restore', 'settings', 'audit-logs', 'print-templates',
        ] as $module) {
            $this->get('/'.$module)->assertNotFound();
            $this->post('/'.$module)->assertNotFound();
        }
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
