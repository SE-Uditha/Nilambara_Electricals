<?php

namespace App\Modules\POS\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Employees\Models\Employee;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockReturn;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\POS\Models\PosSale;
use App\Modules\POS\Requests\CheckoutRequest;
use App\Modules\POS\Services\POSCalculationService;
use App\Modules\POS\Services\POSCheckoutService;
use App\Modules\POS\Services\POSReceiptService;
use Barryvdh\DomPDF\Facade\Pdf;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class POSController extends Controller
{
    public function index(Request $request, InventoryService $inventory, POSCalculationService $calculations): View
    {
        $term = trim((string) $request->query('q', ''));
        $parts = collect();
        if ($term !== '') {
            $like = '%'.$term.'%';
            $parts = SparePart::query()->with(['brand', 'category', 'unit', 'defaultLocation'])
                ->where('is_active', true)
                ->where(function ($query) use ($like): void {
                    $query->where('sku', 'like', $like)
                        ->orWhere('barcode', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhereHas('brand', fn ($brand) => $brand->where('name', 'like', $like))
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', $like));
                })
                ->orderBy('name')
                ->limit(30)
                ->get();
        }
        $cart = $this->cart($request, $calculations);
        $parts->each(fn (SparePart $part) => $part->setAttribute('saleable_quantity', $inventory->availableAtDefaultLocation($part)));
        $customerTerm = trim((string) $request->query('customer_q', ''));
        $customers = Customer::query()->where('is_active', true)
            ->when($customerTerm !== '', function ($query) use ($customerTerm): void {
                $like = '%'.$customerTerm.'%';
                $query->where(function ($customerQuery) use ($like): void {
                    $customerQuery->where('customer_code', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('mobile_number', 'like', $like);
                });
            })
            ->orderBy('customer_code')
            ->limit(30)
            ->get();

        return view('modules.pos.index', [
            'parts' => $parts,
            'cart' => $cart,
            'customers' => $customers,
            'customerTerm' => $customerTerm,
            'checkoutKey' => (string) Str::uuid(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function addProduct(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'part_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999999999.999'],
        ]);
        $scan = trim((string) ($data['barcode'] ?? ''));
        if (empty($data['part_id']) && $scan === '') {
            return back()->withErrors(['barcode' => __('pos.scan_or_select_product')]);
        }
        $part = ! empty($data['part_id'])
            ? SparePart::query()->with(['unit', 'defaultLocation'])->whereKey($data['part_id'])->firstOrFail()
            : SparePart::query()->with(['unit', 'defaultLocation'])
                ->where(fn ($query) => $query->where('barcode', $scan)->orWhere('sku', $scan))
                ->first();
        if ($part === null || ! $part->is_active || $part->defaultLocation === null || ! $part->defaultLocation->is_active) {
            return back()->withErrors(['barcode' => __('pos.product_unavailable')]);
        }

        $cart = $request->session()->get('pos_cart', []);
        $id = (int) $part->getKey();
        $quantity = BigDecimal::of((string) ($data['quantity'] ?? '1'))->toScale(3);
        $newQuantity = BigDecimal::of((string) ($cart[$id] ?? '0'))->plus($quantity)->toScale(3);
        $available = $inventory->availableAtDefaultLocation($part);
        if ($newQuantity->isGreaterThan($available)) {
            return back()->withErrors(['barcode' => __('pos.insufficient_stock', ['product' => $part->name, 'available' => (string) $available])]);
        }
        $scale = (int) $part->unit->quantity_scale;
        if ($newQuantity->compareTo($newQuantity->toScale($scale)) !== 0) {
            return back()->withErrors(['barcode' => __('pos.quantity_precision', ['product' => $part->name, 'scale' => $scale])]);
        }
        $cart[$id] = (string) $newQuantity;
        $request->session()->put('pos_cart', $cart);

        return redirect()->route('pos.index', ['q' => $request->input('search_term')])
            ->with('status', __('pos.product_added', ['product' => $part->name]));
    }

    public function updateQuantity(Request $request, SparePart $part, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999999999.999'],
        ]);
        $part->load(['unit', 'defaultLocation']);
        abort_unless($part->is_active && $part->defaultLocation?->is_active, 422);
        $quantity = BigDecimal::of((string) $data['quantity'])->toScale(3);
        if ($quantity->compareTo($quantity->toScale((int) $part->unit->quantity_scale)) !== 0) {
            return back()->withErrors(['quantity' => __('pos.quantity_precision', ['product' => $part->name, 'scale' => $part->unit->quantity_scale])]);
        }
        $available = $inventory->availableAtDefaultLocation($part);
        if ($quantity->isGreaterThan($available)) {
            return back()->withErrors(['quantity' => __('pos.insufficient_stock', ['product' => $part->name, 'available' => (string) $available])]);
        }
        $cart = $request->session()->get('pos_cart', []);
        $cart[$part->getKey()] = (string) $quantity;
        $request->session()->put('pos_cart', $cart);

        return back();
    }

    public function removeProduct(Request $request, SparePart $part): RedirectResponse
    {
        $cart = $request->session()->get('pos_cart', []);
        unset($cart[$part->getKey()]);
        $request->session()->put('pos_cart', $cart);

        return back();
    }

    public function clearCart(Request $request): RedirectResponse
    {
        $request->session()->forget('pos_cart');

        return redirect()->route('pos.index');
    }

    public function checkout(CheckoutRequest $request, POSCheckoutService $checkout): RedirectResponse
    {
        $sale = $checkout->checkout($request->validated(), $request->session()->get('pos_cart', []), $request->user());
        $request->session()->forget('pos_cart');

        return redirect()->route('pos.sales.show', $sale)
            ->with('status', $sale->status === DocumentStatus::Finalized
                ? __('pos.sale_completed', ['sale' => $sale->sale_number])
                : __('pos.payment_requires_verification', ['sale' => $sale->sale_number]));
    }

    public function sales(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'cashier_id' => ['nullable', 'integer', 'exists:employees,id'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'payment_status' => ['nullable', Rule::in(['pending', 'settled', 'partially_refunded', 'refunded'])],
        ]);
        $sales = PosSale::query()->with(['customer', 'cashierEmployee'])
            ->withCount('items')
            ->when(($term = trim((string) ($filters['q'] ?? ''))) !== '', fn ($query) => $query->where('sale_number', 'like', '%'.$term.'%'))
            ->when(! empty($filters['date_from']), fn ($query) => $query->whereDate('sale_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($query) => $query->whereDate('sale_at', '<=', $filters['date_to']))
            ->when(! empty($filters['customer_id']), fn ($query) => $query->where('customer_id', $filters['customer_id']))
            ->when(! empty($filters['cashier_id']), fn ($query) => $query->where('cashier_employee_id', $filters['cashier_id']))
            ->when(! empty($filters['payment_method']), fn ($query) => $query->where('payment_method', $filters['payment_method']))
            ->when(! empty($filters['payment_status']), fn ($query) => $query->where('payment_status', $filters['payment_status']))
            ->orderByDesc('sale_at')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('modules.pos.sales', [
            'sales' => $sales,
            'filters' => $filters,
            'customers' => Customer::query()->orderBy('customer_code')->get(),
            'employees' => Employee::query()->where('is_active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function show(PosSale $sale): View
    {
        $sale->load([
            'customer',
            'cashierEmployee',
            'paymentAllocations.payment.paymentReversals',
            'items.sparePart',
            'items.location',
            'items.stockMovements',
            'items.returnItems.stockReturn',
            'stockReturns.items.posSaleItem',
            'stockReturns.items.stockMovements',
            'stockReturns.refundPaymentReversal',
            'documentPrintHistories',
        ]);

        return view('modules.pos.show', ['sale' => $sale]);
    }

    public function verify(PosSale $sale, POSCheckoutService $checkout): RedirectResponse
    {
        $checkout->verifyAndFinalize($sale, request()->user());

        return redirect()->route('pos.sales.show', $sale)->with('status', __('pos.sale_completed', ['sale' => $sale->sale_number]));
    }

    public function cancel(Request $request, PosSale $sale, POSCheckoutService $checkout): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $checkout->cancelPending($sale, $data['reason'], $request->user());

        return redirect()->route('pos.sales.show', $sale)->with('status', __('pos.pending_sale_cancelled'));
    }

    public function returns(): View
    {
        $returns = StockReturn::query()->with(['posSale', 'recordedByUser', 'items.posSaleItem.sparePart'])
            ->where('return_type', 'pos_return')->orderByDesc('returned_at')->paginate(25);

        return view('modules.pos.returns', ['returns' => $returns]);
    }

    public function reports(Request $request): View
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'group' => ['nullable', Rule::in(['day', 'month'])],
        ]);
        $query = PosSale::query()->where('status', DocumentStatus::Finalized->value)
            ->when(! empty($filters['date_from']), fn ($builder) => $builder->whereDate('sale_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($builder) => $builder->whereDate('sale_at', '<=', $filters['date_to']));
        $returnsQuery = StockReturn::query()->where('return_type', 'pos_return')->where('status', DocumentStatus::Finalized->value)
            ->when(! empty($filters['date_from']), fn ($builder) => $builder->whereDate('returned_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($builder) => $builder->whereDate('returned_at', '<=', $filters['date_to']));
        $returns = (clone $returnsQuery)
            ->selectRaw('COUNT(*) as return_count, COALESCE(SUM(refund_amount), 0) as refunds')->first();
        $summary = (clone $query)->selectRaw('COUNT(*) as sale_count, COALESCE(SUM(subtotal), 0) as gross_sales, COALESCE(SUM(discount_amount), 0) as discounts, COALESCE(SUM(total), 0) as net_sales')->first();
        $summary->net_sales = (string) BigDecimal::of((string) $summary->net_sales)
            ->minus((string) $returns->refunds)->toScale(2);
        $payments = (clone $query)->selectRaw('payment_method, COUNT(*) as sale_count, COALESCE(SUM(total), 0) as amount')
            ->groupBy('payment_method')->orderBy('payment_method')->get();
        $driver = DB::connection()->getDriverName();
        $dateFormat = ($filters['group'] ?? 'day') === 'month' ? '%Y-%m' : '%Y-%m-%d';
        $periodExpression = $driver === 'sqlite'
            ? "strftime('{$dateFormat}', sale_at)"
            : "DATE_FORMAT(sale_at, '{$dateFormat}')";
        $periods = (clone $query)->selectRaw("{$periodExpression} as period, COUNT(*) as sale_count, COALESCE(SUM(total), 0) as net_sales")
            ->groupBy('period')->orderBy('period')->get();
        $returnPeriodExpression = $driver === 'sqlite'
            ? "strftime('{$dateFormat}', returned_at)"
            : "DATE_FORMAT(returned_at, '{$dateFormat}')";
        $refundsByPeriod = (clone $returnsQuery)->selectRaw("{$returnPeriodExpression} as period, COALESCE(SUM(refund_amount), 0) as refunds")
            ->groupBy('period')->get()->keyBy('period');
        $periods->each(function ($period) use ($refundsByPeriod): void {
            $period->net_sales = (string) BigDecimal::of((string) $period->net_sales)
                ->minus((string) ($refundsByPeriod->get($period->period)?->refunds ?? '0.00'))->toScale(2);
        });
        $items = StockMovement::query()->join('pos_sale_items', 'stock_movements.pos_sale_item_id', '=', 'pos_sale_items.id')
            ->join('spare_parts', 'pos_sale_items.spare_part_id', '=', 'spare_parts.id')
            ->join('pos_sales', 'pos_sale_items.pos_sale_id', '=', 'pos_sales.id')
            ->where('pos_sales.status', DocumentStatus::Finalized->value)
            ->when(! empty($filters['date_from']), fn ($builder) => $builder->whereDate('pos_sales.sale_at', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn ($builder) => $builder->whereDate('pos_sales.sale_at', '<=', $filters['date_to']))
            ->selectRaw('spare_parts.id, spare_parts.sku, spare_parts.name, SUM(pos_sale_items.quantity) as quantity_sold, SUM(pos_sale_items.line_total) as sales_total')
            ->groupBy('spare_parts.id', 'spare_parts.sku', 'spare_parts.name')
            ->orderByDesc('quantity_sold')->limit(30)->get();
        $cashiers = (clone $query)->join('employees', 'pos_sales.cashier_employee_id', '=', 'employees.id')
            ->selectRaw('employees.id, employees.name, COUNT(*) as sale_count, COALESCE(SUM(pos_sales.total), 0) as sales_total')
            ->groupBy('employees.id', 'employees.name')->orderByDesc('sales_total')->get();

        return view('modules.pos.reports', compact('filters', 'summary', 'payments', 'periods', 'items', 'cashiers', 'returns'));
    }

    public function print(Request $request, PosSale $sale, POSReceiptService $receipts): View
    {
        abort_unless($request->user()->can('pos.receipt.print'), 403);
        $paper = $request->validate(['paper' => ['nullable', Rule::in(['thermal', 'A4', 'A5'])]])['paper'] ?? null;
        $data = $receipts->prepare($sale, false, false, $request->user(), $paper);

        return view('modules.pos.receipt', $data);
    }

    public function reprint(Request $request, PosSale $sale, POSReceiptService $receipts): View
    {
        abort_unless($request->user()->can('pos.receipt.print'), 403);
        $paper = $request->validate(['paper' => ['nullable', Rule::in(['thermal', 'A4', 'A5'])]])['paper'] ?? null;

        return view('modules.pos.receipt', $receipts->prepare($sale, true, false, $request->user(), $paper));
    }

    public function pdf(Request $request, PosSale $sale, POSReceiptService $receipts)
    {
        abort_unless($request->user()->can('pos.receipt.print'), 403);
        $paper = $request->validate(['paper' => ['nullable', Rule::in(['A4', 'A5', 'thermal'])]])['paper'] ?? 'A5';
        $data = $receipts->prepare($sale, false, true, $request->user(), $paper);
        $pdf = Pdf::loadView('modules.pos.receipt', $data);
        if ($paper === 'thermal') {
            $width = max(58, min(110, (float) $data['thermalWidth']));
            $pdf->setPaper([0, 0, $width * 2.83465, 900]);
        } else {
            $pdf->setPaper($paper, $data['orientation']);
        }

        return $pdf->download($sale->sale_number.'-receipt.pdf');
    }

    private function cart(Request $request, POSCalculationService $calculations): array
    {
        $quantities = $request->session()->get('pos_cart', []);
        if ($quantities === []) {
            return ['items' => [], 'subtotal' => BigDecimal::zero()->toScale(2), 'total' => BigDecimal::zero()->toScale(2)];
        }
        $parts = SparePart::query()->with(['brand', 'unit', 'defaultLocation'])
            ->whereIn('id', array_map('intval', array_keys($quantities)))->get()->keyBy('id');
        $parts->each(fn (SparePart $part) => $part->setAttribute('saleable_quantity', app(InventoryService::class)->availableAtDefaultLocation($part)));

        return $calculations->calculate($parts, $quantities);
    }
}
