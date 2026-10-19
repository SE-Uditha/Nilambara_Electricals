<?php

namespace App\Modules\Customers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CreditManagement\Models\CustomerAccountEntry;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Requests\StoreCustomerRequest;
use App\Modules\Customers\Requests\UpdateCustomerRequest;
use App\Modules\Customers\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->get(['id', 'customer_code', 'customer_type', 'customer_name', 'company_name', 'mobile_number']);

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

    public function statement(Customer $customer): View
    {
        Gate::authorize('statement', $customer);

        return view('modules.customers.statement', [
            'customer' => $customer,
            'entries' => CustomerAccountEntry::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->paginate(30),
            'accountBalance' => $customer->customerAccountEntries()->sum('amount'),
        ]);
    }
}
