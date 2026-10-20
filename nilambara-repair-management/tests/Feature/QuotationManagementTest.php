<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\JobStatus;
use App\Enums\QuotationPreparationStatus;
use App\Models\User;
use App\Modules\Quotations\Models\Quotation;
use App\Modules\Quotations\Models\QuotationHistory;
use App\Modules\RepairJobs\Models\JobApproval;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\RepairJobs\Services\RepairJobService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class QuotationManagementTest extends TestCase
{
    use CreatesDatabaseRecords;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_draft_quote_calculates_decimal_totals_without_touching_inventory_or_billing(): void
    {
        $user = $this->userWithRole('administrator');
        $job = $this->job();

        $this->actingAs($user)
            ->post(route('quotations.store'), $this->quotationData($job))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $quotation = Quotation::query()->sole();
        $this->assertMatchesRegularExpression('/^QUO-\d{5,}$/', $quotation->quotation_number);
        $this->assertSame(QuotationPreparationStatus::Draft, $quotation->preparation_status);
        $this->assertSame('2750.31', $quotation->total);
        $this->assertSame('2500.31', $quotation->parts_total);
        $this->assertSame('250.00', $quotation->labour_charge);
        $this->assertSame('2500.31', $quotation->items()->sole()->line_total);
        $this->assertSame('Database Test Customer', data_get($quotation->document_snapshot, 'customer.name'));
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_draft_edits_retain_removed_lines_and_audit_the_line_level_change(): void
    {
        $user = $this->userWithRole('administrator');
        $job = $this->job();
        $data = $this->quotationData($job, [
            'items' => [
                ['description' => 'First part', 'quantity' => '1.000', 'unit_price' => '100.0000'],
                ['description' => 'Second part', 'quantity' => '2.000', 'unit_price' => '50.0000'],
            ],
        ]);

        $this->actingAs($user)
            ->post(route('quotations.store'), $data)
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $quotation = Quotation::query()->sole();

        $this->actingAs($user)
            ->put(route('quotations.update', $quotation), $this->quotationData($job, [
                'items' => [
                    ['description' => 'Updated first part', 'quantity' => '1.000', 'unit_price' => '125.0000'],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $quotation->items()->count());
        $this->assertSame(1, $quotation->items()->whereNull('removed_at')->count());
        $this->assertSame(1, $quotation->items()->whereNotNull('removed_at')->count());
        $history = $quotation->history()->where('event_type', 'draft_edited')->sole();
        $this->assertCount(2, $history->old_values['items']);
        $this->assertCount(1, $history->new_values['items']);
        $this->assertSame('First part', $history->old_values['items'][0]['description']);
        $this->assertSame('Updated first part', $history->new_values['items'][0]['description']);
    }

    public function test_issue_and_customer_approval_are_recorded_with_job_status_history(): void
    {
        $user = $this->userWithRole('administrator');
        $job = $this->job();

        $this->actingAs($user)
            ->post(route('quotations.store'), $this->quotationData($job, ['intent' => 'issue']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $quotation = Quotation::query()->sole();
        $this->assertSame(QuotationPreparationStatus::Issued, $quotation->preparation_status);
        $this->assertNotNull($quotation->issued_at);
        $this->assertSame(ApprovalStatus::Pending, $quotation->approval_status);
        $this->assertSame($quotation->quotation_number, data_get($quotation->document_snapshot, 'quotation_number'));
        $this->assertSame(JobStatus::WaitingForCustomerApproval, $job->fresh()->status);
        $this->assertSame(ApprovalStatus::Pending, $job->fresh()->approval_status);

        $this->actingAs($user)
            ->post(route('quotations.decision', $quotation), [
                'decision' => 'approved',
                'decision_date' => now()->toDateString(),
                'customer_contact_name' => 'Customer representative',
                'approval_method' => 'telephone',
                'notes' => 'Confirmed by telephone',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(ApprovalStatus::Approved, $quotation->fresh()->approval_status);
        $this->assertSame(JobStatus::UnderRepair, $job->fresh()->status);
        $this->assertSame(ApprovalStatus::Approved, $job->fresh()->approval_status);
        $this->assertSame(1, $quotation->approvals()->count());
        $this->assertSame(1, JobApproval::query()->where('repair_job_id', $job->getKey())->count());
        $this->assertSame(1, QuotationHistory::query()->where('quotation_id', $quotation->getKey())
            ->where('event_type', 'customer_approved')->count());
        $this->assertSame(1, DB::table('job_status_histories')->where('repair_job_id', $job->getKey())
            ->where('new_status', JobStatus::UnderRepair->value)->count());
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_customer_rejection_sets_customer_contact_failed_and_is_append_only(): void
    {
        $user = $this->userWithRole('administrator');
        $job = $this->job();
        $quotation = $this->issueQuotation($user, $job);

        $this->actingAs($user)
            ->post(route('quotations.decision', $quotation), [
                'decision' => 'rejected',
                'decision_date' => now()->toDateString(),
                'customer_contact_name' => 'Customer representative',
                'approval_method' => 'email',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(ApprovalStatus::Rejected, $quotation->fresh()->approval_status);
        $this->assertSame(JobStatus::CustomerContactFailed, $job->fresh()->status);
        $this->assertSame(1, $quotation->approvals()->count());
        $this->assertDatabaseHas('job_status_histories', [
            'repair_job_id' => $job->getKey(),
            'new_status' => JobStatus::CustomerContactFailed->value,
        ]);
    }

    public function test_revision_copies_quote_items_and_supersedes_the_previous_issued_quote(): void
    {
        $user = $this->userWithRole('administrator');
        $job = $this->job();
        $original = $this->issueQuotation($user, $job);
        $this->actingAs($user)
            ->post(route('quotations.decision', $original), [
                'decision' => 'approved',
                'decision_date' => now()->toDateString(),
                'customer_contact_name' => 'Customer representative',
                'approval_method' => 'telephone',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('quotations.revise', $original))
            ->assertRedirect();

        $revision = Quotation::query()->where('revises_quotation_id', $original->getKey())->sole();
        $this->assertSame(2, $revision->revision);
        $this->assertSame($original->items()->sole()->line_total, $revision->items()->sole()->line_total);
        $this->assertSame(QuotationPreparationStatus::Draft, $revision->preparation_status);

        $this->actingAs($user)
            ->post(route('quotations.issue', $revision))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(QuotationPreparationStatus::Superseded, $original->fresh()->preparation_status);
        $this->assertSame(QuotationPreparationStatus::Issued, $revision->fresh()->preparation_status);
        $this->assertSame(ApprovalStatus::Pending, $job->fresh()->approval_status);
        $this->assertSame(JobStatus::WaitingForCustomerApproval, $job->fresh()->status);
    }

    public function test_revision_of_expired_quotation_gets_a_fresh_validity_period(): void
    {
        $user = $this->userWithRole('administrator');
        $job = $this->job();
        $quotation = $this->issueQuotation($user, $job);
        $this->travel(15)->days();

        $revisionDate = now(config('business.timezone', 'Asia/Colombo'));
        $this->actingAs($user)
            ->post(route('quotations.revise', $quotation))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $revision = Quotation::query()->where('revises_quotation_id', $quotation->getKey())->sole();
        $this->assertSame($revisionDate->toDateString(), $revision->quotation_date->toDateString());
        $this->assertSame($revisionDate->copy()->addDays(14)->toDateString(), $revision->valid_until->toDateString());

        $this->travelBack();
    }

    public function test_unapproved_quotation_is_not_visible_to_cashier(): void
    {
        $user = $this->userWithRole('administrator');
        $job = $this->job();
        $quotation = $this->issueQuotation($user, $job);
        $cashier = $this->userWithRole('cashier');

        $this->actingAs($cashier)
            ->get(route('quotations.show', $quotation))
            ->assertForbidden();
    }

    public function test_repair_cannot_start_until_an_issued_quotation_is_approved(): void
    {
        $user = $this->userWithRole('administrator');
        $job = $this->job();
        $this->issueQuotation($user, $job);

        try {
            app(RepairJobService::class)->updateStatus($job, [
                'status' => JobStatus::UnderRepair->value,
                'notes' => 'Attempt before approval',
            ], $user);
            $this->fail('An issued quotation without approval must block repair.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $this->assertSame(JobStatus::WaitingForCustomerApproval, $job->fresh()->status);
    }

    public function test_expired_quotation_cannot_be_approved(): void
    {
        $user = $this->userWithRole('administrator');
        $job = $this->job();
        $quotation = $this->issueQuotation($user, $job);
        $this->travel(15)->days();

        $this->actingAs($user)
            ->from(route('quotations.show', $quotation))
            ->post(route('quotations.decision', $quotation), [
                'decision' => 'approved',
                'decision_date' => now()->toDateString(),
                'customer_contact_name' => 'Customer representative',
                'approval_method' => 'telephone',
            ])
            ->assertRedirect(route('quotations.show', $quotation))
            ->assertSessionHasErrors('decision');

        $this->travelBack();
        $this->assertSame(ApprovalStatus::Pending, $quotation->fresh()->approval_status);
        $this->assertSame(0, $quotation->approvals()->count());
        $this->assertSame(JobStatus::WaitingForCustomerApproval, $job->fresh()->status);
    }

    public function test_future_customer_decision_date_is_rejected_without_saving_a_decision(): void
    {
        $user = $this->userWithRole('administrator');
        $job = $this->job();
        $quotation = $this->issueQuotation($user, $job);

        $this->actingAs($user)
            ->from(route('quotations.show', $quotation))
            ->post(route('quotations.decision', $quotation), [
                'decision' => 'approved',
                'decision_date' => now()->addDay()->toDateTimeString(),
                'customer_contact_name' => 'Customer representative',
                'approval_method' => 'telephone',
            ])
            ->assertRedirect(route('quotations.show', $quotation))
            ->assertSessionHasErrors('decision_date');

        $this->assertSame(ApprovalStatus::Pending, $quotation->fresh()->approval_status);
        $this->assertSame(0, $quotation->approvals()->count());
        $this->assertSame(JobStatus::WaitingForCustomerApproval, $job->fresh()->status);
    }

    public function test_a4_and_a5_pdfs_render_with_sinhala_font_and_log_each_export(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer(['customer_name' => 'නිමල පෙරේරා']);
        $job = $this->job($customer, ['item_name' => 'විදුලි මෝටරය']);
        $quotation = $this->issueQuotation($user, $job);

        $a4 = $this->actingAs($user)
            ->get(route('quotations.pdf', ['quotation' => $quotation, 'paper' => 'A4']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->getContent();
        $this->assertStringStartsWith('%PDF-', $a4);
        $this->assertStringContainsString('NotoSansSinhala', $a4);
        $this->assertStringContainsString('595.28', $a4);

        $a5 = $this->actingAs($user)
            ->get(route('quotations.pdf', ['quotation' => $quotation, 'paper' => 'A5']))
            ->assertOk()
            ->getContent();
        $this->assertStringStartsWith('%PDF-', $a5);
        $this->assertStringContainsString('419.53', $a5);
        $this->assertSame(2, DB::table('document_print_histories')
            ->where('quotation_id', $quotation->getKey())
            ->where('output_reference', $quotation->quotation_number.'.pdf')->count());
    }

    private function issueQuotation(User $user, RepairJob $job): Quotation
    {
        $this->actingAs($user)
            ->post(route('quotations.store'), $this->quotationData($job, ['intent' => 'issue']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return Quotation::query()->where('repair_job_id', $job->getKey())->sole();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function quotationData(RepairJob $job, array $overrides = []): array
    {
        return array_replace([
            'repair_job_id' => $job->getKey(),
            'quotation_date' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'proposed_repair_description' => 'Replace worn motor bearings',
            'labour_charge' => '250.00',
            'terms_and_conditions' => 'Parts availability may vary.',
            'items' => [[
                'description' => 'Motor bearing',
                'quantity' => '2.500',
                'unit_price' => '1000.1234',
            ]],
            'intent' => 'draft',
        ], $overrides);
    }
}
