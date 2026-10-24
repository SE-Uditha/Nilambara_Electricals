<?php

namespace Tests\Feature;

use App\Enums\JobStatus;
use App\Models\User;
use App\Modules\CreditManagement\Models\CustomerAccountEntry;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Services\InvoiceBalanceService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentAllocation;
use App\Modules\Payments\Services\PaymentAllocationService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\RepairReceipts\Services\RepairReceiptService;
use App\Modules\RolesPermissions\Models\Permission;
use Brick\Math\BigDecimal;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Database\CreatesDatabaseRecords;
use Tests\TestCase;

class PaymentManagementTest extends TestCase
{
    use CreatesDatabaseRecords;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_cash_bank_and_cheque_partial_payments_settle_an_invoice(): void
    {
        $cashier = $this->userWithRole('cashier');
        $customer = $this->customer();
        $invoice = $this->issuedInvoice($cashier, $customer, '100.00');

        $this->postPayment($cashier, $invoice, '30.00', 'cash')
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->postPayment($cashier, $invoice, '20.00', 'bank_transfer', ['reference_number' => 'BANK-001'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->postPayment($cashier, $invoice, '50.00', 'cheque', [
            'reference_number' => 'CHQ-001',
            'cheque_date' => now()->toDateString(),
        ])->assertSessionHasErrors('method');
        $this->postPayment($cashier, $invoice, '50.00', 'bank_transfer', ['reference_number' => 'BANK-002'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $summary = app(InvoiceBalanceService::class)->summary($invoice->fresh());
        $this->assertSame('100.00', $summary['total_paid']);
        $this->assertSame('0.00', $summary['outstanding']);
        $this->assertSame('paid', $summary['payment_status']);
        $this->assertDatabaseCount('payments', 3);
        $this->assertDatabaseCount('customer_account_entries', 4);
        $this->assertDatabaseHas('payments', ['method' => 'bank_transfer', 'bank_reference' => 'BANK-001']);

        $bankPayment = Payment::query()->where('bank_reference', 'BANK-001')->firstOrFail();
        $this->actingAs($cashier)->get(route('payments.index'))->assertOk()->assertSee($bankPayment->payment_number);
        $this->actingAs($cashier)->get(route('credit-invoices.index'))->assertOk();
    }

    public function test_customer_advance_is_applied_once_when_invoice_is_issued(): void
    {
        $cashier = $this->userWithRole('cashier');
        $customer = $this->customer();
        $advanceData = [
            'customer_id' => $customer->getKey(),
            'amount' => '25.00',
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ];
        $advance = app(PaymentService::class)->receiveAdvance($advanceData, $cashier);
        $invoice = $this->issuedInvoice($cashier, $customer, '100.00');

        $this->assertSame('75.00', app(InvoiceBalanceService::class)->summary($invoice)['outstanding']);
        $this->assertDatabaseCount('payment_allocations', 1);
        app(PaymentAllocationService::class)->applyAvailableAdvances($invoice, $cashier);
        $retry = app(PaymentService::class)->receiveAdvance($advanceData, $cashier);
        $this->assertSame($advance->getKey(), $retry->getKey());
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_allocations', 1);
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $advance->getKey(),
            'invoice_id' => $invoice->getKey(),
            'amount' => '25.00',
        ]);
        $this->assertSame('-25.00', CustomerAccountEntry::query()->where('payment_id', $advance->getKey())->value('amount'));
    }

    public function test_job_advance_is_linked_to_the_customer_job_and_printed_receipt(): void
    {
        $cashier = $this->userWithRole('cashier');
        $customer = $this->customer();
        $job = $this->job($customer);
        $payment = app(PaymentService::class)->receiveAdvance([
            'amount' => '40.00',
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ], $cashier, $job);

        $allocation = PaymentAllocation::query()->sole();
        $this->assertSame($job->getKey(), $allocation->repair_job_id);
        $this->assertSame($customer->getKey(), $payment->customer_id);
        $this->actingAs($cashier)->get(route('payments.print', $payment))
            ->assertOk()
            ->assertSee($payment->payment_number)
            ->assertSee($job->automatic_number ?: $job->job_number);
    }

    public function test_repair_receipt_records_authorized_advance_and_snapshots_payment_reference(): void
    {
        $administrator = $this->userWithRole('administrator');
        $employee = $administrator->employee;
        $customer = $this->customer();

        $receipt = app(RepairReceiptService::class)->register([
            'customer_id' => $customer->getKey(),
            'received_by_employee_id' => $employee->getKey(),
            'job_number' => 'RECEIPT-ADV-1',
            'item_name' => 'Test appliance',
            'brand' => 'Test brand',
            'job_type' => 'normal',
            'labor_cost' => '0.00',
            'advance_payment' => '15.00',
            'payment_method' => 'cash',
        ], $administrator);

        $payment = Payment::query()->sole();
        $this->assertSame($payment->payment_number, data_get($receipt->document_snapshot, 'advance_payment_number'));
        $this->assertSame('15.00', $payment->amount);
        $this->assertSame('advance', $payment->payment_type);
        $this->assertDatabaseHas('payment_allocations', [
            'payment_id' => $payment->getKey(),
            'target_type' => 'job_advance',
            'repair_job_id' => $receipt->repair_job_id,
        ]);
        $this->actingAs($administrator)->get(route('repair-jobs.show', $receipt->repair_job_id))
            ->assertOk()
            ->assertSee($payment->payment_number);
    }

    public function test_idempotency_blocks_duplicate_invoice_payment_and_overpayment(): void
    {
        $cashier = $this->userWithRole('cashier');
        $customer = $this->customer();
        $invoice = $this->issuedInvoice($cashier, $customer, '100.00');
        $key = (string) Str::uuid();
        $data = [
            'payment_type' => 'invoice',
            'invoice_id' => $invoice->getKey(),
            'payment_date' => now()->toDateString(),
            'amount' => '60.00',
            'method' => 'cash',
            'idempotency_key' => $key,
        ];

        $this->actingAs($cashier)->post(route('payments.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $paymentId = Payment::query()->sole()->getKey();
        $this->actingAs($cashier)->post(route('payments.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($paymentId, Payment::query()->sole()->getKey());
        $this->assertDatabaseCount('payments', 1);

        $this->actingAs($cashier)->from(route('payments.create'))->post(route('payments.store'), [
            ...$data,
            'amount' => '40.01',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('payments.create'))->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_cross_customer_allocation_is_rejected_without_creating_an_allocation(): void
    {
        $manager = $this->userWithRole('cashier');
        $customer = $this->customer();
        $invoice = $this->issuedInvoice($manager, $customer, '100.00');
        $otherCustomer = $this->customer();
        $payment = $this->payment($otherCustomer, [
            'amount' => '20.00',
            'received_by_employee_id' => $manager->employee->getKey(),
        ]);

        try {
            app(PaymentAllocationService::class)->allocateToInvoice(
                $payment,
                $invoice,
                '20.00',
                $manager,
                (string) Str::uuid(),
            );
            $this->fail('Cross-customer allocation should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice_id', $exception->errors());
        }

        $this->assertDatabaseCount('payment_allocations', 0);
    }

    public function test_refund_and_full_accounting_reversal_restore_invoice_balance_and_log_entries(): void
    {
        $manager = $this->userWithRole('cashier');
        $customer = $this->customer();
        $invoice = $this->issuedInvoice($manager, $customer, '100.00');
        $payment = app(PaymentService::class)->receiveInvoicePayment($this->paymentData($invoice, '100.00'), $manager);

        app(PaymentService::class)->reverse($payment, [
            'reversal_type' => 'refund',
            'amount' => '25.00',
            'method' => 'cash',
            'reason' => 'Customer cash refund',
            'idempotency_key' => (string) Str::uuid(),
        ], $manager);
        $this->assertSame('25.00', app(InvoiceBalanceService::class)->summary($invoice->fresh())['outstanding']);
        $this->assertSame('25.00', (string) BigDecimal::of((string) $payment->paymentReversals()->sum('amount'))->toScale(2));
        $this->assertSame('25.00', CustomerAccountEntry::query()->where('payment_reversal_id', $payment->paymentReversals()->first()->getKey())->value('amount'));

        $remainingPayment = app(PaymentService::class)->receiveInvoicePayment($this->paymentData($invoice, '25.00'), $manager);
        app(PaymentService::class)->reverse($remainingPayment, [
            'reversal_type' => 'reversal',
            'amount' => '25.00',
            'reason' => 'Duplicate receipt fully reversed',
            'idempotency_key' => (string) Str::uuid(),
        ], $manager);

        $this->assertSame('25.00', app(InvoiceBalanceService::class)->summary($invoice->fresh())['outstanding']);
        $this->assertDatabaseHas('customer_account_entries', [
            'reverses_entry_id' => CustomerAccountEntry::query()->where('payment_id', $remainingPayment->getKey())->value('id'),
            'amount' => '25.00',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.allocation_released']);
    }

    public function test_only_authorized_roles_can_reverse_and_receiver_impersonation_is_restricted(): void
    {
        $cashier = $this->userWithRole('cashier');
        DB::table('permission_user')->insert(['user_id' => $cashier->id, 'permission_id' => Permission::findByName('payments.reverse')->id, 'model_type' => User::class, 'effect' => 'deny']);
        $customer = $this->customer();
        $invoice = $this->issuedInvoice($cashier, $customer, '100.00');
        $payment = app(PaymentService::class)->receiveInvoicePayment($this->paymentData($invoice, '20.00'), $cashier);
        $this->actingAs($cashier)->post(route('payments.reverse', $payment), [
            'reversal_type' => 'refund',
            'amount' => '20.00',
            'method' => 'cash',
            'reason' => 'Unauthorized refund attempt',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->actingAs($cashier)->from(route('payments.create'))->post(route('payments.store'), [
            ...$this->paymentData($invoice, '10.00'),
            'received_by_employee_id' => $this->employee(['is_active' => true])->getKey(),
        ])->assertRedirect(route('payments.create'))->assertSessionHasErrors('received_by_employee_id');
        $this->assertDatabaseCount('payment_reversals', 0);
    }

    public function test_customer_statement_uses_ledger_running_balance_and_pdf_route(): void
    {
        $cashier = $this->userWithRole('cashier');
        $customer = $this->customer(['opening_balance' => '15.00']);
        CustomerAccountEntry::query()->create([
            'customer_id' => $customer->getKey(),
            'entry_type' => 'opening',
            'amount' => '15.00',
            'occurred_at' => now()->subDays(3),
            'reference_number' => $customer->customer_code,
            'description' => 'Opening balance recorded for statement test.',
            'recorded_by_user_id' => $cashier->getKey(),
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $invoice = $this->issuedInvoice($cashier, $customer, '100.00');
        $this->postPayment($cashier, $invoice, '35.00', 'cash')->assertRedirect();

        $this->actingAs($cashier)->get(route('customers.statement', $customer))
            ->assertOk()
            ->assertSee('15.00')
            ->assertSee('80.00');
        $this->actingAs($cashier)->get(route('customers.statement.print', $customer))->assertOk()->assertSee($invoice->invoice_number);
        $this->actingAs($cashier)->get(route('customers.statement.pdf', $customer))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_credit_collections_are_permission_checked_and_support_repeated_partial_payments(): void
    {
        $cashier = $this->userWithRole('cashier');
        $customer = $this->customer();
        $invoice = $this->issuedInvoice($cashier, $customer, '100.00', 'credit');

        $this->actingAs($cashier)->get(route('payments.create', ['invoice_q' => $invoice->invoice_number]))
            ->assertOk()
            ->assertSee($invoice->invoice_number);
        $this->actingAs($cashier)->get(route('credit-invoices.collect', $invoice))
            ->assertRedirect(route('payments.create', ['invoice_id' => $invoice->getKey()]));
        $this->postPayment($cashier, $invoice, '30.00', 'cash')->assertRedirect()->assertSessionHasNoErrors();
        $this->postPayment($cashier, $invoice, '70.00', 'cash')->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('paid', app(InvoiceBalanceService::class)->summary($invoice->fresh())['payment_status']);
        $this->assertSame(2, Payment::query()->where('payment_type', 'credit_collection')->count());
        $this->actingAs($cashier)->get(route('credit-invoices.show', $invoice))
            ->assertOk()
            ->assertSee('100.00');
        $this->actingAs($cashier)->get(route('credit-invoices.index', ['payment_status' => 'all']))
            ->assertOk()
            ->assertSee($invoice->invoice_number);
    }

    public function test_partially_paid_cash_invoice_and_credit_method_invoice_appear_in_outstanding(): void
    {
        $cashier = $this->userWithRole('cashier');
        $cashInvoice = $this->issuedInvoice($cashier, $this->customer(), '100.00', 'cash');
        $unpaidCash = $this->issuedInvoice($cashier, $this->customer(), '80.00', 'cash');
        $creditInvoice = $this->issuedInvoice($cashier, $this->customer(), '60.00', 'credit');

        $this->actingAs($cashier)->get(route('credit-invoices.index'))->assertOk()
            ->assertSee($creditInvoice->invoice_number)->assertDontSee($cashInvoice->invoice_number);

        $this->postPayment($cashier, $cashInvoice, '40.00', 'cash')->assertSessionHasNoErrors();
        $this->get(route('credit-invoices.index'))->assertOk()
            ->assertSee($cashInvoice->invoice_number)->assertSee($creditInvoice->invoice_number)->assertDontSee($unpaidCash->invoice_number);
        $this->get(route('credit-invoices.show', $cashInvoice))->assertOk();
    }

    public function test_credit_payment_method_on_the_invoice_form_stores_credit_terms(): void
    {
        $cashier = $this->userWithRole('cashier');
        $invoice = $this->issuedInvoice($cashier, $this->customer(), '50.00', 'cash');
        $request = \App\Modules\Invoices\Requests\StoreInvoiceRequest::create('/', 'POST', ['payment_method' => 'credit']);
        $method = new \ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('credit', $request->input('payment_terms'));
        $this->assertNull($request->input('payment_method'));
        $this->assertNotNull($invoice);
    }

    public function test_invoice_is_only_created_together_with_its_payment_record(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer();
        $job = $this->job($customer, [
            'brand' => 'Test brand',
            'status' => JobStatus::RepairCompleted->value,
            'labour_charge' => '500.00',
            'parts_charge' => '0.00',
            'total_job_amount' => '500.00',
            'repair_description' => 'Test repair',
        ]);
        $data = [
            'customer_id' => $customer->getKey(),
            'repair_job_ids' => [$job->getKey()],
            'invoice_date' => now()->toDateString(),
            'payment_terms' => 'cash',
            'payment_method' => 'cash',
            'intent' => 'issue',
        ];

        $this->actingAs($user)->post(route('invoices.store'), $data)->assertSessionHasErrors('amount_received');
        $this->assertDatabaseCount('invoices', 0);

        $this->post(route('invoices.store'), $data + ['amount_received' => '600.00'])->assertSessionHasErrors();
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);

        $this->post(route('invoices.store'), $data + ['amount_received' => '200.00'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->actingAs($user)->get(route('credit-invoices.index'))->assertSee(Invoice::query()->first()->invoice_number);
    }

    public function test_credit_invoice_needs_no_payment_now(): void
    {
        $user = $this->userWithRole('administrator');
        $customer = $this->customer();
        $job = $this->job($customer, [
            'brand' => 'Test brand',
            'status' => JobStatus::RepairCompleted->value,
            'labour_charge' => '500.00',
            'parts_charge' => '0.00',
            'total_job_amount' => '500.00',
            'repair_description' => 'Test repair',
        ]);
        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->getKey(),
            'repair_job_ids' => [$job->getKey()],
            'invoice_date' => now()->toDateString(),
            'payment_method' => 'credit',
            'due_date' => now()->addDays(30)->toDateString(),
            'intent' => 'issue',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_credit_method_moves_invoice_balance_to_credit_management_without_a_payment(): void
    {
        $cashier = $this->userWithRole('cashier');
        $customer = $this->customer();
        $invoice = $this->issuedInvoice($cashier, $customer, '100.00');
        $this->postPayment($cashier, $invoice, '30.00', 'cash')->assertSessionHasNoErrors();

        $this->actingAs($cashier)->post(route('payments.store'), [
            'payment_type' => 'invoice',
            'invoice_id' => $invoice->getKey(),
            'payment_date' => now()->toDateString(),
            'method' => 'credit',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertRedirect(route('credit-invoices.show', $invoice))->assertSessionHasNoErrors();

        $this->assertSame('credit', $invoice->fresh()->payment_terms->value);
        $this->assertDatabaseCount('payments', 1);
        $this->actingAs($cashier)->post(route('payments.store'), [
            'payment_type' => 'invoice',
            'invoice_id' => $invoice->getKey(),
            'payment_date' => now()->toDateString(),
            'method' => 'credit',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('invoice_id');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->employee(['user_id' => $user->getKey(), 'is_active' => true]);

        return $user;
    }

    private function issuedInvoice(User $user, $customer, string $amount, string $terms = 'cash'): Invoice
    {
        $job = $this->job($customer, [
            'brand' => 'Test brand',
            'status' => JobStatus::RepairCompleted->value,
            'labour_charge' => $amount,
            'parts_charge' => '0.00',
            'total_job_amount' => $amount,
            'repair_description' => 'Test repair',
        ]);
        $this->actingAs($user);
        app(\App\Modules\Invoices\Services\InvoiceService::class)->create([
            'customer_id' => $customer->getKey(),
            'repair_job_ids' => [$job->getKey()],
            'invoice_date' => now()->toDateString(),
            'payment_terms' => $terms,
            'payment_method' => 'cash',
            'due_date' => $terms === 'credit' ? now()->addDays(30)->toDateString() : null,
            'intent' => 'issue',
        ], $user);

        return Invoice::query()->latest('id')->firstOrFail();
    }

    private function paymentData(Invoice $invoice, string $amount): array
    {
        return [
            'payment_type' => 'invoice',
            'invoice_id' => $invoice->getKey(),
            'payment_date' => now()->toDateString(),
            'amount' => $amount,
            'method' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    private function postPayment(User $user, Invoice $invoice, string $amount, string $method, array $extra = [])
    {
        return $this->actingAs($user)->post(route('payments.store'), [
            ...$this->paymentData($invoice, $amount),
            'method' => $method,
            ...$extra,
        ]);
    }
}
