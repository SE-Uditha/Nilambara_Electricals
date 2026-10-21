<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Reports\Services\XlsxReportExporter;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_every_report_with_empty_business_data(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrator');
        $this->actingAs($user);

        foreach ([
            'repair', 'technicians', 'sales', 'collections', 'advances', 'customer-credit',
            'overdue-credit', 'supplier-outstanding', 'purchases', 'expenses', 'stock',
            'stock-movements', 'repair-parts', 'adjustments', 'purchase-history',
            'supplier-purchases', 'valuation', 'profit-estimate',
        ] as $report) {
            $this->get(route('reports.show', $report))->assertOk();
        }
    }

    public function test_administrator_can_export_reports_as_xlsx_pdf_and_csv(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrator');
        $this->actingAs($user);

        $this->get(route('reports.export', ['report' => 'sales', 'format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->get(route('reports.export', ['report' => 'repair', 'format' => 'pdf']))->assertOk();
        $this->get(route('reports.export', ['report' => 'repair', 'format' => 'csv']))->assertOk();
    }

    public function test_xlsx_export_is_a_valid_excel_workbook_with_inline_text_cells(): void
    {
        $path = app(XlsxReportExporter::class)->create(
            DB::query()->selectRaw('? as label', ['=not-a-formula']),
            [['field' => 'label', 'label' => 'Test value']],
        );
        $archive = new \ZipArchive;

        $this->assertTrue($archive->open($path) === true);
        $this->assertNotFalse($archive->getFromName('[Content_Types].xml'));
        $worksheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $this->assertIsString($worksheet);
        $this->assertStringContainsString('t="inlineStr"', $worksheet);
        $this->assertStringContainsString('=not-a-formula', $worksheet);
        $archive->close();
        unlink($path);
    }

    public function test_cashier_cannot_view_financial_reports_or_export_reports(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user);

        $this->get(route('reports.show', 'sales'))->assertForbidden();
        $this->get(route('reports.export', ['report' => 'sales', 'format' => 'xlsx']))->assertForbidden();
    }
}
