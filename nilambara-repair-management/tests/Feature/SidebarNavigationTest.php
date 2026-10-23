<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function sidebar(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);

        return new \DOMXPath($document);
    }

    public function test_cashier_sees_permitted_dropdowns_and_no_empty_management_groups(): void
    {
        $response = $this->actingAs($this->actor('cashier'))->get(route('dashboard'))->assertOk();
        $sidebar = $this->sidebar($response->getContent());
        foreach (['customers', 'repair_jobs', 'invoices', 'payments', 'suppliers', 'purchases', 'inventory'] as $group) {
            $this->assertSame(1, $sidebar->query('//nav//details[@data-sidebar-group="'.$group.'"]')->length);
        }
        foreach (['repair_receipts', 'pos', 'employees', 'roles_permissions', 'system_settings'] as $group) {
            $this->assertSame(0, $sidebar->query('//nav//details[@data-sidebar-group="'.$group.'"]')->length);
        }
        $this->assertSame(0, $sidebar->query('//nav//details[not(.//a)]')->length);
        $this->assertSame(1, $sidebar->query('//nav//a[@href="'.route('repair-jobs.index').'"]')->length);
        $this->assertSame(0, $sidebar->query('//nav//a[@href="'.route('repair-receipts.index').'"]')->length);
    }

    public function test_repair_jobs_stays_selected_when_accepting_a_job(): void
    {
        $response = $this->actingAs($this->actor('cashier'))->get(route('repair-jobs.create'))->assertOk();
        $selected = $this->sidebar($response->getContent())->query('//nav//a[@aria-current="page"]');
        $this->assertSame(1, $selected->length);
        $this->assertSame(route('repair-jobs.create'), $selected->item(0)->getAttribute('href'));
        $this->assertSame(1, $this->sidebar($response->getContent())->query('//nav//details[@data-sidebar-group="repair_jobs"][@open]')->length);
    }

    public function test_filtered_invoice_marks_only_its_shortcut_active_and_opens_the_parent(): void
    {
        $response = $this->actingAs($this->actor('cashier'))->get(route('invoices.index', ['status' => 'draft']))->assertOk();
        $sidebar = $this->sidebar($response->getContent());
        $selected = $sidebar->query('//nav//a[@aria-current="page"]');
        $this->assertSame(1, $selected->length);
        $this->assertSame(route('invoices.index', ['status' => 'draft']), $selected->item(0)->getAttribute('href'));
        $this->assertSame(1, $sidebar->query('//nav//details[@data-sidebar-group="invoices"][@open]')->length);
    }

    public function test_report_path_parameter_selects_the_correct_report_subtopic(): void
    {
        $response = $this->actingAs($this->actor('administrator'))->get(route('reports.show', ['report' => 'stock']))->assertOk();
        $sidebar = $this->sidebar($response->getContent());
        $selected = $sidebar->query('//nav//a[@aria-current="page"]');
        $this->assertSame(1, $selected->length);
        $this->assertSame(route('reports.show', ['report' => 'stock']), $selected->item(0)->getAttribute('href'));
        $this->assertSame(1, $sidebar->query('//nav//details[@data-sidebar-group="reports"][@open]')->length);
    }
}
