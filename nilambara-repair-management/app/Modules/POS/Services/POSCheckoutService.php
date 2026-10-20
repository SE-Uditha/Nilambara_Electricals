<?php

namespace App\Modules\POS\Services;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Employees\Models\Employee;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\POS\Models\PosSale;
use App\Modules\POS\Models\PosSaleItem;
use App\Modules\Settings\Models\DocumentSequence;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class POSCheckoutService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly POSCalculationService $calculations,
        private readonly PaymentService $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string|int>  $cart
     */
    public function checkout(array $data, array $cart, User $actor): PosSale
    {
        abort_unless($actor->can('pos.sale'), 403);
        if ($existing = PosSale::query()->where('idempotency_key', $data['idempotency_key'])->first()) {
            $replayCart = $cart === [] ? $existing->items()->pluck('quantity', 'spare_part_id')->all() : $cart;
            $idempotencyHash = $this->idempotencyHash($data, $replayCart);
            $this->assertIdempotentReplay($existing, $idempotencyHash, $actor);

            return $existing;
        }
        $idempotencyHash = $this->idempotencyHash($data, $cart);

        return DB::transaction(function () use ($data, $cart, $actor, $idempotencyHash): PosSale {
            if ($existing = PosSale::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first()) {
                $replayCart = $cart === [] ? $existing->items()->pluck('quantity', 'spare_part_id')->all() : $cart;
                $replayHash = $this->idempotencyHash($data, $replayCart);
                $this->assertIdempotentReplay($existing, $replayHash, $actor);

                return $existing;
            }

            if ($cart === []) {
                throw ValidationException::withMessages(['cart' => __('pos.cart_empty')]);
            }
            $employee = Employee::query()->where('user_id', $actor->getKey())->where('is_active', true)->first();
            if ($employee === null) {
                throw ValidationException::withMessages(['checkout' => __('pos.active_cashier_required')]);
            }

            $quantities = $this->quantities($cart);
            $parts = $this->inventory->lockSaleableParts($quantities);
            $this->assertQuantityPrecision($parts, $quantities);
            $summary = $this->calculations->calculate($parts, $quantities, (string) ($data['discount_amount'] ?? '0.00'));
            if (! $summary['total']->isGreaterThan(0)) {
                throw ValidationException::withMessages(['cart' => __('pos.positive_sale_total_required')]);
            }

            $discount = BigDecimal::of((string) ($data['discount_amount'] ?? '0.00'))->toScale(2, RoundingMode::HalfUp);
            if ($discount->isGreaterThan(0)) {
                abort_unless($actor->can('pos.discount'), 403);
            }

            $customer = null;
            if (! empty($data['customer_id'])) {
                $customer = Customer::query()->whereKey($data['customer_id'])->where('is_active', true)->first();
                if ($customer === null) {
                    throw ValidationException::withMessages(['customer_id' => __('pos.active_customer_required')]);
                }
            }

            $method = PaymentMethod::from($data['payment_method']);
            $reference = trim((string) ($data['payment_reference'] ?? ''));
            if ($method !== PaymentMethod::Cash) {
                $this->assertUniqueExternalReference($method, $reference);
            }
            $total = $summary['total'];
            $tendered = $method === PaymentMethod::Cash
                ? BigDecimal::of((string) $data['amount_tendered'])->toScale(2)
                : $total;
            if ($method === PaymentMethod::Cash && $tendered->isLessThan($total)) {
                throw ValidationException::withMessages(['amount_tendered' => __('pos.tender_below_total')]);
            }

            $sequence = DocumentSequence::query()->where('scope', 'pos_sale')->lockForUpdate()->firstOrFail();
            $saleNumber = $sequence->prefix.str_pad((string) $sequence->next_value, $sequence->padding, '0', STR_PAD_LEFT);
            $sequence->increment('next_value');
            $now = now(config('business.timezone', 'Asia/Colombo'));
            $pendingVerification = $method !== PaymentMethod::Cash;
            $sale = PosSale::query()->create([
                'sale_number' => $saleNumber,
                'sale_at' => $now,
                'customer_id' => $customer?->getKey(),
                'cashier_employee_id' => $employee->getKey(),
                'subtotal' => (string) $summary['subtotal'],
                'discount_amount' => (string) $discount,
                'total' => (string) $total,
                'payment_terms' => 'cash',
                'status' => DocumentStatus::Draft->value,
                'payment_method' => $method->value,
                'payment_status' => 'pending',
                'payment_reference' => $reference !== '' ? $reference : null,
                'cheque_date' => $method === PaymentMethod::Cheque ? $data['cheque_date'] : null,
                'bank_name' => trim((string) ($data['bank_name'] ?? '')) ?: null,
                'amount_tendered' => (string) $tendered,
                'change_due' => $method === PaymentMethod::Cash ? (string) $tendered->minus($total)->toScale(2) : '0.00',
                'idempotency_key' => $data['idempotency_key'],
                'idempotency_hash' => $idempotencyHash,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'customer_scope_id' => $customer?->getKey() ?? 0,
            ]);

            foreach ($summary['items'] as $index => $line) {
                /** @var SparePart $part */
                $part = $line['part'];
                PosSaleItem::query()->create([
                    'pos_sale_id' => $sale->getKey(),
                    'line_number' => $index + 1,
                    'spare_part_id' => $part->getKey(),
                    'location_id' => $part->default_location_id,
                    'description' => $part->sku.' · '.$part->name,
                    'quantity' => (string) $line['quantity'],
                    'unit_price' => (string) $line['unit_price'],
                    'unit_cost' => DB::table('stock_movements')->where('spare_part_id', $part->getKey())
                        ->where('location_id', $part->default_location_id)->whereNotNull('unit_cost')
                        ->orderByDesc('occurred_at')->orderByDesc('id')->value('unit_cost'),
                    'discount_amount' => '0.00',
                    'line_total' => (string) $line['line_total'],
                ]);
            }

            BusinessAudit::record($actor, 'pos.sale.created', PosSale::class, (int) $sale->getKey(), newValues: [
                'sale_number' => $saleNumber, 'subtotal' => (string) $summary['subtotal'],
                'discount_amount' => (string) $discount, 'total' => (string) $total,
            ]);
            if ($discount->isGreaterThan(0)) {
                BusinessAudit::record($actor, 'pos.sale.discounted', PosSale::class, (int) $sale->getKey(), newValues: [
                    'discount_amount' => (string) $discount,
                ]);
            }

            if ($pendingVerification) {
                $sale->forceFill(['document_snapshot' => $this->snapshot($sale, $summary['items'], $customer)])->save();
                BusinessAudit::record($actor, 'pos.sale.payment_pending', PosSale::class, (int) $sale->getKey(), newValues: [
                    'sale_number' => $saleNumber,
                    'payment_method' => $method->value,
                    'total' => (string) $total,
                ]);

                return $sale->refresh();
            }

            $sale->forceFill(['document_snapshot' => $this->snapshot($sale, $summary['items'], $customer)])->save();
            $this->payments->receivePosPayment($sale, $actor);
            $this->postStockMovements($sale, $actor);
            $sale->forceFill([
                'status' => DocumentStatus::Finalized->value,
                'payment_status' => 'settled',
                'finalized_at' => $now,
            ])->save();
            BusinessAudit::record($actor, 'pos.sale.finalized', PosSale::class, (int) $sale->getKey(), newValues: [
                'sale_number' => $saleNumber,
                'total' => (string) $total,
                'payment_method' => $method->value,
                'stock_movement_ids' => $sale->items()->with('stockMovements')->get()->pluck('stockMovements.id')->filter()->values()->all(),
            ]);

            return $sale->refresh();
        }, 3);
    }

    public function verifyAndFinalize(PosSale $sale, User $actor): PosSale
    {
        Gate::forUser($actor)->authorize('verifyPayment', $sale);
        abort_unless($actor->can('pos.payment.verify'), 403);

        return DB::transaction(function () use ($sale, $actor): PosSale {
            $locked = PosSale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === DocumentStatus::Finalized) {
                return $locked;
            }
            if ($locked->status !== DocumentStatus::Draft || $locked->payment_status !== 'pending'
                || in_array($locked->payment_method, [null, PaymentMethod::Cash->value], true)) {
                throw ValidationException::withMessages(['sale' => __('pos.sale_cannot_be_verified')]);
            }

            $this->assertUniqueExternalReference(PaymentMethod::from($locked->payment_method), (string) $locked->payment_reference, (int) $locked->getKey());
            $quantities = $locked->items()->orderBy('spare_part_id')->pluck('quantity', 'spare_part_id')->all();
            $this->inventory->lockSaleableParts($quantities);
            $this->payments->receivePosPayment($locked, $actor);
            $this->postStockMovements($locked, $actor);
            $locked->forceFill([
                'status' => DocumentStatus::Finalized->value,
                'payment_status' => 'settled',
                'payment_verified_by_user_id' => $actor->getKey(),
                'payment_verified_at' => now(config('business.timezone', 'Asia/Colombo')),
                'finalized_at' => now(config('business.timezone', 'Asia/Colombo')),
            ])->save();
            BusinessAudit::record($actor, 'pos.sale.finalized', PosSale::class, (int) $locked->getKey(), newValues: [
                'sale_number' => $locked->sale_number,
                'payment_method' => $locked->payment_method,
                'payment_reference' => $locked->payment_reference,
                'verified_by_user_id' => $actor->getKey(),
            ]);

            return $locked->refresh();
        }, 3);
    }

    public function cancelPending(PosSale $sale, string $reason, User $actor): PosSale
    {
        Gate::forUser($actor)->authorize('cancel', $sale);
        abort_unless($actor->can('pos.sale.cancel'), 403);

        return DB::transaction(function () use ($sale, $reason, $actor): PosSale {
            $locked = PosSale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== DocumentStatus::Draft || $locked->payment_status !== 'pending') {
                throw ValidationException::withMessages(['sale' => __('pos.only_pending_sales_can_be_cancelled')]);
            }
            $locked->forceFill([
                'status' => DocumentStatus::Cancelled->value,
                'cancelled_at' => now(config('business.timezone', 'Asia/Colombo')),
                'cancellation_reason' => trim($reason),
            ])->save();
            BusinessAudit::record($actor, 'pos.sale.cancelled', PosSale::class, (int) $locked->getKey(), newValues: [
                'sale_number' => $locked->sale_number,
                'reason' => trim($reason),
            ]);

            return $locked->refresh();
        });
    }

    private function postStockMovements(PosSale $sale, User $actor): void
    {
        foreach ($sale->items()->with('posSale')->orderBy('spare_part_id')->get() as $item) {
            $movement = $this->inventory->recordPosSale($item, $actor);
            BusinessAudit::record($actor, 'pos.stock_deducted', StockMovement::class, (int) $movement->getKey(), newValues: [
                'sale_number' => $sale->sale_number,
                'spare_part_id' => $item->spare_part_id,
                'quantity_delta' => (string) $movement->quantity_delta,
            ]);
        }
    }

    /**
     * @param  array<int, string|int>  $cart
     * @return array<int, string>
     */
    private function quantities(array $cart): array
    {
        $quantities = [];
        foreach ($cart as $id => $quantity) {
            $productId = filter_var($id, FILTER_VALIDATE_INT);
            if ($productId === false || $productId < 1 || ! is_numeric($quantity)) {
                throw ValidationException::withMessages(['cart' => __('pos.cart_invalid')]);
            }
            $decimal = BigDecimal::of((string) $quantity);
            if (! $decimal->isGreaterThan(0)) {
                throw ValidationException::withMessages(['cart' => __('pos.quantity_positive')]);
            }
            $quantities[(int) $productId] = (string) $decimal->toScale(3, RoundingMode::Unnecessary);
        }
        ksort($quantities);

        return $quantities;
    }

    private function assertQuantityPrecision($parts, array $quantities): void
    {
        foreach ($quantities as $id => $quantity) {
            /** @var SparePart $part */
            $part = $parts->get((int) $id);
            $value = BigDecimal::of($quantity);
            $scale = (int) $part->unit->quantity_scale;
            if ($value->compareTo($value->toScale($scale, RoundingMode::Down)) !== 0) {
                throw ValidationException::withMessages(['cart' => __('pos.quantity_precision', ['product' => $part->name, 'scale' => $scale])]);
            }
        }
    }

    private function assertUniqueExternalReference(PaymentMethod $method, string $reference, ?int $exceptSaleId = null): void
    {
        $sales = PosSale::query()->where('payment_method', $method->value)
            ->where('payment_reference', $reference);
        if ($exceptSaleId !== null) {
            $sales->whereKeyNot($exceptSaleId);
        }
        $payments = Payment::query();
        if ($method === PaymentMethod::Cheque) {
            $payments->where('cheque_number', $reference);
        } elseif ($method === PaymentMethod::BankTransfer) {
            $payments->where('bank_reference', $reference);
        }
        if ($reference === '' || $sales->exists() || $payments->exists()) {
            throw ValidationException::withMessages(['payment_reference' => __('pos.duplicate_or_missing_reference')]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string|int>  $cart
     */
    private function idempotencyHash(array $data, array $cart): string
    {
        $quantities = [];
        foreach ($cart as $partId => $quantity) {
            $quantities[(int) $partId] = (string) BigDecimal::of((string) $quantity)->toScale(3, RoundingMode::Unnecessary);
        }
        ksort($quantities);

        return hash('sha256', json_encode([
            'payment_method' => (string) $data['payment_method'],
            'customer_id' => isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            'discount_amount' => (string) BigDecimal::of((string) ($data['discount_amount'] ?? '0.00'))->toScale(2, RoundingMode::HalfUp),
            'amount_tendered' => isset($data['amount_tendered'])
                ? (string) BigDecimal::of((string) $data['amount_tendered'])->toScale(2, RoundingMode::HalfUp)
                : null,
            'payment_reference' => $data['payment_reference'] ?? null,
            'cheque_date' => $data['cheque_date'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'notes' => trim((string) ($data['notes'] ?? '')),
            'cart' => $quantities,
        ], JSON_THROW_ON_ERROR));
    }

    private function assertIdempotentReplay(PosSale $sale, string $idempotencyHash, User $actor): void
    {
        abort_unless((int) $sale->cashierEmployee?->user_id === (int) $actor->getKey(), 403);
        if (! is_string($sale->idempotency_hash) || ! hash_equals($sale->idempotency_hash, $idempotencyHash)) {
            throw ValidationException::withMessages(['idempotency_key' => __('pos.idempotency_conflict')]);
        }
    }

    private function snapshot(PosSale $sale, array $items, ?Customer $customer): array
    {
        return [
            'sale_number' => $sale->sale_number,
            'sale_at' => $sale->sale_at->toIso8601String(),
            'customer' => $customer ? [
                'code' => $customer->customer_code,
                'name' => $customer->display_name,
                'mobile_number' => $customer->mobile_number,
            ] : null,
            'cashier' => $sale->cashierEmployee()->value('name'),
            'items' => array_map(fn (array $line): array => [
                'sku' => $line['part']->sku,
                'name' => $line['part']->name,
                'quantity' => (string) $line['quantity'],
                'unit_price' => (string) $line['unit_price'],
                'line_total' => (string) $line['line_total'],
            ], $items),
            'subtotal' => (string) $sale->subtotal,
            'discount_amount' => (string) $sale->discount_amount,
            'total' => (string) $sale->total,
        ];
    }
}
