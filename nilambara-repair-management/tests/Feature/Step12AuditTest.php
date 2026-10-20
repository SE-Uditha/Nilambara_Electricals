<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Reports\Services\ReportDataService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class Step12AuditTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    private function administrator(): User
    {
        $this->seed(RoleSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole('administrator');
        $this->employee(['user_id' => $actor->id, 'is_active' => true]);

        return $actor;
    }

    public function test_customer_search_treats_sql_payload_as_data_and_escapes_stored_html(): void
    {
        $actor = $this->administrator();
        $customer = $this->customer(['customer_name' => '<script>alert("step12")</script>']);
        $this->actingAs($actor)->get(route('customers.index', ['q' => "' OR 1=1 --"]))
            ->assertOk()->assertViewHas('customers', fn ($rows) => $rows->total() === 0);
        $this->get(route('customers.show', $customer))->assertOk()
            ->assertDontSee('<script>alert("step12")</script>', false)
            ->assertSee(e($customer->customer_name), false);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_all_report_queries_execute_with_linked_fixture_and_record_local_timings(): void
    {
        $actor = $this->administrator();
        $customer = $this->customer();
        for ($i = 0; $i < 25; $i++) {
            $this->job($customer);
        }
        $reports = [
            'repair', 'technicians', 'sales', 'collections', 'advances', 'customer-credit',
            'overdue-credit', 'supplier-outstanding', 'purchases', 'expenses', 'stock',
            'stock-movements', 'repair-parts', 'pos-parts', 'adjustments', 'purchase-history',
            'supplier-purchases', 'valuation', 'profit-estimate', 'daily-sales', 'monthly-sales',
            'yearly-sales', 'repair-income', 'invoice-sales', 'cash-credit-sales', 'cashier-sales',
            'discounts', 'refunds', 'cash-summary', 'cash-collections', 'customer-statement', 'supplier-statement',
        ];
        $timings = [];
        foreach ($reports as $report) {
            $start = hrtime(true);
            $data = app(ReportDataService::class)->build($report, [], $actor);
            $rows = $data['query']->limit(50)->get();
            $timings[$report] = round((hrtime(true) - $start) / 1e6, 3);
            $this->assertNotEmpty($data['columns'], $report);
            if ($report === 'repair') {
                $this->assertCount(25, $rows);
            }
        }
        $this->actingAs($actor);
        foreach (['dashboard' => [], 'customers.index' => ['q' => 'Database'], 'repair-jobs.index' => ['q' => 'Test']] as $route => $parameters) {
            $start = hrtime(true);
            $this->get(route($route, $parameters))->assertOk();
            $timings[$route] = round((hrtime(true) - $start) / 1e6, 3);
        }
        file_put_contents(storage_path('logs/step12-local-timings.json'), json_encode([
            'driver' => config('database.default'), 'fixture_customers' => 1, 'fixture_jobs' => 25,
            'users' => 1, 'iterations' => 1, 'includes_http_rendering' => ['dashboard', 'customers.index', 'repair-jobs.index'],
            'milliseconds' => $timings,
            'limitations' => 'Single local run; empty financial and supplier datasets; not a capacity benchmark.',
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
