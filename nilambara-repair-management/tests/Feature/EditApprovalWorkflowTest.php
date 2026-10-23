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
