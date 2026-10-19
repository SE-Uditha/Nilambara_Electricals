<?php

namespace App\Modules\CreditManagement\Controllers;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Invoices\Models\Invoice;
use App\Modules\Invoices\Services\InvoiceBalanceService;
use App\Modules\Payments\Models\PaymentAllocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CreditInvoiceController extends Controller
{
    public function index(Request $request, InvoiceBalanceService $balances): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'payment_status' => ['nullable', Rule::in(['all', 'outstanding', 'unpaid', 'partially_paid', 'paid'])],
        ]);
        $query = Invoice::query()->with('customer')
            ->where('status', DocumentStatus::Finalized->value)
            ->where('payment_terms', 'credit')
            ->orderByDesc('invoice_date')->orderByDesc('id');
        if (($term = trim((string) ($filters['q'] ?? ''))) !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('invoice_number', 'like', $like)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('customer_name', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('mobile_number', 'like', $like));
            });
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('invoice_date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('invoice_date', '<=', $filters['date_to']);
        }
        $balances->constrainPaymentStatus($query, $filters['payment_status'] ?? 'outstanding');
        $invoices = $query->paginate(20)->withQueryString();
        foreach ($invoices as $invoice) {
            $invoice->setAttribute('payment_summary', $balances->summary($invoice));
        }

        return view('modules.credit-management.index', [
            'invoices' => $invoices,
            'filters' => $filters,
            'customers' => Customer::query()->orderBy('customer_code')->get(),
        ]);
    }

    public function show(Invoice $invoice, InvoiceBalanceService $balances): View
    {
        abort_unless($invoice->status === DocumentStatus::Finalized && $invoice->payment_terms?->value === 'credit', 404);
        $invoice->load(['customer', 'invoiceJobs.repairJob']);
        $jobIds = $invoice->invoiceJobs->pluck('repair_job_id');
        $allocations = PaymentAllocation::query()
            ->with(['payment.receivedByEmployee', 'releases.releasedByUser', 'allocatedByUser'])
            ->where(function ($query) use ($invoice, $jobIds): void {
                $query->where('target_type', 'invoice')->where('invoice_id', $invoice->getKey());
                if ($jobIds->isNotEmpty()) {
                    $query->orWhere(function ($advance) use ($jobIds): void {
                        $advance->where('target_type', 'job_advance')->whereIn('repair_job_id', $jobIds);
                    });
                }
            })
            ->orderBy('allocated_at')
            ->orderBy('id')
            ->get();

        return view('modules.credit-management.show', [
            'invoice' => $invoice,
            'balance' => $balances->summary($invoice),
            'allocations' => $allocations,
        ]);
    }

    public function collect(Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->status === DocumentStatus::Finalized && $invoice->payment_terms?->value === 'credit', 404);
        abort_unless(auth()->user()->can('credit.collect'), 403);

        return redirect()->route('payments.create', ['invoice_id' => $invoice->getKey()]);
    }
}
