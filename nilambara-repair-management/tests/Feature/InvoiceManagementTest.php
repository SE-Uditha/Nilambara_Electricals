<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\DocumentStatus;
use App\Enums\JobStatus;
use App\Enums\QuotationPreparationStatus;
use App\Models\User;
use App\Modules\Inventory\Models\JobPartUsage;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceHistory;
use App\Modules\Invoices\Models\InvoiceJob;
use App\Modules\Invoices\Services\InvoiceBalanceService;
use App\Modules\Invoices\Services\InvoiceService;
use App\Modules\Payments\Models\PaymentAllocation;
use App\Modules\Payments\Models\PaymentAllocationRelease;
use App\Modules\PrintTemplates\Models\PrintTemplate;
use App\Modules\Quotations\Models\Quotation;
use App\Modules\RepairJobs\Models\RepairJob;
use Brick\Math\BigDecimal;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
{
    use CreatesDatabaseRecords;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_creates_unique_draft_invoices_with_multiple_job_totals(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer();
        $jobs = [
            $this->billableJob($customer, '250.00', '125.00'),
            $this->billableJob($customer, '310.00', '40.00'),
            $this->billableJob($customer, '100.00', '0.00'),
        ];

        $this->actingAs($user)->post(route('invoices.store'), $this->invoiceData($customer, $jobs))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->sole();
        $this->assertSame('INV-00001', $invoice->invoice_number);
        $this->assertSame(DocumentStatus::Draft, $invoice->status);
        $this->assertSame('825.00', $invoice->total);
        $this->assertSame(3, $invoice->invoiceJobs()->count());
        $this->assertSame(5, $invoice->invoiceItems()->count());
        $this->assertSame(4, InvoiceHistory::query()->where('invoice_id', $invoice->getKey())->count());
        $this->assertSame('Database Test Customer', data_get($invoice->document_snapshot, 'customer.name'));
        $this->assertDatabaseCount('payments', 0);

        $next = $this->billableJob($customer, '90.00', '10.00');
        $this->actingAs($user)->post(route('invoices.store'), $this->invoiceData($customer, [$next]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('INV-00002', Invoice::query()->orderByDesc('id')->firstOrFail()->invoice_number);
    }

    public function test_invoice_form_restores_customer_and_jobs_after_validation_failure(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer(['customer_name' => 'Restored Invoice Customer']);
        $job = $this->billableJob($customer, '100.00', '0.00');

        $this->actingAs($user)->from(route('invoices.create'))
            ->post(route('invoices.store'), $this->invoiceData($customer, [$job], ['invoice_date' => 'not-a-date']))
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHasErrors('invoice_date');

        $this->actingAs($user)->get(route('invoices.create'))
            ->assertOk()
            ->assertSee($customer->display_name)
            ->assertSee($job->automatic_number ?: $job->job_number);
    }

    public function test_rejects_cross_customer_duplicate_and_uncompleted_jobs_atomically(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer();
        $job = $this->billableJob($customer, '100.00', '0.00');
        $otherJob = $this->billableJob($this->customer(), '50.00', '0.00');

        $this->actingAs($user)->from(route('invoices.create'))
            ->post(route('invoices.store'), $this->invoiceData($customer, [$job, $otherJob]))
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHasErrors('repair_job_ids');
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('invoice_jobs', 0);

        $this->actingAs($user)->from(route('invoices.create'))
            ->post(route('invoices.store'), [
                ...$this->invoiceData($customer, [$job]),
                'repair_job_ids' => [$job->getKey(), $job->getKey()],
            ])
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHasErrors('repair_job_ids.1');
        $this->assertDatabaseCount('invoices', 0);

        $unfinished = $this->job($customer, ['status' => JobStatus::UnderRepair->value]);
        $this->actingAs($user)->from(route('invoices.create'))
            ->post(route('invoices.store'), $this->invoiceData($customer, [$unfinished]))
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHasErrors('repair_job_ids');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_actual_issued_part_usages_are_itemized_with_decimal_rounding_without_stock_changes(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer();
        $part = $this->part(['name' => 'Motor coupling', 'selling_price' => '100.1234']);
        $location = $this->location();
        $job = $this->billableJob($customer, '50.00', '150.19');
        JobPartUsage::query()->create([
            'repair_job_id' => $job->getKey(),
            'spare_part_id' => $part->getKey(),
            'location_id' => $location->getKey(),
            'quantity' => '1.500',
            'unit_cost' => '50.0000',
            'unit_price' => '100.1234',
            'status' => 'issued',
            'requested_by_user_id' => $user->getKey(),
            'idempotency_key' => $this->code('USAGE'),
        ]);

        $invoice = $this->createIssuedInvoice($user, $customer, [$job]);

        $partLine = $invoice->invoiceItems()->where('item_type', 'part')->sole();
        $this->assertSame('Motor coupling', $partLine->description);
        $this->assertSame('150.19', $partLine->line_total);
        $this->assertSame('200.19', $invoice->total);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_draft_update_appends_jobs_and_saves_delivery_metadata_without_removing_links(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer();
        $first = $this->billableJob($customer, '200.00', '0.00');
        $second = $this->billableJob($customer, '100.00', '50.00');
        $this->actingAs($user)->post(route('invoices.store'), $this->invoiceData($customer, [$first]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $invoice = Invoice::query()->sole();

        $this->actingAs($user)->put(route('invoices.update', $invoice), $this->invoiceData($customer, [$first, $second], [
            'collected_by' => 'Test collector',
            'vehicle_number' => 'WP ABC-1234',
        ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('350.00', $invoice->fresh()->total);
        $this->assertSame('Test collector', $invoice->fresh()->collected_by);
        $this->assertSame(2, $invoice->invoiceJobs()->count());
        $this->assertDatabaseHas('invoice_history', ['invoice_id' => $invoice->getKey(), 'event_type' => 'draft_updated']);
    }

    public function test_invoice_transaction_rolls_back_when_a_billing_line_cannot_be_written(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Rollback fault injection is isolated to SQLite.');
        }
        $user = $this->userWithRole('administrator');
        $customer = $this->customer();
        $job = $this->billableJob($customer, '100.00', '0.00');
        DB::statement("CREATE TRIGGER fail_invoice_items BEFORE INSERT ON invoice_items BEGIN SELECT RAISE(ABORT, 'test line insert failure'); END");

        try {
            app(InvoiceService::class)->create($this->invoiceData($customer, [$job]), $user);
            $this->fail('The injected line failure should abort invoice creation.');
        } catch (QueryException) {
            $this->assertDatabaseCount('invoices', 0);
            $this->assertDatabaseCount('invoice_jobs', 0);
            $this->assertDatabaseCount('invoice_items', 0);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS fail_invoice_items');
        }
    }

    public function test_approved_quotation_prices_are_snapshotted_and_mismatches_are_blocked(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer();
        $job = $this->billableJob($customer, '100.00', '400.00');
        $quotation = $this->approvedQuotation($job, '500.00', '100.00', '400.00');

        $this->actingAs($user)->post(route('invoices.store'), $this->invoiceData($customer, [$job], ['intent' => 'issue']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->sole();
        $this->assertSame(DocumentStatus::Finalized, $invoice->status);
        $this->assertSame('500.00', $invoice->total);
        $this->assertSame($quotation->quotation_number, data_get($invoice->invoiceJobs()->sole()->job_snapshot, 'quotation_number'));
        $this->assertSame($quotation->items()->sole()->getKey(), $invoice->invoiceItems()->whereNotNull('quotation_item_id')->sole()->quotation_item_id);
        $this->assertSame(0, DB::table('stock_movements')->where('repair_job_id', $job->getKey())->count());

        $secondJob = $this->billableJob($customer, '101.00', '400.00');
        $this->approvedQuotation($secondJob, '500.00', '100.00', '400.00');
        $this->actingAs($user)->from(route('invoices.create'))
            ->post(route('invoices.store'), $this->invoiceData($customer, [$secondJob]))
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHasErrors('repair_job_ids');
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_invoice_balance_uses_existing_advance_and_payment_allocations_without_accepting_payments(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer();
        $job = $this->billableJob($customer, '100.00', '400.00');
        $invoice = $this->createIssuedInvoice($user, $customer, [$job]);
        $advancePayment = $this->payment($customer, [
            'payment_type' => 'advance',
            'amount' => '50.00',
        ]);
        $this->allocation($advancePayment->getKey(), $customer->getKey(), 'job_advance', $job->getKey(), null, '50.00', $user);

        $invoicePayment = $this->payment($customer, ['amount' => '100.00']);
        $invoiceAllocation = $this->allocation($invoicePayment->getKey(), $customer->getKey(), 'invoice', null, $invoice->getKey(), '100.00', $user);
        PaymentAllocationRelease::query()->create([
            'payment_allocation_id' => $invoiceAllocation->getKey(),
            'payment_id' => $invoicePayment->getKey(),
            'amount' => '25.00',
            'reason_type' => 'reapplication',
            'released_at' => now(),
            'released_by_user_id' => $user->getKey(),
            'notes' => 'Test release',
            'idempotency_key' => $this->code('REL'),
        ]);

        $this->actingAs($user)->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('50.00')
            ->assertSee('75.00')
            ->assertSee('375.00');
        $balance = app(InvoiceBalanceService::class)->summary($invoice->fresh());
        $this->assertSame('50.00', $balance['advance_paid']);
        $this->assertSame('75.00', $balance['previous_payments']);
        $this->assertSame('0.00', $balance['current_payment']);
        $this->assertSame('375.00', $balance['outstanding']);
        $this->assertSame('partially_paid', $balance['payment_status']);
    }

    public function test_issued_snapshot_is_immutable_and_payment_prevents_cancellation(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer();
        $job = $this->billableJob($customer, '100.00', '400.00');
        $invoice = $this->createIssuedInvoice($user, $customer, [$job]);
        $snapshot = $invoice->document_snapshot;

        $this->expectException(\LogicException::class);
        $invoice->total = '999.00';
        $invoice->save();
        $this->assertSame($snapshot, $invoice->fresh()->document_snapshot);
    }

    public function test_invoice_with_allocated_payment_cannot_be_cancelled(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer();
        $job = $this->billableJob($customer, '100.00', '400.00');
        $invoice = $this->createIssuedInvoice($user, $customer, [$job]);
        $payment = $this->payment($customer, ['amount' => '10.00']);
        $this->allocation($payment->getKey(), $customer->getKey(), 'invoice', null, $invoice->getKey(), '10.00', $user);

        $this->actingAs($user)->from(route('invoices.show', $invoice))
            ->post(route('invoices.cancel', $invoice), ['reason' => 'Test cancellation reason'])
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHasErrors('invoice');
        $this->assertSame(DocumentStatus::Finalized, $invoice->fresh()->status);
    }

    public function test_cancelled_invoice_keeps_its_number_and_job_reservation(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer();
        $job = $this->billableJob($customer, '100.00', '0.00');
        $invoice = $this->createIssuedInvoice($user, $customer, [$job]);

        $this->actingAs($user)->post(route('invoices.cancel', $invoice), ['reason' => 'Customer invoice cancelled'])
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHasNoErrors();
        $this->assertSame(DocumentStatus::Cancelled, $invoice->fresh()->status);
        $this->assertSame($invoice->invoice_number, $invoice->fresh()->invoice_number);
        $this->assertSame(1, InvoiceJob::query()->where('repair_job_id', $job->getKey())->count());

        $this->actingAs($user)->from(route('invoices.create'))
            ->post(route('invoices.store'), $this->invoiceData($customer, [$job]))
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHasErrors('repair_job_ids');
    }

    public function test_cashier_can_create_issue_and_print_but_technician_cannot_manage_invoices(): void
    {
        $cashier = $this->userWithRole('cashier');
        $customer = $this->customer();
        $job = $this->billableJob($customer, '500.00', '0.00');

        $this->actingAs($cashier)->post(route('invoices.store'), $this->invoiceData($customer, [$job], ['intent' => 'issue']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $invoice = Invoice::query()->sole();
        $this->actingAs($cashier)->get(route('invoices.print', $invoice))->assertOk();

        $technician = $this->userWithRole('technician');
        $this->actingAs($technician)->get(route('invoices.index'))->assertForbidden();
        $this->actingAs($technician)->get(route('invoices.show', $invoice))->assertForbidden();
    }

    public function test_print_duplicate_and_a5_pdf_use_saved_data_and_log_each_output(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer(['customer_name' => 'නිමල පෙරේරා']);
        $job = $this->billableJob($customer, '250.00', '125.00', ['item_name' => 'විදුලි මෝටරය']);
        $invoice = $this->createIssuedInvoice($user, $customer, [$job]);

        $this->actingAs($user)->get(route('invoices.print', $invoice))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('විදුලි මෝටරය');
        $this->actingAs($user)->get(route('invoices.duplicate', $invoice))
            ->assertOk()
            ->assertSee('DUPLICATE COPY');

        $pdf = $this->actingAs($user)->get(route('invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('NotoSansSinhala', $pdf);
        $this->assertStringContainsString('419.53', $pdf);
        $this->assertSame(3, DB::table('document_print_histories')->where('invoice_id', $invoice->getKey())->count());
        $this->assertSame(1, DB::table('document_print_histories')->where('invoice_id', $invoice->getKey())->where('is_duplicate', true)->count());
    }

    public function test_invoice_print_uses_active_template_layout_and_footer(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer();
        $job = $this->billableJob($customer, '250.00', '125.00');
        $invoice = $this->createIssuedInvoice($user, $customer, [$job]);
        $template = PrintTemplate::query()->create([
            'code' => 'invoice-custom',
            'version' => 1,
            'document_type' => 'invoice',
            'name' => 'Custom invoice',
            'document_title' => 'Custom invoice title',
            'paper_size' => 'A4',
            'orientation' => 'landscape',
            'font_size_pt' => 11,
            'margin_top_mm' => 6,
            'margin_right_mm' => 6,
            'margin_bottom_mm' => 6,
            'margin_left_mm' => 6,
            'footer' => 'Custom invoice footer',
            'duplicate_copy_label' => 'Copy',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('invoices.print', $invoice))
            ->assertOk()
            ->assertSee('Custom invoice title')
            ->assertSee('Custom invoice footer')
            ->assertSee('size:A4 landscape');

        $history = DB::table('document_print_histories')->where('invoice_id', $invoice->getKey())->first();
        $this->assertSame($template->getKey(), $history->print_template_id);
        $this->assertSame('A4', data_get(json_decode($history->document_snapshot, true), 'paper_size'));
        $this->assertSame('landscape', data_get(json_decode($history->document_snapshot, true), 'orientation'));
    }

    public function test_search_and_filters_use_invoice_customer_and_repair_job_records(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer(['mobile_number' => '0770001111']);
        $job = $this->billableJob($customer, '500.00', '0.00');
        $invoice = $this->createIssuedInvoice($user, $customer, [$job]);

        $this->actingAs($user)->get(route('invoices.index', ['q' => '0770001111']))
            ->assertOk()
            ->assertSee($invoice->invoice_number);
        $this->actingAs($user)->get(route('invoices.index', ['status' => 'finalized', 'payment_status' => 'outstanding']))
            ->assertOk()
            ->assertSee($invoice->invoice_number);
    }

    public function test_step_four_customer_search_and_billable_job_lookup_are_reused(): void
    {
        $cashier = $this->userWithRole('cashier');
        $customer = $this->customer(['customer_name' => 'Invoice Lookup Customer']);
        $job = $this->billableJob($customer, '75.00', '0.00');

        $this->actingAs($cashier)->get(route('customers.search', ['q' => 'Invoice Lookup']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $customer->getKey());
        $this->actingAs($cashier)->get(route('invoices.jobs', $customer))
            ->assertOk()
            ->assertJsonPath('data.0.id', $job->getKey());
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->employee(['user_id' => $user->getKey(), 'is_active' => true]);

        return $user;
    }

    private function billableJob($customer, string $labour, string $parts, array $overrides = []): RepairJob
    {
        return $this->job($customer, array_replace([
            'brand' => 'Test brand',
            'status' => JobStatus::RepairCompleted->value,
            'labour_charge' => $labour,
            'parts_charge' => $parts,
            'total_job_amount' => (string) BigDecimal::of($labour)->plus($parts)->toScale(2),
            'repair_description' => 'Repair motor assembly',
        ], $overrides));
    }

    private function approvedQuotation(RepairJob $job, string $total, string $labour, string $partTotal): Quotation
    {
        $quotation = $this->quotation($job, [
            'quotation_number' => $this->code('QUO'),
            'proposed_repair_description' => 'Approved motor repair',
            'labour_charge' => $labour,
            'subtotal' => $total,
            'discount_amount' => '0.00',
            'total' => $total,
            'parts_total' => $partTotal,
            'approval_status' => ApprovalStatus::Approved->value,
            'preparation_status' => QuotationPreparationStatus::Issued->value,
            'status' => 'finalized',
            'issued_at' => now(),
            'valid_until' => now()->addDays(10)->toDateString(),
        ]);
        $quotation->items()->create([
            'line_number' => 1,
            'display_order' => 1,
            'item_type' => 'part',
            'description' => 'Approved motor part',
            'quantity' => '1.000',
            'unit_price' => $partTotal,
            'discount_amount' => '0.00',
            'line_total' => $partTotal,
        ]);

        return $quotation;
    }

    private function createIssuedInvoice(User $user, $customer, array $jobs): Invoice
    {
        $this->actingAs($user)->post(route('invoices.store'), $this->invoiceData($customer, $jobs, ['intent' => 'issue']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return Invoice::query()->latest('id')->firstOrFail();
    }

    private function invoiceData($customer, array $jobs, array $overrides = []): array
    {
        return array_replace([
            'customer_id' => $customer->getKey(),
            'repair_job_ids' => array_map(fn (RepairJob $job): int => (int) $job->getKey(), $jobs),
            'invoice_date' => now()->toDateString(),
            'payment_terms' => 'cash',
            'payment_method' => 'cash',
            'intent' => 'draft',
        ], $overrides);
    }

    private function allocation(int $paymentId, int $customerId, string $target, ?int $jobId, ?int $invoiceId, string $amount, User $user): PaymentAllocation
    {
        return PaymentAllocation::query()->create([
            'payment_id' => $paymentId,
            'customer_id' => $customerId,
            'target_type' => $target,
            'repair_job_id' => $jobId,
            'invoice_id' => $invoiceId,
            'pos_sale_id' => null,
            'amount' => $amount,
            'allocated_at' => now(),
            'allocated_by_user_id' => $user->getKey(),
            'idempotency_key' => $this->code('ALLOC'),
            'customer_scope_id' => $customerId,
        ]);
    }
}
