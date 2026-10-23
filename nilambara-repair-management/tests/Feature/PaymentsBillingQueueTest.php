<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->get(route('repair-jobs.billing', $cancelled))->assertOk()->assertSee('Add Custom Charge');
        $this->post(route('repair-jobs.billing.update', $cancelled), ['labour_charge' => '50.00', 'idempotency_key' => (string) Str::uuid(), 'charges' => [['description' => 'Inspection fee', 'quantity' => '1', 'unit_price' => '25.00']]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('75.00', $cancelled->fresh()->total_job_amount);

        $this->get(route('invoices.index'))->assertOk()->assertSee('Saved bills ready to invoice')->assertSee($cancelled->item_name);

        $this->post(route('invoices.store'), ['customer_id' => $cancelled->customer_id, 'repair_job_ids' => [$cancelled->id], 'invoice_date' => now()->toDateString(), 'payment_terms' => 'cash', 'payment_method' => 'cash', 'intent' => 'issue'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('75.00', Invoice::sole()->total);
        $this->get(route('payments.index'))->assertDontSee($cancelled->item_name);
    }
}
