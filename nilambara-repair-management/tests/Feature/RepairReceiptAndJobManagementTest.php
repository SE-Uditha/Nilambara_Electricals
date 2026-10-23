<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\User;
use App\Modules\AuditLogs\Models\AuditLog;
use App\Modules\CreditManagement\Models\CustomerAccountEntry;
use App\Modules\Customers\Models\Customer;
use App\Modules\Employees\Models\Employee;
use App\Modules\JobCards\Models\JobCard;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentAllocation;
use App\Modules\RepairJobs\Models\JobInspection;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Modules\RepairReceipts\Models\RepairReceipt;
use App\Modules\RepairReceipts\Services\RepairReceiptService;
use App\Modules\RolesPermissions\Models\Permission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class RepairReceiptAndJobManagementTest extends TestCase
{
    use CreatesDatabaseRecords;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_accepting_a_job_generates_one_receipt_and_exposes_print_pdf_and_legacy_redirects(): void
    {
        $cashier = $this->userWithRole('cashier');
        $customer = $this->customer($this->customerAttributes(['customer_name' => 'Nadeesha Silva', 'address' => '12 Lake Road, Colombo', 'mobile_number' => '0771234567']));
        $receiver = $this->employee(['name' => 'Nimal Perera']);
        $this->actingAs($cashier)->get(route('repair-jobs.create'))->assertOk()->assertSee(__('ui.new_repair_job'));
        $this->post(route('repair-jobs.store'), $this->receiptData($customer, $receiver, ['job_type' => 'urgent']))->assertSessionHasNoErrors();
        $job = RepairJob::sole();
        $receipt = RepairReceipt::sole();
        $this->assertSame($job->id, $receipt->repair_job_id);
        $this->get(route('repair-jobs.show', $job))->assertOk()->assertSee(route('repair-jobs.receipt.print', $job), false)->assertSee(route('repair-jobs.receipt.pdf', $job), false);
        $this->get(route('repair-jobs.receipt.print', $job))->assertOk()->assertSee('Customer Copy')->assertSee('12 Lake Road, Colombo')->assertDontSee('Urgent')->assertDontSee(__('ui.job_type'));
        $pdf = $this->get(route('repair-jobs.receipt.pdf', $job))->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $root = base_path('tmp/pdfs');
        if (! is_dir($root)) {
            mkdir($root, 0700, true);
        }
        file_put_contents($root.'/repair-receipt-preview.pdf', $pdf->getContent());
        $this->get(route('repair-receipts.index'))->assertRedirect(route('repair-jobs.index'));
        $this->get(route('repair-receipts.show', $receipt))->assertRedirect(route('repair-jobs.show', $job));
        $this->assertDatabaseCount('repair_jobs', 1);
        $this->assertDatabaseCount('repair_receipts', 1);
        $cashier->revokePermissionTo('receipts.print');
        DB::table('permission_user')->insert(['user_id' => $cashier->id, 'permission_id' => Permission::findByName('receipts.print')->id, 'model_type' => User::class, 'effect' => 'deny']);
        $this->actingAs($cashier->fresh())->get(route('repair-jobs.receipt.print', $job))->assertForbidden();
        $this->get(route('repair-jobs.receipt.pdf', $job))->assertForbidden();
    }

    public function test_cashier_saves_receipt_and_creates_linked_job_history_and_a6_card(): void
    {
        $cashier = $this->userWithRole('cashier');
        $customer = $this->customer($this->customerAttributes([
            'customer_name' => 'Nadeesha Silva',
            'mobile_number' => '0771234567',
            'address' => '12 Lake Road, Colombo',
        ]));
        $receiver = $this->employee();

        $this->actingAs($cashier)
            ->post(route('repair-receipts.store'), $this->receiptData($customer, $receiver))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $job = RepairJob::query()->sole();
        $receipt = RepairReceipt::query()->sole();

        $this->assertMatchesRegularExpression('/^JOB-\d{5,}$/', $job->automatic_number);
        $this->assertSame('MAN-2026-0001', $job->job_number);
        $this->assertSame($customer->id, $job->customer_id);
        $this->assertSame($receiver->id, $job->received_by_employee_id);
        $this->assertSame($job->id, $receipt->repair_job_id);
        $this->assertSame($customer->id, $receipt->customer_id);
        $this->assertSame('Received', $job->status->value);
        $this->assertDatabaseHas('job_status_histories', [
            'repair_job_id' => $job->id,
            'previous_status' => null,
            'new_status' => JobStatus::Received->value,
            'changed_by_user_id' => $cashier->id,
        ]);
        $this->assertDatabaseHas('job_cards', ['repair_job_id' => $job->id, 'paper_size' => 'A5']);
        $this->assertSame('Nadeesha Silva', data_get($receipt->document_snapshot, 'customer.name'));
        $this->assertSame('12 Lake Road, Colombo', data_get($receipt->document_snapshot, 'customer.address'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'repair_receipt.created',
            'entity_id' => $receipt->id,
        ]);
    }

    public function test_automatic_references_use_the_database_sequence_and_manual_numbers_are_unique(): void
    {
        $administrator = $this->userWithRole('administrator');
        $customer = $this->customer();
        $receiver = $this->employee();

        $this->actingAs($administrator)
            ->post(route('repair-receipts.store'), $this->receiptData($customer, $receiver))
            ->assertRedirect();
        $this->actingAs($administrator)
            ->post(route('repair-receipts.store'), $this->receiptData($customer, $receiver, [
                'job_number' => 'MAN-2026-0002',
            ]))
            ->assertRedirect();

        $numbers = RepairJob::query()->orderBy('id')->pluck('automatic_number')->all();
        $this->assertCount(2, array_unique($numbers));
        foreach ($numbers as $number) {
            $this->assertMatchesRegularExpression('/^JOB-\d{5,}$/', $number);
        }

        $this->actingAs($administrator)
            ->from(route('repair-receipts.create'))
            ->post(route('repair-receipts.store'), $this->receiptData($customer, $receiver))
            ->assertRedirect(route('repair-receipts.create'))
            ->assertSessionHasErrors('job_number');

        $this->assertDatabaseCount('repair_jobs', 2);
        $this->assertDatabaseCount('repair_receipts', 2);
    }

    public function test_receipt_validation_rejects_inactive_customers_and_missing_required_fields(): void
    {
        $cashier = $this->userWithRole('cashier');
        $inactiveCustomer = $this->customer(['is_active' => false]);
        $receiver = $this->employee();

        $this->actingAs($cashier)
            ->from(route('repair-receipts.create'))
            ->post(route('repair-receipts.store'), $this->receiptData($inactiveCustomer, $receiver, [
                'item_name' => '',
                'brand' => '',
                'model' => '',
            ]))
            ->assertRedirect(route('repair-receipts.create'))
            ->assertSessionHasErrors(['customer_id', 'item_name', 'brand', 'model']);

        $this->assertDatabaseCount('repair_jobs', 0);
        $this->assertDatabaseCount('repair_receipts', 0);
    }

    public function test_advance_payment_is_recorded_once_in_payment_allocation_and_customer_ledger(): void
    {
        $administrator = $this->userWithRole('administrator');
        $customer = $this->customer();
        $receiver = $this->employee();

        $this->actingAs($administrator)->post(route('repair-receipts.store'), $this->receiptData($customer, $receiver, [
            'advance_payment' => '1250.50',
            'payment_method' => 'cash',
        ]))->assertRedirect();

        $job = RepairJob::query()->sole();
        $payment = Payment::query()->sole();
        $allocation = PaymentAllocation::query()->sole();
        $entry = CustomerAccountEntry::query()->sole();
        $this->assertSame('advance', $payment->payment_type);
        $this->assertSame('1250.50', $payment->amount);
        $this->assertSame('job_advance', $allocation->target_type);
        $this->assertSame($job->id, $allocation->repair_job_id);
        $this->assertSame('-1250.50', $entry->amount);
        $this->assertSame($payment->id, $entry->payment_id);
        $this->assertSame('1250.50', number_format((float) PaymentAllocation::query()
            ->where('repair_job_id', $job->id)
            ->where('target_type', 'job_advance')
            ->sum('amount'), 2, '.', ''));
    }

    public function test_receipt_and_duplicate_print_use_snapshot_without_job_type_and_log_each_copy(): void
    {
        $administrator = $this->userWithRole('administrator');
        $customer = $this->customer();
        $receiver = $this->employee();
        $this->actingAs($administrator)->post(route('repair-receipts.store'), $this->receiptData($customer, $receiver, [
            'job_type' => 'urgent',
        ]))->assertRedirect();
        $receipt = RepairReceipt::query()->sole();

        $this->actingAs($administrator)->get(route('repair-receipts.print', $receipt))
            ->assertOk()
            ->assertSee('A5')
            ->assertSee('MAN-2026-0001')
            ->assertSee(config('business.name'))
            ->assertDontSee('Urgent');

        $this->actingAs($administrator)->get(route('repair-receipts.duplicate', $receipt))
            ->assertOk()
            ->assertSee('DUPLICATE COPY');

        $this->assertDatabaseCount('document_print_histories', 2);
        $this->assertDatabaseHas('document_print_histories', [
            'repair_receipt_id' => $receipt->id,
            'is_duplicate' => 1,
            'duplicate_label' => 'DUPLICATE COPY',
        ]);
        $this->assertSame(1, AuditLog::query()->where('action', 'repair_receipt.reprinted')->count());
    }

    public function test_failed_advance_recording_rolls_back_the_entire_job_registration(): void
    {
        $administrator = $this->userWithRole('administrator');
        $customer = $this->customer();
        $receiver = $this->employee();
        $data = $this->receiptData($customer, $receiver, [
            'advance_payment' => '10.00',
            'payment_method' => 'unsupported',
        ]);

        try {
            app(RepairReceiptService::class)->register($data, $administrator);
            $this->fail('Invalid payment method should fail advance recording.');
        } catch (\ValueError) {
            $this->assertDatabaseCount('repair_jobs', 0);
            $this->assertDatabaseCount('repair_receipts', 0);
            $this->assertDatabaseCount('job_status_histories', 0);
            $this->assertDatabaseCount('job_cards', 0);
            $this->assertDatabaseCount('payments', 0);
            $this->assertDatabaseCount('customer_account_entries', 0);
        }
    }

    public function test_authorized_status_changes_append_history_and_do_not_invent_transitions(): void
    {
        $administrator = $this->userWithRole('administrator');
        $job = $this->job(null, ['automatic_number' => 'JOB-TEST-10001']);

        $this->actingAs($administrator)
            ->post(route('repair-jobs.update-status', $job), [
                'status' => JobStatus::UnderRepair->value,
                'notes' => 'Started workshop work.',
            ])
            ->assertRedirect(route('repair-jobs.show', $job));

        $this->assertSame(JobStatus::UnderRepair, $job->fresh()->status);
        $this->assertDatabaseHas('job_status_histories', [
            'repair_job_id' => $job->id,
            'previous_status' => JobStatus::Received->value,
            'new_status' => JobStatus::UnderRepair->value,
            'notes' => 'Started workshop work.',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'repair_job.status_changed', 'entity_id' => $job->id]);
    }

    public function test_job_assignment_and_record_level_access_are_limited_to_active_technicians(): void
    {
        $administrator = $this->userWithRole('administrator');
        $technician = $this->userWithRole('cashier');
        $technicianEmployee = $this->employee(['user_id' => $technician->id, 'job_title' => 'Technician']);
        $job = $this->job();

        $this->actingAs($administrator)
            ->post(route('repair-jobs.assign', $job), ['technician_id' => $technicianEmployee->id])
            ->assertRedirect(route('repair-jobs.show', $job));

        $this->assertSame($technicianEmployee->id, $job->fresh()->technician_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'repair_job.technician_assigned', 'entity_id' => $job->id]);

        $unlinkedEmployee = $this->employee(['is_active' => false]);
        $this->actingAs($administrator)
            ->from(route('repair-jobs.assign-form', $job))
            ->post(route('repair-jobs.assign', $job), ['technician_id' => $unlinkedEmployee->id])
            ->assertRedirect(route('repair-jobs.assign-form', $job))
            ->assertSessionHasErrors('technician_id');

        $assignedJob = $this->job(null, ['technician_id' => $technicianEmployee->id]);
        $otherJob = $this->job();
        $this->actingAs($technician)->get(route('repair-jobs.show', $assignedJob))->assertOk();
        $this->actingAs($technician)->get(route('repair-jobs.show', $otherJob))->assertOk();
        $this->actingAs($technician)->get(route('repair-jobs.index'))->assertOk()->assertSee($otherJob->automatic_number);
    }

    public function test_cashier_can_register_print_and_assign_but_technical_status_requires_permission(): void
    {
        $cashier = $this->userWithRole('cashier');
        DB::table('permission_user')->insert(['user_id' => $cashier->id, 'permission_id' => Permission::findByName('jobs.update-status')->id, 'model_type' => User::class, 'effect' => 'deny']);
        $job = $this->job();

        $this->actingAs($cashier)->get(route('repair-jobs.index'))->assertOk();
        $this->actingAs($cashier)->get(route('repair-jobs.status-search'))->assertOk();
        $this->actingAs($cashier)->post(route('repair-jobs.update-status', $job), [
            'status' => JobStatus::UnderRepair->value,
        ])->assertForbidden();
        $technician = $this->userWithRole('cashier');
        $employee = $this->employee(['user_id' => $technician->id, 'job_title' => 'Technician']);
        $this->actingAs($cashier)->post(route('repair-jobs.assign', $job), [
            'technician_id' => $employee->id,
        ])->assertRedirect(route('repair-jobs.show', $job))->assertSessionHasNoErrors();
        $this->assertSame($employee->id, $job->fresh()->technician_id);
    }

    public function test_technician_can_record_inspection_and_repair_notes_without_stock_deduction(): void
    {
        $technician = $this->userWithRole('cashier');
        $employee = $this->employee(['user_id' => $technician->id]);
        $job = $this->job(null, ['technician_id' => $employee->id]);

        $this->actingAs($technician)->post(route('repair-jobs.inspect', $job), [
            'inspected_at' => now()->format('Y-m-d\TH:i'),
            'existing_fault' => 'Bearing noise',
            'inspection_result' => 'Bearing failure confirmed.',
            'technical_information' => 'Motor winding intact.',
        ])->assertRedirect(route('repair-jobs.show', $job));

        $this->actingAs($technician)->post(route('repair-jobs.save-repair', $job), [
            'work_performed' => 'Replaced bearing.',
            'parts_used' => '6203 sealed bearing',
            'parts_quantity' => '1.000',
            'parts_cost' => '350.00',
            'labor_cost' => '500.00',
            'testing_details' => 'Run test passed.',
        ])->assertRedirect(route('repair-jobs.show', $job));

        $this->assertSame(2, JobInspection::query()->where('repair_job_id', $job->id)->count());
        $this->assertDatabaseHas('job_inspections', [
            'repair_job_id' => $job->id,
            'parts_used' => '6203 sealed bearing',
            'parts_quantity' => '1.000',
        ]);
        $this->assertSame('350.00', $job->fresh()->parts_cost);
        $this->assertSame('500.00', $job->fresh()->labour_cost);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_job_card_print_is_one_page_a5_with_writable_technician_and_handover_fields(): void
    {
        $administrator = $this->userWithRole('administrator');
        $job = $this->job(null, ['automatic_number' => 'JOB-TEST-10002']);

        $response = $this->actingAs($administrator)->get(route('job-cards.print', $job))
            ->assertOk()
            ->assertSee('@page')
            ->assertSee('size: A5 portrait')
            ->assertSee(__('ui.job_id'))
            ->assertSee(__('ui.worksheet_job_number'))
            ->assertSee(__('ui.worksheet_received_date'))
            ->assertSee(__('ui.job_status'))
            ->assertSee(__('ui.worksheet_issues_found'))
            ->assertSee(__('ui.repair_details'))
            ->assertSee(__('ui.parts_used'))
            ->assertSee(__('ui.parts_cost'))
            ->assertSee(__('ui.total_amount'))
            ->assertSee(__('ui.special_notes'))
            ->assertSee(__('ui.worksheet_handover'))
            ->assertSee(__('ui.cashier_received'));

        $this->assertSame(1, substr_count($response->getContent(), '<article class="page">'));

        $this->assertDatabaseHas('document_print_histories', [
            'job_card_id' => JobCard::query()->where('repair_job_id', $job->id)->value('id'),
            'is_duplicate' => 0,
        ]);
    }

    public function test_job_card_is_a_blank_technician_worksheet_even_after_saved_repair_updates(): void
    {
        $actor = $this->userWithRole('cashier');
        $job = $this->job(null, ['automatic_number' => 'JOB-WORKSHEET-001', 'repair_description' => 'OLD REPAIR TEXT', 'parts_cost' => '8765.43']);
        $inspector = $this->employee(['name' => 'Saved Inspector Name']);
        $inspection = JobInspection::create(['repair_job_id' => $job->id, 'inspected_at' => '2026-10-20 09:00:00', 'inspected_by_employee_id' => $inspector->id, 'inspection_result' => 'OLD FINDINGS', 'parts_used' => 'OLD PARTS TEXT', 'parts_quantity' => '7.000', 'repaired_by_employee_id' => $inspector->id, 'special_notes' => 'OLD COMPLETION NOTES']);
        $this->actingAs($actor);
        foreach (['job-cards.print', 'job-cards.duplicate'] as $route) {
            $this->get(route($route, $job))->assertOk()->assertSee('JOB-WORKSHEET-001')->assertSee($job->item_name)->assertSee(__('ui.worksheet_issues_found'))->assertSee(__('ui.worksheet_handover'))->assertDontSee('OLD REPAIR TEXT')->assertDontSee('OLD FINDINGS')->assertDontSee('OLD PARTS TEXT')->assertDontSee('OLD COMPLETION NOTES')->assertDontSee('Saved Inspector Name')->assertDontSee('8765.43');
        }
        $pdf = $this->get(route('job-cards.pdf', $job))->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        if (! is_dir(base_path('tmp/pdfs'))) { mkdir(base_path('tmp/pdfs'),0700,true); }
        file_put_contents(base_path('tmp/pdfs/technician-worksheet-preview.pdf'), $pdf->getContent());
        $this->assertSame('OLD COMPLETION NOTES', $inspection->fresh()->special_notes);
        $this->assertSame('OLD REPAIR TEXT', $job->fresh()->repair_description);
        $this->assertDatabaseHas('document_print_histories', ['job_card_id' => $job->jobCard->id]);
    }

    public function test_search_and_filters_return_database_paginated_jobs(): void
    {
        $administrator = $this->userWithRole('administrator');
        $customer = $this->customer($this->customerAttributes([
            'customer_name' => 'Status Search Customer',
            'mobile_number' => '0777654321',
        ]));
        $matching = $this->job($customer, [
            'job_number' => 'MAN-SEARCH-1',
            'automatic_number' => 'JOB-SEARCH-0001',
            'item_name' => 'Water pump',
            'status' => JobStatus::UnderRepair->value,
        ]);
        $this->job(null, [
            'job_number' => 'MAN-OTHER-2',
            'automatic_number' => 'JOB-OTHER-0002',
            'item_name' => 'Fan',
        ]);

        $this->actingAs($administrator)
            ->get(route('repair-jobs.status-search', ['q' => '0777654321', 'status' => JobStatus::UnderRepair->value]))
            ->assertOk()
            ->assertSee($matching->job_number)
            ->assertSee('0777654321')
            ->assertDontSee('MAN-OTHER-2')
            ->assertSee(__('ui.amount_unavailable'));

        $this->actingAs($administrator)
            ->get(route('repair-jobs.index', ['q' => 'JOB-OTHER-0002']))
            ->assertOk()
            ->assertSee('MAN-OTHER-2')
            ->assertDontSee('MAN-SEARCH-1');
    }

    public function test_repair_job_identifiers_cannot_be_changed_after_creation(): void
    {
        $job = $this->job(null, ['automatic_number' => 'JOB-TEST-10003']);
        $job->job_number = 'CHANGED';

        $this->expectException(\LogicException::class);
        $job->save();
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
    private function receiptData(Customer $customer, Employee $receiver, array $overrides = []): array
    {
        return array_replace([
            'customer_id' => $customer->getKey(),
            'job_number' => 'MAN-2026-0001',
            'job_type' => 'normal',
            'item_name' => 'Electric water pump',
            'brand' => 'Nilambara',
            'model' => 'NP-60',
            'item_description' => 'Domestic pressure pump',
            'reported_fault' => 'Does not start',
            'accessories_received' => 'Power cable',
            'physical_condition' => 'Used',
            'visible_damage' => 'Small scratch',
            'advance_payment' => '0.00',
            'payment_method' => '',
            'labor_cost' => '1000.00',
            'received_by_employee_id' => $receiver->getKey(),
            'special_notes' => 'Call customer before inspection.',
            'intent' => 'save',
        ], $overrides);
    }
}
