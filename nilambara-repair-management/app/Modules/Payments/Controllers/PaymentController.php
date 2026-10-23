<?php

namespace App\Modules\Payments\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\JobStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentTerms;
use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Employees\Models\Employee;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Services\InvoiceBalanceService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Requests\ReversePaymentRequest;
use App\Modules\Payments\Requests\StorePaymentRequest;
use App\Modules\Payments\Services\PaymentBalanceService;
use App\Modules\Payments\Services\PaymentPrintService;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\RepairJobs\Models\RepairJob;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request, PaymentBalanceService $balances): View
    {
        Gate::authorize('viewAny', Payment::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'payment_type' => ['nullable', Rule::in(['advance', 'invoice', 'credit_collection'])],
            'status' => ['nullable', Rule::in(['unallocated', 'partially_allocated', 'allocated', 'partially_reversed', 'reversed'])],
        ]);
        $query = Payment::query()->with([
            'customer',
            'receivedByEmployee',
            'paymentAllocations.invoice',
            'paymentAllocations.repairJob',
            'paymentReversals',
        ])
            ->whereIn('payment_type', ['advance', 'invoice', 'credit_collection'])
            ->orderByDesc('paid_at')->orderByDesc('id');

        if (($term = trim((string) ($filters['q'] ?? ''))) !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('payment_number', 'like', $like)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('customer_name', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('mobile_number', 'like', $like))
                    ->orWhereHas('paymentAllocations.invoice', fn ($invoice) => $invoice->where('invoice_number', 'like', $like))
                    ->orWhereHas('paymentAllocations.repairJob', fn ($job) => $job->where('job_number', 'like', $like)
                        ->orWhere('automatic_number', 'like', $like));
            });
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('paid_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('paid_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }
        if (! empty($filters['payment_type'])) {
            $query->where('payment_type', $filters['payment_type']);
        }
        if (! empty($filters['status'])) {
            $balances->constrainStatus($query, $filters['status']);
        }

        $payments = $query->paginate(20)->withQueryString();
        foreach ($payments as $payment) {
            $payment->setAttribute('balance_summary', $balances->summary($payment));
        }

        return view('modules.payments.index', ['payments' => $payments, 'billingJobs' => $this->billingQueue($request)]);
    }

    private function billingQueue(Request $request)
    {
        if (! $request->user()->can('invoices.create')) {
            return collect();
        }

        return RepairJob::query()->with('customer')
            ->whereIn('status', JobStatus::billableValues())
            ->whereDoesntHave('invoiceJobs')
            ->orderByDesc('updated_at')->limit(50)->get();
    }

    public function create(Request $request, InvoiceBalanceService $balances, bool $creditCollection = false): View
    {
        Gate::authorize('create', Payment::class);
        $filters = $request->validate([
            'invoice_q' => ['nullable', 'string', 'max:120'],
        ]);
        $selectedInvoice = null;
        $invoiceId = $request->integer('invoice_id');
        if ($invoiceId > 0) {
            $selectedInvoice = Invoice::query()->with('customer')->whereKey($invoiceId)->firstOrFail();
            abort_unless($selectedInvoice->status === DocumentStatus::Finalized, 404);
            $selectedInvoice->setAttribute('payment_summary', $balances->summary($selectedInvoice));
            abort_unless($selectedInvoice->payment_summary['outstanding'] !== '0.00', 404);
            $creditCollection = $selectedInvoice->payment_terms === PaymentTerms::Credit;
        }
        if ($creditCollection) {
            Gate::authorize('credit.collect');
        }
        $customers = Customer::query()->where('is_active', true)->orderBy('customer_code')->get();
        $invoiceQuery = Invoice::query()->with('customer')
            ->where('status', DocumentStatus::Finalized->value)
            ->where('total', '>', 0);
        $search = trim((string) ($filters['invoice_q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $invoiceQuery->where(function ($builder) use ($like): void {
                $builder->where('invoice_number', 'like', $like)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('customer_name', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('mobile_number', 'like', $like));
            });
        }
        $openInvoices = $balances->constrainPaymentStatus($invoiceQuery, 'outstanding')
            ->orderByDesc('invoice_date')->orderByDesc('id')->limit(100)->get();

        return view('modules.payments.create', [
            'invoice' => $selectedInvoice,
            'invoiceSearch' => $search,
            'customers' => $customers,
            'employees' => auth()->user()->can('payments.receive-for-others')
                ? Employee::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'currentEmployee' => auth()->user()->employee,
            'openInvoices' => $openInvoices,
            'jobs' => collect(),
            'idempotencyKey' => old('idempotency_key', (string) Str::uuid()),
            'creditCollection' => $creditCollection,
        ]);
    }

    public function jobs(Customer $customer): JsonResponse
    {
        Gate::authorize('create', Payment::class);
        $jobs = RepairJob::query()
            ->where('customer_id', $customer->getKey())
            ->whereNull('cancelled_at')
            ->with('invoiceJobs.invoice')
            ->orderByDesc('received_at')
            ->limit(100)
            ->get()
            ->map(fn (RepairJob $job): array => [
                'id' => $job->getKey(),
                'number' => $job->automatic_number ?: $job->job_number,
                'item' => $job->item_name,
                'status' => $job->status->value,
                'has_invoice' => $job->invoiceJobs->isNotEmpty(),
            ]);

        return response()->json(['data' => $jobs]);
    }

    public function store(StorePaymentRequest $request, PaymentService $service): RedirectResponse
    {
        $data = $request->validated();
        if ($data['payment_type'] === 'invoice') {
            $payment = $service->receiveInvoicePayment($data, $request->user());
        } else {
            $job = empty($data['repair_job_id'])
                ? null
                : RepairJob::query()->findOrFail($data['repair_job_id']);
            $payment = $service->receiveAdvance($data, $request->user(), $job);
        }

        return redirect()->route('payments.show', $payment)->with('status', __('payments.payment_saved'));
    }

    public function show(Payment $payment, PaymentBalanceService $balances): View
    {
        Gate::authorize('view', $payment);
        $payment->load([
            'customer',
            'receivedByEmployee',
            'paymentAllocations.invoice.customer',
            'paymentAllocations.repairJob',
            'paymentAllocations.allocatedByUser',
            'paymentAllocations.releases.releasedByUser',
            'paymentReversals.reversedByUser',
        ]);

        return view('modules.payments.show', [
            'payment' => $payment,
            'balance' => $balances->summary($payment),
        ]);
    }

    public function reverse(ReversePaymentRequest $request, Payment $payment, PaymentService $service): RedirectResponse
    {
        Gate::authorize('reverse', $payment);
        $service->reverse($payment, $request->validated(), $request->user());

        return redirect()->route('payments.show', $payment)->with('status', __('payments.reversal_saved'));
    }

    public function print(Payment $payment, PaymentPrintService $printer): View
    {
        Gate::authorize('print', $payment);

        return $printer->render($printer->prepare($payment, false, false, request()->user()));
    }

    public function duplicate(Payment $payment, PaymentPrintService $printer): View
    {
        Gate::authorize('print', $payment);

        return $printer->render($printer->prepare($payment, true, false, request()->user()));
    }

    public function pdf(Payment $payment, PaymentPrintService $printer)
    {
        Gate::authorize('export', $payment);
        $data = $printer->prepare($payment, false, true, request()->user());

        return Pdf::loadView('modules.payments.print', $data)
            ->setPaper($data['paperSize'], $data['orientation'])
            ->download($payment->payment_number.'.pdf');
    }
}
