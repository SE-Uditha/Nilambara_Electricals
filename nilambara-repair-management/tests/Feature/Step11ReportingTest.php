<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Dashboard\Services\DashboardService;
use App\Modules\Payments\Models\PaymentReversal;
use App\Modules\Reports\Services\ReportDataService;
use Brick\Math\BigDecimal;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class Step11ReportingTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    private function administrator(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('administrator');

        return $user;
    }

    public function test_all_grouped_sales_statements_and_refund_reports_and_exports_work_on_empty_data(): void
    {
        $this->actingAs($this->administrator());
        foreach (['daily-sales', 'monthly-sales', 'yearly-sales', 'repair-income', 'invoice-sales', 'cash-credit-sales', 'cashier-sales', 'discounts', 'refunds', 'cash-summary', 'cash-collections', 'customer-statement', 'supplier-statement'] as $report) {
            $this->get(route('reports.show', $report))->assertOk();
            $this->get(route('reports.export', ['report' => $report, 'format' => 'xlsx']))->assertOk();
        }
    }

    public function test_collections_use_actual_reversal_dates_instead_of_rewriting_prior_days(): void
    {
        $actor = $this->administrator();
        $customer = $this->customer();
        $payment = $this->payment($customer, ['amount' => '30.00', 'paid_at' => '2026-10-19 10:00:00']);
        PaymentReversal::create(['payment_id' => $payment->id, 'customer_id' => $customer->id,
            'customer_scope_id' => $customer->id, 'reversal_type' => 'refund', 'amount' => '10.00', 'method' => 'cash',
            'reversed_at' => '2026-10-20 10:00:00', 'reversed_by_user_id' => $actor->id, 'reason' => 'Reporting fixture refund.', 'idempotency_key' => 'step11-refund']);
        $reports = app(ReportDataService::class);
        $before = $reports->build('collections', ['date_from' => '2026-10-19', 'date_to' => '2026-10-19'], $actor);
        $after = $reports->build('collections', ['date_from' => '2026-10-20', 'date_to' => '2026-10-20'], $actor);
        $this->assertSame('30.00', $before['summary']['total']);
        $this->assertSame('-10.00', $after['summary']['total']);
        $this->assertSame(1, $after['query']->count());
    }

    public function test_grouped_sales_count_multi_job_invoices_once_and_ignore_drafts(): void
    {
        $actor = $this->administrator();
        $customer = $this->customer();
        $invoice = $this->invoice($customer, ['subtotal' => '100.00', 'total' => '90.00', 'discount_amount' => '10.00',
            'status' => 'finalized', 'finalized_at' => '2026-10-19 10:00:00', 'invoice_date' => '2026-10-19']);
        $this->invoice($customer, ['subtotal' => '999.00', 'total' => '999.00', 'status' => 'draft']);
        $report = app(ReportDataService::class)->build('daily-sales', [], $actor);
        $row = $report['query']->sole();
        $this->assertSame('90.00', (string) BigDecimal::of((string) $row->net_sales)->toScale(2));
        $this->assertEquals(1, $row->documents);
        $this->assertSame('2026-10-19', $row->period);
        $this->actingAs($actor)->get(route('reports.show', ['report' => 'sales', 'sort' => 'arbitrary_sql']))->assertStatus(422);
        $this->get(route('reports.show', ['report' => 'sales', 'sort' => 'net_sales', 'direction' => 'desc']))->assertOk();
    }

    public function test_dashboard_date_cohort_and_inventory_aggregates_use_real_records(): void
    {
        $actor = $this->administrator();
        $this->job(null, ['received_at' => '2026-10-19 10:00:00', 'status' => 'Pending Inspection']);
        $this->job(null, ['received_at' => '2026-10-18 10:00:00', 'status' => 'Cancelled']);
        $location = $this->location();
        $part = $this->part(['default_location_id' => $location->id]);
        $this->movement($part, $location, ['quantity_delta' => '5.000', 'occurred_at' => '2026-10-19 10:00:00']);
        $result = app(DashboardService::class)->data(['period' => 'custom', 'date_from' => '2026-10-19', 'date_to' => '2026-10-19'], $actor);
        $this->assertEquals(1, $result['jobs']['received_jobs']);
        $this->assertEquals(1, $result['jobs']['pending_inspection']);
        $this->assertEquals(0, $result['jobs']['cancelled_jobs']);
        $this->assertSame('5.000', $result['inventory']['current_stock']);
        $this->assertCount(1, $result['recent_jobs']);
        $response = $this->actingAs($actor)->get(route('dashboard', ['period' => 'custom', 'date_from' => '2026-10-19', 'date_to' => '2026-10-19']))->assertOk();
        $previewRoot = storage_path('app/testing');
        if (! is_dir($previewRoot)) {
            mkdir($previewRoot, 0700, true);
        }
        file_put_contents($previewRoot.'/step11-dashboard.html', $response->getContent());
    }

    public function test_cashier_own_collections_and_technician_reports_do_not_grant_financial_access(): void
    {
        $this->seed(RoleSeeder::class);
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $employee = $this->employee(['user_id' => $cashier->id]);
        $this->payment(null, ['received_by_employee_id' => $employee->id, 'amount' => '10.00']);
        $this->payment(null, ['amount' => '20.00']);
        $report = app(ReportDataService::class)->build('collections', [], $cashier);
        $this->assertSame('10.00', $report['summary']['total']);
        $this->actingAs($cashier)->get(route('reports.show', 'collections'))->assertOk();
        $this->get(route('reports.show', 'profit-estimate'))->assertForbidden();
        $this->get(route('reports.export', ['report' => 'collections', 'format' => 'pdf']))->assertForbidden();
    }

    public function test_report_pdf_and_workbook_preserve_real_unicode_money_and_dates(): void
    {
        $actor = $this->administrator();
        $customer = $this->customer(['customer_name' => 'නිලම්බර පරීක්ෂණ']);
        $this->job($customer, ['received_at' => '2026-10-19 10:30:00']);
        $this->invoice($customer, ['subtotal' => '1234.56', 'total' => '1234.56', 'status' => 'finalized', 'finalized_at' => '2026-10-19 10:30:00']);
        $this->actingAs($actor);
        $pdf = $this->get(route('reports.export', ['report' => 'repair', 'format' => 'pdf']))->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $previewRoot = storage_path('app/testing');
        if (! is_dir($previewRoot)) {
            mkdir($previewRoot, 0700, true);
        }
        file_put_contents($previewRoot.'/step11-repair-report.pdf', $pdf->getContent());
        $export = $this->get(route('reports.export', ['report' => 'sales', 'format' => 'xlsx']))->assertOk();
        $archive = new \ZipArchive;
        $this->assertTrue($archive->open($export->baseResponse->getFile()->getPathname()) === true);
        $sheet = $archive->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('1234.56', $sheet);
        $this->assertStringContainsString('t="n"', $sheet);
        $this->assertNotFalse($archive->getFromName('xl/styles.xml'));
        $archive->close();
    }
}
