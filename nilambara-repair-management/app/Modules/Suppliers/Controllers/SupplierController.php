<?php

namespace App\Modules\Suppliers\Controllers;

use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Models\SupplierAccountEntry;
use App\Modules\Suppliers\Services\SupplierPaymentService;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class SupplierController
{
    public function index(Request $request)
    {
        Gate::authorize('suppliers.view');
        $data = $request->validate(['q' => 'nullable|string|max:100']);
        $query = Supplier::orderBy('name');
        if ($data['q'] ?? null) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$data['q'].'%')->orWhere('supplier_code', 'like', '%'.$data['q'].'%')->orWhere('mobile_number', 'like', '%'.$data['q'].'%'));
        }

        return view('modules.suppliers.index', ['suppliers' => $query->paginate(20)->withQueryString()]);
    }

    public function form(?Supplier $supplier = null)
    {
        Gate::authorize($supplier?->exists ? 'suppliers.update' : 'suppliers.create');

        return view('modules.suppliers.form', ['supplier' => $supplier ?? new Supplier]);
    }

    public function save(Request $request, ?Supplier $supplier = null)
    {
        Gate::authorize($supplier?->exists ? 'suppliers.update' : 'suppliers.create');
        $data = $request->validate(['name' => 'required|string|max:255', 'contact_person' => 'nullable|string|max:255', 'address' => 'required|string|max:2000', 'mobile_number' => 'required|string|max:30', 'telephone_number' => 'nullable|string|max:30', 'email' => 'nullable|email|max:255', 'opening_balance' => [$supplier?->exists ? 'prohibited' : 'required', 'numeric', 'decimal:0,2', 'between:-999999999999.99,999999999999.99'], 'credit_terms' => 'nullable|string|max:255', 'credit_days' => 'required|integer|between:0,3650', 'notes' => 'nullable|string|max:2000', 'is_active' => 'required|boolean']);
        $supplier = DB::transaction(function () use ($supplier, $data, $request) {
            if ($supplier?->exists) {
                $old = $supplier->only(array_keys($data));
                $supplier->update($data);
                BusinessAudit::record($request->user(), 'supplier.updated', Supplier::class, $supplier->id, $old, $supplier->only(array_keys($data)));
            } else {
                $supplier = Supplier::create($data + ['supplier_code' => 'SUP-'.Str::ulid()]);
                if (! BigDecimal::of($supplier->opening_balance)->isZero()) {
                    SupplierAccountEntry::create(['supplier_id' => $supplier->id, 'entry_type' => 'opening', 'amount' => $supplier->opening_balance, 'occurred_at' => now(), 'reference_number' => $supplier->supplier_code, 'description' => 'Supplier opening balance', 'recorded_by_user_id' => $request->user()->id, 'idempotency_key' => 'supplier-opening:'.$supplier->id]);
                }
                BusinessAudit::record($request->user(), 'supplier.created', Supplier::class, $supplier->id, newValues: ['name' => $supplier->name, 'opening_balance' => $supplier->opening_balance]);
            }

            return $supplier;
        });

        return redirect()->route('suppliers.show', $supplier)->with('status', 'Supplier saved.');
    }

    public function show(Supplier $supplier)
    {
        Gate::authorize('suppliers.view');

        return view('modules.suppliers.show', ['supplier' => $supplier, 'entries' => $supplier->accountEntries()->orderBy('occurred_at')->orderBy('id')->paginate(30), 'balance' => (string) BigDecimal::of((string) $supplier->accountEntries()->sum('amount'))->toScale(2), 'purchases' => $supplier->purchases()->orderByDesc('id')->limit(30)->get(), 'openingOutstanding' => app(SupplierPaymentService::class)->openingOutstanding($supplier), 'payments' => $supplier->payments()->with('supplierPaymentReversals')->orderByDesc('id')->limit(30)->get()]);
    }

    public function payOpening(Request $request, Supplier $supplier, SupplierPaymentService $service)
    {
        $data = $request->validate(['amount' => 'required|numeric|decimal:0,2|gt:0|max:999999999999.99', 'method' => 'required|in:cash,bank_transfer,cheque', 'reference' => 'nullable|required_unless:method,cash|string|max:100', 'cheque_date' => 'nullable|required_if:method,cheque|date_format:Y-m-d', 'notes' => 'nullable|string|max:2000', 'idempotency_key' => 'required|uuid']);
        $service->payOpening($supplier, $data, $request->user());

        return back()->with('status', 'Supplier opening balance payment recorded.');
    }
}
