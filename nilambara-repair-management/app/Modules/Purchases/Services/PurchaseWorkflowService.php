<?php

namespace App\Modules\Purchases\Services;

use App\Models\User;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\StockReturn;
use App\Modules\Inventory\Models\StockReturnItem;
use App\Modules\Inventory\Services\StockWorkflowService;
use App\Modules\Purchases\Models\GoodsReceivedNote;
use App\Modules\Purchases\Models\Purchase;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Models\SupplierAccountEntry;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PurchaseWorkflowService
{
    public function __construct(private StockWorkflowService $stock) {}

    public function create(array $data, User $actor): Purchase
    {
        Gate::forUser($actor)->authorize('purchases.create');

        return DB::transaction(function () use ($data, $actor) {
            $supplier = Supplier::whereKey($data['supplier_id'])->lockForUpdate()->firstOrFail();
            if (! $supplier->is_active) {
                throw ValidationException::withMessages(['supplier_id' => 'Select an active supplier.']);
            }
            $number = 'PUR-'.$data['idempotency_key'];
            if ($old = Purchase::where('purchase_number', $number)->first()) {
                $this->sameDraft($old, $data, $actor);

                return $old;
            }
            $subtotal = BigDecimal::zero();
            $lines = [];
            foreach ($data['items'] as $index => $item) {
                $part = SparePart::findOrFail($item['spare_part_id']);
                if (! $part->is_active) {
                    throw ValidationException::withMessages(['items' => 'Select active parts.']);
                }
                $quantity = $this->stock->quantity($part, $item['quantity']);
                if (! $quantity->isPositive()) {
                    throw ValidationException::withMessages(['items' => 'Purchase quantities must be positive.']);
                }
                $line = $quantity->multipliedBy($item['unit_cost'])->toScale(2, RoundingMode::HalfUp);
                $lines[] = ['line_number' => $index + 1, 'spare_part_id' => $part->id, 'description' => $part->name, 'quantity' => (string) $quantity, 'unit_cost' => $item['unit_cost'], 'discount_amount' => '0.00', 'line_total' => (string) $line];
                $subtotal = $subtotal->plus($line);
            }
            $discount = BigDecimal::of($data['discount_amount'] ?? '0')->toScale(2);
            $other = BigDecimal::of($data['other_costs'] ?? '0')->toScale(2);
            if ($discount->isNegative() || $other->isNegative() || $discount->isGreaterThan($subtotal)) {
                throw ValidationException::withMessages(['discount_amount' => 'Discount must not exceed the subtotal.']);
            }
            $purchase = Purchase::create(['purchase_number' => $number, 'supplier_id' => $supplier->id, 'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null, 'purchase_date' => $data['purchase_date'], 'subtotal' => (string) $subtotal, 'discount_amount' => (string) $discount, 'other_costs' => (string) $other, 'total' => (string) $subtotal->minus($discount)->plus($other), 'payment_terms' => $data['payment_terms'], 'due_date' => $data['due_date'] ?? null, 'status' => 'draft', 'created_by_user_id' => $actor->id, 'notes' => $data['notes'] ?? null]);
            $purchase->items()->createMany($lines);
            BusinessAudit::record($actor, 'purchase.draft.created', Purchase::class, $purchase->id, newValues: ['total' => $purchase->total]);

            return $purchase;
        }, 3);
    }

    private function sameDraft(Purchase $old, array $data, User $actor): void
    {
        $same = (int) $old->supplier_id === (int) $data['supplier_id'] && (int) $old->created_by_user_id === (int) $actor->id;
        foreach (['purchase_date', 'supplier_invoice_number', 'payment_terms', 'due_date', 'notes'] as $field) {
            $value = $old->getRawOriginal($field);
            $same = $same && (string) $value === (string) ($data[$field] ?? '');
        }
        foreach (['discount_amount', 'other_costs'] as $field) {
            $same = $same && BigDecimal::of($old->$field)->compareTo($data[$field] ?? '0') === 0;
        }
        $lines = $old->items()->orderBy('line_number')->get();
        $same = $same && count($data['items']) === $lines->count();
        foreach ($data['items'] as $index => $item) {
            $line = $lines->get($index);
            $same = $same && $line && (int) $line->spare_part_id === (int) $item['spare_part_id'] && BigDecimal::of($line->quantity)->compareTo($item['quantity']) === 0 && BigDecimal::of($line->unit_cost)->compareTo($item['unit_cost']) === 0;
        }
        if (! $same) {
            throw ValidationException::withMessages(['idempotency_key' => 'Request key already used with different purchase details.']);
        }
    }

    public function finalize(Purchase $purchase, User $actor): Purchase
    {
        Gate::forUser($actor)->authorize('purchases.approve');

        return DB::transaction(function () use ($purchase, $actor) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($purchase->status->value === 'finalized') {
                return $purchase;
            }
            if ($purchase->status->value !== 'draft') {
                throw ValidationException::withMessages(['purchase' => 'Only draft purchases can be finalized.']);
            }
            $purchase->fill(['status' => 'finalized', 'finalized_at' => now()])->save();
            if (BigDecimal::of($purchase->total)->isPositive()) {
                SupplierAccountEntry::create(['supplier_id' => $purchase->supplier_id, 'entry_type' => 'purchase', 'amount' => $purchase->total, 'occurred_at' => now(), 'reference_number' => $purchase->purchase_number, 'description' => 'Finalized supplier purchase', 'recorded_by_user_id' => $actor->id, 'purchase_id' => $purchase->id, 'idempotency_key' => 'purchase:'.$purchase->id]);
            }
            BusinessAudit::record($actor, 'purchase.finalized', Purchase::class, $purchase->id, newValues: ['total' => $purchase->total]);

            return $purchase;
        }, 3);
    }

    public function receive(Purchase $purchase, array $data, User $actor): GoodsReceivedNote
    {
        Gate::forUser($actor)->authorize('purchases.receive');

        return DB::transaction(function () use ($purchase, $data, $actor) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($purchase->status->value !== 'finalized') {
                throw ValidationException::withMessages(['purchase' => 'Finalize the purchase before receiving stock.']);
            }
            $item = $purchase->items()->whereKey($data['purchase_item_id'])->lockForUpdate()->firstOrFail();
            $part = $this->stock->lockPart($item->spare_part_id, $data['location_id']);
            $quantity = $this->stock->quantity($part, $data['quantity']);
            $number = 'GRN-'.$data['idempotency_key'];
            if ($old = GoodsReceivedNote::where('grn_number', $number)->lockForUpdate()->first()) {
                $line = $old->items()->sole();
                if ($old->purchase_id !== $purchase->id || $old->received_by_employee_id !== $actor->employee?->id || (int) $line->purchase_item_id !== (int) $item->id || (int) $line->location_id !== (int) $data['location_id'] || BigDecimal::of($line->quantity)->compareTo($quantity) !== 0) {
                    throw ValidationException::withMessages(['idempotency_key' => 'GRN key already used with different details.']);
                }

                return $old;
            }
            $received = $item->goodsReceivedNoteItems()->lockForUpdate()->pluck('quantity')->reduce(fn (BigDecimal $sum, $q) => $sum->plus((string) $q), BigDecimal::zero());
            if (! $quantity->isPositive() || $received->plus($quantity)->isGreaterThan($item->quantity)) {
                throw ValidationException::withMessages(['quantity' => 'Quantity exceeds the unreceived purchase quantity.']);
            }
            if (! $actor->employee?->is_active) {
                throw ValidationException::withMessages(['employee' => 'An active employee account is required.']);
            }
            $grn = GoodsReceivedNote::create(['grn_number' => $number, 'purchase_id' => $purchase->id, 'supplier_id' => $purchase->supplier_id, 'received_at' => now(), 'received_by_employee_id' => $actor->employee->id, 'status' => 'finalized', 'finalized_at' => now(), 'notes' => $data['notes'] ?? null]);
            $line = $grn->items()->create(['purchase_id' => $purchase->id, 'purchase_item_id' => $item->id, 'spare_part_id' => $part->id, 'location_id' => $data['location_id'], 'quantity' => (string) $quantity, 'unit_cost' => $item->unit_cost]);
            $this->stock->movement($part->id, $data['location_id'], 'purchase_receipt', $quantity, $actor, $number, 'Supplier goods received', 'grn:'.$line->id, ['grn_item_id' => $line->id, 'unit_cost' => $item->unit_cost]);
            BusinessAudit::record($actor, 'purchase.goods.received', GoodsReceivedNote::class, $grn->id, newValues: ['purchase' => $purchase->purchase_number, 'quantity' => (string) $quantity]);

            return $grn;
        }, 3);
    }

    public function returnStock(Purchase $purchase, array $data, User $actor): StockReturn
    {
        Gate::forUser($actor)->authorize('purchases.receive');
        Gate::forUser($actor)->authorize('inventory.adjust');

        return DB::transaction(function () use ($purchase, $data, $actor) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            if ($purchase->status->value !== 'finalized') {
                throw ValidationException::withMessages(['purchase' => 'Only finalized purchases can have supplier returns.']);
            }
            $item = $purchase->items()->whereKey($data['purchase_item_id'])->firstOrFail();
            if ($purchase->items()->where('spare_part_id', $item->spare_part_id)->count() !== 1) {
                throw ValidationException::withMessages(['purchase_item_id' => 'Duplicate-part purchase lines need separate source reconciliation before a return.']);
            }
            $part = $this->stock->lockPart($item->spare_part_id, $data['location_id']);
            $quantity = $this->stock->quantity($part, $data['quantity']);
            $credit = BigDecimal::of($data['credit_amount'] ?? '0')->toScale(2);
            if ($credit->isPositive()) {
                Gate::forUser($actor)->authorize('purchases.approve');
            }
            $hash = hash('sha256', json_encode([$purchase->id, $item->id, (int) $data['location_id'], (string) $quantity, (string) $credit, $data['reason'], $actor->id], JSON_THROW_ON_ERROR));
            $returns = StockReturn::class;
            if ($old = $returns::where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) {
                if (! hash_equals((string) $old->idempotency_hash, $hash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'Return key already used with different details.']);
                }

                return $old;
            }
            $received = $item->goodsReceivedNoteItems()->where('location_id', $data['location_id'])->lockForUpdate()->pluck('quantity')->reduce(fn (BigDecimal $sum, $q) => $sum->plus((string) $q), BigDecimal::zero());
            $returned = StockReturnItem::whereHas('stockReturn', fn ($q) => $q->where('purchase_id', $purchase->id)->where('return_type', 'supplier_return')->where('status', 'finalized'))->where('spare_part_id', $part->id)->where('location_id', $data['location_id'])->lockForUpdate()->pluck('quantity')->reduce(fn (BigDecimal $sum, $q) => $sum->plus((string) $q), BigDecimal::zero());
            if (! $quantity->isPositive() || $quantity->plus($returned)->isGreaterThan($received) || $quantity->isGreaterThan($this->stock->balance($part->id, $data['location_id']))) {
                throw ValidationException::withMessages(['quantity' => 'Return cannot exceed received, unreturned and available stock.']);
            }
            $credits = SupplierAccountEntry::where('entry_type', 'adjustment')->whereIn('reference_number', $returns::where('purchase_id', $purchase->id)->where('return_type', 'supplier_return')->select('return_number'))->lockForUpdate()->pluck('amount')->reduce(fn (BigDecimal $sum, $amount) => $sum->minus((string) $amount), BigDecimal::zero());
            if ($credit->isNegative() || $credit->plus($credits)->isGreaterThan($purchase->total)) {
                throw ValidationException::withMessages(['credit_amount' => 'Supplier credit cannot exceed the purchase total.']);
            }
            $return = $returns::create(['return_number' => 'SR-'.$data['idempotency_key'], 'return_type' => 'supplier_return', 'purchase_id' => $purchase->id, 'returned_at' => now(), 'recorded_by_user_id' => $actor->id, 'status' => 'finalized', 'reason' => $data['reason'], 'idempotency_key' => $data['idempotency_key'], 'idempotency_hash' => $hash]);
            $line = $return->items()->create(['spare_part_id' => $part->id, 'location_id' => $data['location_id'], 'quantity' => (string) $quantity, 'unit_cost' => $item->unit_cost, 'condition' => 'resalable', 'notes' => $data['reason']]);
            $this->stock->movement($part->id, $data['location_id'], 'supplier_return', $quantity->negated(), $actor, $return->return_number, $data['reason'], 'supplier-return:'.$return->id, ['return_item_id' => $line->id, 'unit_cost' => $item->unit_cost]);
            if ($credit->isPositive()) {
                SupplierAccountEntry::create(['supplier_id' => $purchase->supplier_id, 'entry_type' => 'adjustment', 'amount' => (string) $credit->negated(), 'occurred_at' => now(), 'reference_number' => $return->return_number, 'description' => 'Agreed supplier return credit: '.$data['reason'], 'recorded_by_user_id' => $actor->id, 'idempotency_key' => 'supplier-return-credit:'.$return->id]);
            }
            BusinessAudit::record($actor, 'purchase.stock.returned', $returns, $return->id, newValues: ['purchase_id' => $purchase->id, 'quantity' => (string) $quantity, 'agreed_credit' => (string) $credit]);

            return $return;
        }, 3);
    }
}
