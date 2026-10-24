<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Approvals\Models\ApprovalRequest;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EditApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase, \Tests\Feature\Database\CreatesDatabaseRecords;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function customerPayload(array $overrides = []): array
    {
        return $overrides + [
            'customer_type' => 'individual', 'customer_name' => 'Edited Name', 'address' => 'New address',
            'mobile_number' => '0771234567', 'reason' => 'Customer corrected the name',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function test_cashier_cannot_edit_directly_but_can_request_customer_edit_and_admin_approval_applies_it(): void
    {
        $cashier = $this->userWithRole('cashier');
        $admin = $this->userWithRole('administrator');
        $customer = $this->job()->customer;
        $originalName = $customer->display_name;

        $this->actingAs($cashier)->get(route('customers.edit', $customer))->assertForbidden();
        $this->put(route('customers.update', $customer), $this->customerPayload())->assertForbidden();
        $this->get(route('customers.show', $customer))->assertOk()->assertSee('Request edit');

        $this->get(route('customers.request-edit', $customer))->assertOk();
        $payload = $this->customerPayload();
        $this->post(route('customers.request-edit.store', $customer), $payload)->assertRedirect(route('customers.show', $customer));
        $this->post(route('customers.request-edit.store', $customer), $payload)->assertRedirect();
        $this->assertSame(1, ApprovalRequest::where('kind', 'customer_update')->count());
        $this->assertSame($originalName, $customer->fresh()->display_name);

        $approval = ApprovalRequest::firstOrFail();
        $this->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Approved fine'])->assertForbidden();

        $this->actingAs($admin)->get(route('approval-requests.show', $approval))->assertOk()->assertSee('Edited Name');
        $this->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Approved fine'])->assertSessionHasNoErrors();

        $this->assertSame('Edited Name', $customer->fresh()->display_name);
        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_rejected_customer_edit_changes_nothing_and_admin_can_still_edit_directly(): void
    {
        $cashier = $this->userWithRole('cashier');
        $admin = $this->userWithRole('administrator');
        $customer = $this->job()->customer;
        $originalName = $customer->display_name;

        $this->actingAs($cashier)->post(route('customers.request-edit.store', $customer), $this->customerPayload())->assertSessionHasNoErrors();
        $approval = ApprovalRequest::firstOrFail();
        $this->actingAs($admin)->post(route('approval-requests.decide', $approval), ['decision' => 'rejected', 'comment' => 'Not needed'])->assertSessionHasNoErrors();
        $this->assertSame($originalName, $customer->fresh()->display_name);

        $this->actingAs($admin)->get(route('customers.request-edit', $customer))->assertForbidden();
        $this->put(route('customers.update', $customer), $this->customerPayload(['customer_name' => 'Direct Edit']))->assertRedirect();
        $this->assertSame('Direct Edit', $customer->fresh()->display_name);
    }

    public function test_job_edit_follows_the_same_approval_flow(): void
    {
        $cashier = $this->userWithRole('cashier');
        $admin = $this->userWithRole('administrator');
        $job = $this->job();
        $payload = [
            'job_type' => 'urgent', 'item_name' => 'Changed item', 'brand' => 'Acme', 'model' => 'X1',
            'reason' => 'Wrong item recorded', 'idempotency_key' => (string) Str::uuid(),
        ];

        $this->actingAs($cashier)->get(route('repair-jobs.edit', $job))->assertForbidden();
        $this->put(route('repair-jobs.update', $job), $payload)->assertForbidden();
        $this->get(route('repair-jobs.request-edit', $job))->assertOk();
        $this->post(route('repair-jobs.request-edit.store', $job), $payload)->assertRedirect(route('repair-jobs.show', $job));
        $this->assertNotSame('Changed item', $job->fresh()->item_name);

        $approval = ApprovalRequest::where('kind', 'job_update')->firstOrFail();
        $this->actingAs($admin)->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Approved fine'])->assertSessionHasNoErrors();
        $this->assertSame('Changed item', $job->fresh()->item_name);
    }

    public function test_admin_can_delete_a_job_directly_and_cashier_cannot(): void
    {
        $cashier = $this->userWithRole('cashier');
        $admin = $this->userWithRole('administrator');
        $job = $this->job();

        $this->actingAs($cashier)->delete(route('repair-jobs.destroy', $job))->assertForbidden();
        $this->assertDatabaseHas('repair_jobs', ['id' => $job->id]);

        $this->actingAs($admin)->delete(route('repair-jobs.destroy', $job))->assertRedirect(route('repair-jobs.index'));
        $this->assertDatabaseMissing('repair_jobs', ['id' => $job->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'repair_job.deleted']);
    }

    public function test_cashier_delete_request_needs_admin_approval(): void
    {
        $cashier = $this->userWithRole('cashier');
        $admin = $this->userWithRole('administrator');
        $job = $this->job();
        $payload = ['reason' => 'Created by mistake', 'idempotency_key' => (string) Str::uuid()];

        $this->actingAs($cashier)->post(route('repair-jobs.request-delete', $job), $payload)->assertRedirect(route('repair-jobs.show', $job));
        $this->post(route('repair-jobs.request-delete', $job), $payload);
        $this->assertSame(1, ApprovalRequest::where('kind', 'job_delete')->count());
        $this->assertDatabaseHas('repair_jobs', ['id' => $job->id]);

        $approval = ApprovalRequest::where('kind', 'job_delete')->firstOrFail();
        $this->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Approved fine'])->assertForbidden();
        $this->assertDatabaseHas('repair_jobs', ['id' => $job->id]);

        $this->actingAs($admin)->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Approved fine'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('repair_jobs', ['id' => $job->id]);
    }

    public function test_deleting_a_job_also_deletes_its_invoice(): void
    {
        $admin = $this->userWithRole('administrator');
        $job = $this->job();
        $invoice = $this->invoice($job->customer);
        \App\Modules\Invoices\Models\InvoiceJob::create([
            'invoice_id' => $invoice->id,
            'repair_job_id' => $job->id,
            'customer_id' => $job->customer_id,
        ]);

        $this->actingAs($admin);
        $db = fn (string $table) => \Illuminate\Support\Facades\DB::table($table);
        $receiptId = $db('repair_receipts')->insertGetId(['repair_job_id' => $job->id, 'customer_id' => $job->customer_id, 'receipt_number' => 'RC-DEL-1', 'created_at' => now(), 'updated_at' => now()]);
        $cardId = $db('job_cards')->insertGetId(['repair_job_id' => $job->id, 'paper_size' => 'A5', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['repair_receipt_id' => $receiptId], ['job_card_id' => $cardId], ['invoice_id' => $invoice->id]] as $link) {
            \Illuminate\Support\Facades\DB::table('document_print_histories')->insert($link + [
                'printed_at' => now(), 'printed_by_user_id' => $admin->id, 'document_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->delete(route('repair-jobs.destroy', $job))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('repair_jobs', ['id' => $job->id]);
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('invoice_jobs', ['invoice_id' => $invoice->id]);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('document_print_histories')->count());
    }

    public function test_admin_and_cashier_can_both_update_job_status_but_only_admin_can_cancel(): void
    {
        $cashier = $this->userWithRole('cashier');
        $admin = $this->userWithRole('administrator');
        $job = $this->job();

        $this->actingAs($cashier)->post(route('repair-jobs.update-status', $job), ['status' => 'Under Inspection'])->assertSessionHasNoErrors();
        $this->assertSame('Under Inspection', $job->fresh()->status->value);

        $this->actingAs($admin)->post(route('repair-jobs.update-status', $job), ['status' => 'Waiting for Spare Parts'])->assertSessionHasNoErrors();
        $this->assertSame('Waiting for Spare Parts', $job->fresh()->status->value);

        $this->actingAs($cashier)->post(route('repair-jobs.update-status', $job), ['status' => 'Cancelled', 'cancellation_reason' => 'Customer left'])->assertSessionHasErrors('status');
    }

    public function test_status_can_be_changed_from_the_job_status_window_and_returns_there(): void
    {
        $cashier = $this->userWithRole('cashier');
        $job = $this->job();

        $this->actingAs($cashier)->get(route('repair-jobs.status-search'))->assertOk()->assertSee('name="return_to"', false)->assertSee(route('repair-jobs.update-status', $job), false);

        $this->from(route('repair-jobs.status-search'))->post(route('repair-jobs.update-status', $job), ['status' => 'Under Inspection', 'return_to' => 'status-search'])
            ->assertRedirect(route('repair-jobs.status-search'))->assertSessionHasNoErrors();
        $this->assertSame('Under Inspection', $job->fresh()->status->value);
    }

    public function test_cancelled_job_hides_inspection_repair_and_quotation_sections(): void
    {
        $admin = $this->userWithRole('administrator');
        $job = $this->job();

        $this->actingAs($admin)->get(route('repair-jobs.show', $job))->assertSee(__('ui.inspection_and_repair_history'));

        $job->forceFill(['status' => \App\Enums\JobStatus::Cancelled])->save();
        $this->get(route('repair-jobs.show', $job))->assertOk()->assertDontSee(__('ui.inspection_and_repair_history'))->assertDontSee(__('ui.save_inspection'));
    }

    public function test_cancellation_reason_is_optional(): void
    {
        $admin = $this->userWithRole('administrator');
        $job = $this->job();

        $this->actingAs($admin)->post(route('repair-jobs.update-status', $job), ['status' => 'Cancelled'])->assertSessionHasNoErrors();
        $this->assertSame('Cancelled', $job->fresh()->status->value);
        $this->assertNull($job->fresh()->cancellation_reason);
    }

    public function test_approval_is_blocked_when_the_record_changed_after_the_request(): void
    {
        $cashier = $this->userWithRole('cashier');
        $admin = $this->userWithRole('administrator');
        $customer = $this->job()->customer;

        $this->actingAs($cashier)->post(route('customers.request-edit.store', $customer), $this->customerPayload())->assertSessionHasNoErrors();
        $customer->forceFill(['address' => 'Changed meanwhile'])->save();

        $approval = ApprovalRequest::firstOrFail();
        $this->actingAs($admin)->post(route('approval-requests.decide', $approval), ['decision' => 'approved', 'comment' => 'Approved fine'])->assertSessionHasErrors('decision');
        $this->assertSame('pending', $approval->fresh()->status);
    }
}
