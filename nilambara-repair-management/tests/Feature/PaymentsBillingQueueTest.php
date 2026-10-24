<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;
use App\Support\SidebarNavigation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class PaymentsBillingQueueTest extends TestCase
{
    use CreatesDatabaseRecords, RefreshDatabase;

    public function test_finished_jobs_queue_in_payments_get_billed_and_show_in_invoices(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->employee(['user_id' => $user->id]);
        $this->actingAs($user);

        $queued = [];
        foreach (JobStatus::billable() as $status) {
            $queued[$status->value] = $this->job(null, ['status' => $status->value, 'item_name' => 'Item '.$status->name, 'cancelled_at' => $status === JobStatus::Cancelled ? now() : null]);
        }
        $other = $this->job(null, ['status' => JobStatus::UnderRepair->value, 'item_name' => 'Still repairing']);

        $page = $this->get(route('payments.index'))->assertOk()->assertSee('Jobs awaiting billing');
        foreach ($queued as $job) {
            $page->assertSee($job->item_name);
        }
        $page->assertDontSee('Still repairing');

        $cancelled = $queued[JobStatus::Cancelled->value];
        $this->get(route('repair-jobs.billing', $cancelled))->assertOk()->assertSee('Base labour amount')->assertDontSee('Add Custom Charge')->assertDontSee('Add Spare Part')->assertDontSee('Spare parts already used');
        $this->post(route('repair-jobs.billing.update', $cancelled), ['labour_charge' => '75.00', 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('75.00', $cancelled->fresh()->total_job_amount);

        $this->get(route('invoices.index'))->assertOk()->assertSee('Saved bills ready to invoice')->assertSee($cancelled->item_name);

        $this->post(route('invoices.store'), ['customer_id' => $cancelled->customer_id, 'repair_job_ids' => [$cancelled->id], 'invoice_date' => now()->toDateString(), 'payment_terms' => 'cash', 'payment_method' => 'cash', 'intent' => 'issue', 'amount_received' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('75.00', Invoice::sole()->total);
        $this->get(route('payments.index'))->assertDontSee($cancelled->item_name);
    }

    public function test_prepare_bill_highlights_payments_menu_and_not_repair_jobs(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->employee(['user_id' => $user->id]);
        $job = $this->job(null, ['status' => JobStatus::RepairCompleted->value]);

        $this->actingAs($user);
        $request = Request::create(route('repair-jobs.billing', $job));
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));
        $items = collect(app(SidebarNavigation::class)->sections($user, $request))->pluck('items')->flatten(1);

        $this->assertTrue($items->firstWhere('label', 'payments')['active']);
        $this->assertFalse($items->firstWhere('label', 'repair_jobs')['active']);
        $this->assertTrue(collect($items->firstWhere('label', 'payments')['children'])->firstWhere('label', 'payments')['active']);
    }

    public function test_record_payment_lists_outstanding_for_each_invoice(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->employee(['user_id' => $user->id]);
        $job = $this->job(null, ['status' => JobStatus::RepairCompleted->value, 'labour_charge' => '100.00', 'parts_charge' => '0.00', 'total_job_amount' => '100.00', 'brand' => 'B', 'repair_description' => 'x']);
        $this->actingAs($user)->post(route('invoices.store'), ['customer_id' => $job->customer_id, 'repair_job_ids' => [$job->id], 'invoice_date' => now()->toDateString(), 'payment_method' => 'cash', 'intent' => 'issue', 'amount_received' => '40'])
            ->assertSessionHasNoErrors();

        $this->get(route('payments.create'))->assertOk()
            ->assertSee('data-outstanding="60.00"', false)
            ->assertSee('id="invoice-balance"', false);
    }

    public function test_cancel_and_payments_button_creates_a_cancelled_job_and_opens_payments(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $employee = $this->employee(['user_id' => $user->id]);
        $this->actingAs($user);
        $customer = $this->customer($this->customerAttributes());
        $payload = [
            'customer_id' => $customer->id, 'job_number' => 'INSP-2', 'job_type' => 'normal', 'item_name' => 'Fan', 'brand' => 'Orient',
            'received_by_employee_id' => $employee->id, 'advance_payment' => '0.00', 'intent' => 'cancel_payments',
        ];

        $this->post(route('repair-jobs.store'), $payload + ['labor_cost' => '0'])->assertSessionHasErrors('labor_cost');

        $this->post(route('repair-jobs.store'), $payload + ['labor_cost' => '500.00'])->assertRedirect(route('payments.index'))->assertSessionHas('status', 'Repair job cancelled and receipt generated.');
        $job = \App\Modules\RepairJobs\Models\RepairJob::sole();
        $this->assertSame(JobStatus::Cancelled, $job->status);
        $this->assertNotNull($job->cancelled_at);
        $this->assertSame('500.00', $job->total_job_amount);
        $this->get(route('payments.index'))->assertSee('Fan')->assertSee('500.00');
    }

    public function test_intake_inspection_charge_on_a_declined_repair_reaches_payments_and_the_invoice(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $employee = $this->employee(['user_id' => $user->id]);
        $this->actingAs($user);
        $customer = $this->customer($this->customerAttributes());

        $this->post(route('repair-jobs.store'), [
            'customer_id' => $customer->id, 'job_number' => 'INSP-1', 'job_type' => 'normal', 'item_name' => 'Grinder', 'brand' => 'Honda',
            'received_by_employee_id' => $employee->id, 'labor_cost' => '750.00', 'advance_payment' => '0.00',
        ])->assertSessionHasNoErrors();
        $job = \App\Modules\RepairJobs\Models\RepairJob::sole();
        $this->assertSame('750.00', $job->total_job_amount);

        $job->forceFill(['status' => JobStatus::Cancelled->value, 'cancelled_at' => now()])->save();
        $this->get(route('payments.index'))->assertOk()->assertSee('Grinder')->assertSee('750.00');
        $this->get(route('repair-jobs.billing', $job))->assertOk()->assertSee('750.00');

        $this->post(route('invoices.store'), ['customer_id' => $customer->id, 'repair_job_ids' => [$job->id], 'invoice_date' => now()->toDateString(), 'payment_terms' => 'cash', 'payment_method' => 'cash', 'intent' => 'issue', 'amount_received' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $invoice = Invoice::sole();
        $this->assertSame('750.00', $invoice->total);
        $this->get(route('invoices.print', $invoice))->assertOk()->assertSee('750.00');
    }
}
