<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PrintTemplates\Models\PrintTemplate;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_template_edits_create_new_active_versions_and_can_reset_to_original(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrator');
        $template = PrintTemplate::query()->create([
            'code' => 'invoice-standard',
            'version' => 1,
            'document_type' => 'invoice',
            'name' => 'Standard invoice',
            'paper_size' => 'A5',
            'orientation' => 'portrait',
            'font_size_pt' => 10,
            'margin_top_mm' => 5,
            'margin_right_mm' => 5,
            'margin_bottom_mm' => 5,
            'margin_left_mm' => 5,
            'duplicate_copy_label' => 'Duplicate Copy',
            'is_active' => true,
        ]);
        $this->actingAs($user);

        $this->get(route('print-templates.preview', $template))->assertOk()->assertSee(__('print_templates.sample_preview'));
        $this->put(route('print-templates.update', $template), [
            'name' => 'Updated invoice',
            'business_name' => 'Nilambara Electricals',
            'business_address' => '',
            'telephone_number' => '',
            'document_title' => 'Invoice',
            'paper_size' => 'A5',
            'orientation' => 'landscape',
            'thermal_width_mm' => '',
            'font_size_pt' => 11,
            'margin_top_mm' => 6,
            'margin_right_mm' => 6,
            'margin_bottom_mm' => 6,
            'margin_left_mm' => 6,
            'footer' => 'Thank you',
            'duplicate_copy_label' => 'Copy',
        ])->assertRedirect(route('print-templates.index'));

        $versionTwo = PrintTemplate::query()->where('code', $template->code)->where('version', 2)->firstOrFail();
        $this->assertTrue($versionTwo->is_active);
        $this->assertFalse($template->fresh()->is_active);
        $this->post(route('print-templates.reset', $versionTwo))->assertRedirect(route('print-templates.index'));

        $versionThree = PrintTemplate::query()->where('code', $template->code)->where('version', 3)->firstOrFail();
        $this->assertTrue($versionThree->is_active);
        $this->assertSame('Standard invoice', $versionThree->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'print_template.reset']);
    }

    public function test_paper_size_validation_matches_quotation_renderer_support(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrator');
        $template = PrintTemplate::query()->create([
            'code' => 'quotation-standard',
            'version' => 1,
            'document_type' => 'quotation',
            'name' => 'Standard quotation',
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'font_size_pt' => 10,
            'margin_top_mm' => 5,
            'margin_right_mm' => 5,
            'margin_bottom_mm' => 5,
            'margin_left_mm' => 5,
            'duplicate_copy_label' => 'Duplicate Copy',
            'is_active' => true,
        ]);

        $this->actingAs($user)->put(route('print-templates.update', $template), [
            'name' => 'Standard quotation',
            'business_name' => '',
            'business_address' => '',
            'telephone_number' => '',
            'document_title' => '',
            'paper_size' => 'A6',
            'orientation' => 'portrait',
            'thermal_width_mm' => '',
            'font_size_pt' => 10,
            'margin_top_mm' => 5,
            'margin_right_mm' => 5,
            'margin_bottom_mm' => 5,
            'margin_left_mm' => 5,
            'footer' => '',
            'duplicate_copy_label' => 'Duplicate Copy',
        ])->assertSessionHasErrors('paper_size');

        $this->assertSame(1, PrintTemplate::query()->where('code', $template->code)->count());
    }
}
