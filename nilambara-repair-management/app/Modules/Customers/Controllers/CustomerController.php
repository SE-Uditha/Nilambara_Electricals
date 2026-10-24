<?php

namespace App\Modules\Customers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Approvals\Services\ApprovalAccess;
use App\Modules\Approvals\Services\ApprovalService;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Requests\StoreCustomerRequest;
use App\Modules\Customers\Requests\RequestCustomerEditRequest;
use App\Modules\Customers\Requests\UpdateCustomerRequest;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Customers\Services\CustomerStatementService;
use App\Modules\PrintTemplates\Models\DocumentPrintHistory;
use App\Modules\PrintTemplates\Models\PrintTemplate;
use App\Support\BusinessAudit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $query = Customer::query()->orderByDesc('id');
        $search = trim((string) $request->query('q'));

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $term = '%'.$search.'%';
                $builder->where('customer_code', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('company_name', 'like', $term)
                    ->orWhere('mobile_number', 'like', $term);
            });
        }

        if (in_array($request->query('type'), ['individual', 'company'], true)) {
            $query->where('customer_type', $request->query('type'));
        }

        if ($request->query('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->query('status') === 'inactive') {
            $query->where('is_active', false);
        }

        return view('modules.customers.index', [
            'customers' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Customer::class);

        return view('modules.customers.create');
    }

    public function store(StoreCustomerRequest $request, CustomerService $customers): RedirectResponse
    {
        $customer = $customers->register($request->validated(), $request->user());

        return redirect()->route('customers.show', $customer)
            ->with('status', __('ui.customer_registered'));
    }

    public function search(Request $request): JsonResponse
    {
        Gate::authorize('search', Customer::class);

        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $customers = Customer::query()
            ->where('is_active', true)
            ->where(function ($builder) use ($term): void {
                $like = '%'.$term.'%';
                $builder->where('customer_code', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('mobile_number', 'like', $like);
            })
            ->orderBy('customer_code')
            ->limit(10)
            ->get(['id', 'customer_code', 'customer_type', 'customer_name', 'company_name', 'contact_person', 'address', 'mobile_number']);

        return response()->json([
            'data' => $customers->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'name' => $customer->display_name,
                'type' => $customer->customer_type->label(),
                'contact_person' => $customer->contact_person,
                'address' => $customer->address,
                'mobile_number' => $customer->mobile_number,
                'url' => route('customers.show', $customer),
            ])->all(),
        ]);
    }

    public function show(Customer $customer): View
    {
        Gate::authorize('view', $customer);
        $customer->loadCount(['repairJobs']);

        $accountBalance = null;

        if (request()->user()->can('statement', $customer)) {
            $customer->loadCount(['invoices', 'payments']);
            $accountBalance = $customer->customerAccountEntries()->sum('amount');
        }

        return view('modules.customers.show', [
            'customer' => $customer,
            'accountBalance' => $accountBalance,
        ]);
    }

    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('modules.customers.edit', ['customer' => $customer]);
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        CustomerService $customers,
    ): RedirectResponse {
        $customer = $customers->update($customer, $request->validated(), $request->user());

        return redirect()->route('customers.show', $customer)
            ->with('status', __('ui.customer_updated'));
    }

    public function requestEdit(Request $request, Customer $customer): View
    {
        Gate::authorize('view', $customer);
        abort_unless(ApprovalAccess::canRequestChange($request->user(), 'customer_update'), 403);

        return view('modules.customers.edit', ['customer' => $customer, 'requestMode' => true]);
    }

    public function storeEditRequest(RequestCustomerEditRequest $request, Customer $customer, ApprovalService $approvals): RedirectResponse
    {
        Gate::authorize('view', $customer);
        $data = $request->validated();
        $approvals->submitChange('customer_update', $customer, $data, $data['reason'], $data['idempotency_key'], $request->user());

        return redirect()->route('customers.show', $customer)->with('status', __('ui.edit_request_submitted'));
    }

    public function activate(Request $request, Customer $customer, CustomerService $customers): RedirectResponse
    {
        Gate::authorize('activate', $customer);
        $customers->setActive($customer, true, $request->user());

        return back()->with('status', __('ui.customer_activated'));
    }

    public function deactivate(Request $request, Customer $customer, CustomerService $customers): RedirectResponse
    {
        Gate::authorize('deactivate', $customer);
        $customers->setActive($customer, false, $request->user());

        return back()->with('status', __('ui.customer_deactivated'));
    }

    public function statement(Request $request, Customer $customer, CustomerStatementService $statements): View
    {
        Gate::authorize('statement', $customer);

        $filters = $this->statementFilters($request);

        return view('modules.customers.statement', [
            'customer' => $customer,
            ...$statements->data($customer, $filters, true),
            'filters' => $filters,
        ]);
    }

    public function statementPrint(Request $request, Customer $customer, CustomerStatementService $statements): View
    {
        Gate::authorize('statement', $customer);
        $filters = $this->statementFilters($request);

        $data = [
            'customer' => $customer,
            ...$statements->data($customer, $filters, false),
            'filters' => $filters,
            'printedAt' => now(config('business.timezone', 'Asia/Colombo')),
            ...$this->statementTemplateData(),
        ];
        $this->recordStatementPrint($customer, $data, false);

        return view('modules.customers.statement-print', $data);
    }

    public function statementPdf(Request $request, Customer $customer, CustomerStatementService $statements)
    {
        Gate::authorize('statement', $customer);
        $filters = $this->statementFilters($request);
        $layout = $this->statementTemplateData(true);

        $data = [
            'customer' => $customer,
            ...$statements->data($customer, $filters, false),
            'filters' => $filters,
            'printedAt' => now(config('business.timezone', 'Asia/Colombo')),
            ...$layout,
        ];
        $this->recordStatementPrint($customer, $data, true);

        return Pdf::loadView('modules.customers.statement-print', $data)->setPaper($layout['paperSize'], $layout['orientation'])->download($customer->customer_code.'-statement.pdf');
    }

    private function recordStatementPrint(Customer $customer, array $data, bool $pdf): void
    {
        DB::transaction(function () use ($customer, $data, $pdf) {
            DocumentPrintHistory::create([
                'print_template_id' => $data['template']?->id, 'customer_id' => $customer->id, 'printed_at' => $data['printedAt'], 'printed_by_user_id' => auth()->id(),
                'is_duplicate' => $data['duplicate'], 'duplicate_label' => $data['duplicate'] ? $data['duplicateLabel'] : null,
                'output_reference' => $pdf ? $customer->customer_code.'-statement.pdf' : null,
                'document_snapshot' => ['customer_code' => $customer->customer_code, 'customer_name' => $customer->display_name, 'filters' => $data['filters'], 'closing_balance' => $data['closingBalance'], 'entries' => $data['entries']->toArray(), 'paper_size' => $data['paperSize'], 'orientation' => $data['orientation'], 'export_format' => $pdf ? 'pdf' : 'html'],
            ]);
            BusinessAudit::record(auth()->user(), $pdf ? 'customer.statement.pdf_exported' : 'customer.statement.print_prepared', Customer::class, $customer->id, newValues: ['filters' => $data['filters'], 'duplicate' => $data['duplicate']]);
        });
    }

    /**
     * @return array{date_from: string|null, date_to: string|null}
     */
    private function statementTemplateData(bool $pdf = false): array
    {
        $template = PrintTemplate::where('document_type', 'customer_statement')
            ->where('is_active', true)->orderByDesc('version')->first();
        $logoDataUri = null;
        $path = realpath(public_path($template?->logo_path ?: config('business.logo', '')));
        $public = realpath(public_path());
        if ($path && $public && str_starts_with($path, $public.DIRECTORY_SEPARATOR) && is_file($path)) {
            $mime = mime_content_type($path);
            if (in_array($mime, ['image/png', 'image/jpeg'], true)) {
                $logoDataUri = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
            }
        }

        $paperSize = in_array($template?->paper_size, ['A4', 'A5', 'A6'], true) ? $template->paper_size : 'A4';
        $orientation = in_array($template?->orientation, ['portrait', 'landscape'], true) ? $template->orientation : 'landscape';
        $documentTitle = $template?->document_title ?: __('ui.customer_statement');
        $duplicate = request()->boolean('duplicate');
        $duplicateLabel = $template?->duplicate_copy_label ?: __('ui.duplicate_copy');
        $fontRegularPath = $pdf ? str_replace('\\', '/', public_path('fonts/NotoSansSinhala-Regular.ttf')) : asset('fonts/NotoSansSinhala-Regular.ttf');
        $fontBoldPath = $pdf ? str_replace('\\', '/', public_path('fonts/NotoSansSinhala-Bold.ttf')) : asset('fonts/NotoSansSinhala-Bold.ttf');

        return compact('template', 'logoDataUri', 'paperSize', 'orientation', 'documentTitle', 'fontRegularPath', 'fontBoldPath', 'duplicate', 'duplicateLabel');
    }

    private function statementFilters(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }
}
