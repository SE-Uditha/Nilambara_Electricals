<?php

namespace App\Modules\Invoices\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\JobStatus;
use App\Enums\PaymentTerms;
use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Models\InvoiceJob;
use App\Modules\Invoices\Requests\CancelInvoiceRequest;
use App\Modules\Invoices\Requests\StoreInvoiceRequest;
use App\Modules\Invoices\Requests\UpdateInvoiceRequest;
use App\Modules\Invoices\Services\InvoiceBalanceService;
use App\Modules\Invoices\Services\InvoicePrintService;
use App\Modules\Invoices\Services\InvoiceService;
use App\Modules\RepairJobs\Models\RepairJob;
use App\Models\User;
use App\Modules\Payments\Services\PaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Brick\Math\BigDecimal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request, InvoiceBalanceService $balances): View
    {
        Gate::authorize('viewAny', Invoice::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', Rule::enum(DocumentStatus::class)],
            'payment_status' => ['nullable', Rule::in(['unpaid', 'partially_paid', 'paid', 'outstanding'])],
            'payment_terms' => ['nullable', Rule::enum(PaymentTerms::class)],
        ]);
        $query = Invoice::query()->with(['customer', 'cashierEmployee', 'invoiceJobs.repairJob'])
            ->orderByDesc('invoice_date')->orderByDesc('id');

        if (($term = trim((string) ($filters['q'] ?? ''))) !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('invoice_number', 'like', $like)
                    ->orWhereHas('customer', function ($customer) use ($like): void {
                        $customer->where('customer_name', 'like', $like)
                            ->orWhere('company_name', 'like', $like)
                            ->orWhere('mobile_number', 'like', $like);
                    })
                    ->orWhereHas('invoiceJobs.repairJob', function ($job) use ($like): void {
                        $job->where('job_number', 'like', $like)
                            ->orWhere('automatic_number', 'like', $like);
                    });
            });
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('invoice_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('invoice_date', '<=', $filters['date_to']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['payment_terms'])) {
            $query->where('payment_terms', $filters['payment_terms']);
        }
        if (! empty($filters['payment_status'])) {
            $balances->constrainPaymentStatus($query, $filters['payment_status']);
        }

        $invoices = $query->paginate(20)->withQueryString();
        foreach ($invoices as $invoice) {
            $invoice->setAttribute('payment_summary', $balances->summary($invoice));
        }

        $readyBills = ! $request->user()->can('invoices.create') ? collect() : RepairJob::query()->with('customer')
            ->whereIn('status', JobStatus::billableValues())
            ->whereDoesntHave('invoiceJobs')
            ->where('total_job_amount', '>', 0)
            ->orderByDesc('updated_at')->limit(50)->get();

        return view('modules.invoices.index', ['invoices' => $invoices, 'billingJobs' => $readyBills, 'billingQueueTitle' => 'Saved bills ready to invoice', 'billingQueueHelp' => 'Bills prepared from Payments that have no invoice yet. Create the invoice to bill the customer.']);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Invoice::class);
        $selectedCustomer = $request->old('customer_id', $request->query('customer_id'))
            ? Customer::query()->whereKey($request->old('customer_id', $request->query('customer_id')))->where('is_active', true)->first()
            : null;
        $oldJobIds = array_values(array_unique(array_map('intval', (array) $request->old('repair_job_ids', $request->query('repair_job_ids', [])))));
        $selectedJobs = $selectedCustomer === null || $oldJobIds === [] ? collect() : RepairJob::query()
            ->where('customer_id', $selectedCustomer->getKey())
            ->whereIn('id', $oldJobIds)
            ->whereIn('status', JobStatus::billableValues())
            ->whereDoesntHave('invoiceJobs')
            ->get()
            ->map(fn (RepairJob $job): array => [
                'id' => $job->getKey(),
                'job_number' => $job->automatic_number ?: $job->job_number,
                'item_name' => $job->item_name,
                'description' => $job->repair_description ?: $job->item_description ?: $job->reported_fault,
                'status' => $job->status->value,
                'total' => (string) $job->total_job_amount,
            ])
            ->all();

        return view('modules.invoices.create', [
            'invoice' => null,
            'selectedCustomer' => $selectedCustomer,
            'selectedJobs' => $selectedJobs,
            'invoiceDate' => now(config('business.timezone', 'Asia/Colombo'))->toDateString(),
        ]);
    }

    public function eligibleJobs(Customer $customer): JsonResponse
    {
        Gate::authorize('view', $customer);
        $jobs = RepairJob::query()
            ->where('customer_id', $customer->getKey())
            ->whereIn('status', JobStatus::billableValues())
            ->whereDoesntHave('invoiceJobs')
            ->with(['quotations' => fn ($query) => $query
                ->where('preparation_status', 'issued')
                ->where('approval_status', 'approved')
                ->orderByDesc('revision')])
            ->orderBy('received_at')
            ->get();

        return response()->json(['data' => $jobs->map(fn (RepairJob $job): array => [
            'id' => $job->getKey(),
            'job_number' => $job->automatic_number ?: $job->job_number,
            'item_name' => $job->item_name,
            'description' => $job->repair_description ?: $job->item_description ?: $job->reported_fault,
            'status' => $job->status->value,
            'labour_charge' => (string) $job->labour_charge,
            'parts_charge' => (string) $job->parts_charge,
            'total' => (string) $job->total_job_amount,
            'approved_quotation' => $job->quotations->first()?->quotation_number,
        ])->all()]);
    }

    public function store(StoreInvoiceRequest $request, InvoiceService $service, InvoiceBalanceService $balances, PaymentService $payments): RedirectResponse
    {
        $data = $request->validated();
        $intent = $data['intent'];
        $user = $request->user();
        // An invoice is only created together with its payment record, so new invoices are always issued.
        if (! $user->can('invoices.issue')) {
            abort(403);
        }
        if ($intent === 'preview' && ! $user->can('invoices.print')) {
            abort(403);
        }
        $data['intent'] = 'issue';
        $invoice = DB::transaction(function () use ($data, $user, $service, $balances, $payments): Invoice {
            $invoice = $service->create($data, $user);
            $this->recordInitialPayment($invoice, $data, $user, $balances, $payments);

            return $invoice;
        }, 3);

        if ($intent === 'preview') {
            return redirect()->route('invoices.print', $invoice);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', __('invoices.saved'));
    }

    /** Credit invoices are tracked as outstanding; every other invoice needs the amount received now. */
    private function recordInitialPayment(Invoice $invoice, array $data, User $user, InvoiceBalanceService $balances, PaymentService $payments): void
    {
        if ($invoice->payment_terms === PaymentTerms::Credit) {
            return;
        }
        $outstanding = BigDecimal::of($balances->summary($invoice)['outstanding']);
        if ($outstanding->isLessThanOrEqualTo(0)) {
            return;
        }

        $amount = trim((string) ($data['amount_received'] ?? ''));
        if ($amount === '' || BigDecimal::of($amount)->isLessThanOrEqualTo(0)) {
            throw ValidationException::withMessages(['amount_received' => __('invoices.payment_required')]);
        }
        $method = $invoice->payment_method?->value ?? 'cash';
        if (in_array($method, ['bank_transfer', 'cheque'], true) && trim((string) ($data['reference_number'] ?? '')) === '') {
            throw ValidationException::withMessages(['reference_number' => __('invoices.reference_required')]);
        }
        if (! $user->can('payments.create') || ! $user->can('payments.receive')) {
            throw ValidationException::withMessages(['amount_received' => __('invoices.no_payment_permission')]);
        }

        $payments->receiveInvoicePayment([
            'invoice_id' => $invoice->getKey(),
            'amount' => $amount,
            'method' => $method,
            'payment_date' => now(config('business.timezone', 'Asia/Colombo'))->toDateString(),
            'reference_number' => $data['reference_number'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'idempotency_key' => $data['payment_idempotency_key'] ?? (string) Str::uuid(),
        ], $user);
    }

    public function show(Invoice $invoice, InvoiceBalanceService $balances): View
    {
        Gate::authorize('view', $invoice);
        $invoice->load([
            'customer',
            'cashierEmployee',
            'invoiceJobs.repairJob',
            'invoiceJobs.invoiceItems',
            'history.actor',
            'documentPrintHistories.printedByUser',
        ]);

        return view('modules.invoices.show', [
            'invoice' => $invoice,
            'balance' => $balances->summary($invoice),
            'document' => $invoice->document_snapshot ?: [],
        ]);
    }

    public function edit(Invoice $invoice): View
    {
        Gate::authorize('updateDraft', $invoice);
        $invoice->load(['customer', 'invoiceJobs.repairJob']);
        $selectedJobs = $invoice->invoiceJobs->map(fn (InvoiceJob $link): array => [
            'id' => $link->repair_job_id,
            'job_number' => data_get($link->job_snapshot, 'job_number'),
            'item_name' => data_get($link->job_snapshot, 'item_name'),
            'description' => data_get($link->job_snapshot, 'repair_description'),
            'status' => data_get($link->job_snapshot, 'status'),
            'total' => (string) $link->job_amount,
        ])->all();

        return view('modules.invoices.create', [
            'invoice' => $invoice,
            'selectedCustomer' => $invoice->customer,
            'selectedJobs' => $selectedJobs,
            'invoiceDate' => $invoice->invoice_date->toDateString(),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        $data = $request->validated();
        $intent = $data['intent'];
        if ($intent === 'issue' && ! $request->user()->can('invoices.issue')) {
            abort(403);
        }
        if ($intent === 'preview' && ! $request->user()->can('invoices.print')) {
            abort(403);
        }
        $data['intent'] = $intent === 'issue' ? 'issue' : 'draft';
        $updated = $service->updateDraft($invoice, $data, $request->user());

        if ($intent === 'preview') {
            return redirect()->route('invoices.print', $updated);
        }

        return redirect()->route('invoices.show', $updated)->with('status', __('invoices.saved'));
    }

    public function issue(Request $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        Gate::authorize('issue', $invoice);
        $issued = $service->issue($invoice, $request->user());

        return redirect()->route('invoices.show', $issued)->with('status', __('invoices.issued'));
    }

    public function cancel(CancelInvoiceRequest $request, Invoice $invoice, InvoiceService $service): RedirectResponse
    {
        Gate::authorize('cancel', $invoice);
        $cancelled = $service->cancel($invoice, $request->validated('reason'), $request->user());

        return redirect()->route('invoices.show', $cancelled)->with('status', __('invoices.cancelled'));
    }

    public function print(Invoice $invoice, InvoicePrintService $printer): View
    {
        Gate::authorize('print', $invoice);
        $data = $printer->prepare($invoice, false, false, request()->user());

        return $printer->render($data);
    }

    public function duplicate(Invoice $invoice, InvoicePrintService $printer): View
    {
        Gate::authorize('print', $invoice);
        $data = $printer->prepare($invoice, true, false, request()->user());

        return $printer->render($data);
    }

    public function pdf(Invoice $invoice, InvoicePrintService $printer)
    {
        Gate::authorize('export', $invoice);
        $data = $printer->prepare($invoice, false, true, request()->user());

        return Pdf::loadView('modules.invoices.print', $data)
            ->setPaper($data['paperSize'], $data['orientation'])
            ->download($invoice->invoice_number.'.pdf');
    }
}
