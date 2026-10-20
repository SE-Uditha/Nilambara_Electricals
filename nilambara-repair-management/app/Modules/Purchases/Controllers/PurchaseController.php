<?php

namespace App\Modules\Purchases\Controllers;

use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\PrintTemplates\Services\IntakePdfService;
use App\Modules\Purchases\Models\GoodsReceivedNote;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Purchases\Services\PurchasePrintService;
use App\Modules\Purchases\Services\PurchaseWorkflowService;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Models\SupplierPayment;
use App\Modules\Suppliers\Services\SupplierPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PurchaseController
{
    public function document(Request $request, Purchase $purchase, string $format, PurchasePrintService $printer)
    {
        $data = $printer->prepare($purchase, null, $request->boolean('duplicate'), $format === 'pdf', $request->user());
        $view = view('modules.purchases.document', $data);

        return $format === 'pdf' ? app(IntakePdfService::class)->download($view, $data['number'].'.pdf') : $view;
    }

    public function grnDocument(Request $request, GoodsReceivedNote $grn, string $format, PurchasePrintService $printer)
    {
        $data = $printer->prepare($grn->purchase, $grn, $request->boolean('duplicate'), $format === 'pdf', $request->user());
        $view = view('modules.purchases.document', $data);

        return $format === 'pdf' ? app(IntakePdfService::class)->download($view, $data['number'].'.pdf') : $view;
    }

    public function index(Request $request)
    {
        Gate::authorize('purchases.view');
        $data = $request->validate(['q' => 'nullable|string|max:100']);
        $query = Purchase::with('supplier')->orderByDesc('id');
        if ($data['q'] ?? null) {
            $query->where(fn ($q) => $q->where('purchase_number', 'like', '%'.$data['q'].'%')->orWhere('supplier_invoice_number', 'like', '%'.$data['q'].'%')->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', '%'.$data['q'].'%')));
        }

        return view('modules.purchases.index', ['purchases' => $query->paginate(20)->withQueryString()]);
    }

    public function create()
    {
        Gate::authorize('purchases.create');

        return view('modules.purchases.create', ['suppliers' => Supplier::where('is_active', true)->get(), 'parts' => SparePart::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, PurchaseWorkflowService $service)
    {
        $data = $request->validate(['supplier_id' => 'required|integer|exists:suppliers,id', 'supplier_invoice_number' => ['nullable', 'string', 'max:100', Rule::unique('purchases')->where('supplier_id', $request->input('supplier_id'))], 'purchase_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'payment_terms' => 'required|in:cash,credit', 'due_date' => 'nullable|date_format:Y-m-d|after_or_equal:purchase_date', 'discount_amount' => 'required|numeric|decimal:0,2|between:0,999999999999.99', 'other_costs' => 'required|numeric|decimal:0,2|between:0,999999999999.99', 'notes' => 'nullable|string|max:2000', 'idempotency_key' => 'required|uuid', 'items' => 'required|array|min:1|max:100', 'items.*.spare_part_id' => 'required|integer|exists:spare_parts,id', 'items.*.quantity' => 'required|numeric|decimal:0,3|gt:0|max:999999999.999', 'items.*.unit_cost' => 'required|numeric|decimal:0,4|between:0,999999999.9999']);
        $purchase = $service->create($data, $request->user());

        return redirect()->route('purchases.show', $purchase)->with('status', 'Draft saved. No stock or payable has been posted.');
    }

    public function show(Purchase $purchase, SupplierPaymentService $payments)
    {
        Gate::authorize('purchases.view');

        return view('modules.purchases.show', ['purchase' => $purchase->load(['supplier', 'items.sparePart', 'goodsReceivedNotes.items', 'supplierPaymentAllocations.supplierPayment.supplierPaymentReversals']), 'outstanding' => $payments->outstanding($purchase), 'locations' => StockLocation::where('is_active', true)->get()]);
    }

    public function finalize(Request $request, Purchase $purchase, PurchaseWorkflowService $service)
    {
        $service->finalize($purchase, $request->user());

        return back()->with('status', 'Purchase finalized. Supplier payable recorded; stock is received separately.');
    }

    public function receive(Request $request, Purchase $purchase, PurchaseWorkflowService $service)
    {
        $data = $request->validate(['purchase_item_id' => 'required|integer', 'location_id' => 'required|integer|exists:stock_locations,id', 'quantity' => 'required|numeric|decimal:0,3|gt:0|max:999999999.999', 'idempotency_key' => 'required|uuid', 'notes' => 'nullable|string|max:2000']);
        $service->receive($purchase, $data, $request->user());

        return back()->with('status', 'Goods received and stock posted once.');
    }

    public function pay(Request $request, Purchase $purchase, SupplierPaymentService $service)
    {
        $data = $request->validate(['amount' => 'required|numeric|decimal:0,2|gt:0|max:999999999999.99', 'method' => 'required|in:cash,bank_transfer,cheque', 'reference' => 'nullable|required_unless:method,cash|string|max:100', 'cheque_date' => 'nullable|required_if:method,cheque|date_format:Y-m-d', 'notes' => 'nullable|string|max:2000', 'idempotency_key' => 'required|uuid']);
        $service->pay($purchase, $data, $request->user());

        return back()->with('status', 'Supplier payment recorded.');
    }

    public function reverse(Request $request, SupplierPayment $payment, SupplierPaymentService $service)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:2000', 'idempotency_key' => 'required|uuid']);
        $service->reverse($payment, $data, $request->user());

        return back()->with('status', 'Supplier payment reversed; original retained.');
    }

    public function returnStock(Request $request, Purchase $purchase, PurchaseWorkflowService $service)
    {
        $data = $request->validate(['purchase_item_id' => 'required|integer', 'location_id' => 'required|integer|exists:stock_locations,id', 'quantity' => 'required|numeric|decimal:0,3|gt:0|max:999999999.999', 'credit_amount' => 'nullable|numeric|decimal:0,2|between:0,999999999999.99', 'reason' => 'required|string|min:5|max:2000', 'idempotency_key' => 'required|uuid']);
        $service->returnStock($purchase, $data, $request->user());

        return back()->with('status', 'Supplier stock return recorded. Any agreed credit is recorded separately from cash payments.');
    }
}
