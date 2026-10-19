<?php

namespace App\Modules\POS\Services;

use App\Enums\DocumentStatus;
use App\Models\User;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockReturn;
use App\Modules\Inventory\Models\StockReturnItem;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\POS\Models\PosSale;
use App\Modules\POS\Models\PosSaleItem;
use App\Support\BusinessAudit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class POSReturnService
{
    public function __construct(private readonly InventoryService $inventory, private readonly PaymentService $payments) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(array $data, User $actor): StockReturn
    {
        abort_unless($actor->can('pos.return'), 403);
        $idempotencyHash = $this->idempotencyHash($data);

        return DB::transaction(function () use ($data, $actor, $idempotencyHash): StockReturn {
            $existing = StockReturn::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                $this->assertIdempotentReplay($existing, $idempotencyHash);

                return $existing;
            }

            $saleItem = PosSaleItem::query()->whereKey($data['pos_sale_item_id'])->lockForUpdate()->firstOrFail();
            $existing = StockReturn::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) {
                $this->assertIdempotentReplay($existing, $idempotencyHash);

                return $existing;
            }
            $sale = PosSale::query()->whereKey($saleItem->pos_sale_id)->lockForUpdate()->firstOrFail();
            if ($sale->status !== DocumentStatus::Finalized) {
                throw ValidationException::withMessages(['pos_sale_item_id' => __('pos.return_requires_finalized_sale')]);
            }
            $part = $saleItem->sparePart()->lockForUpdate()->firstOrFail();
            $location = StockLocation::query()->whereKey($saleItem->location_id)->lockForUpdate()->firstOrFail();
            $quantity = BigDecimal::of((string) $data['quantity'])->toScale(3, RoundingMode::Unnecessary);
            if ($quantity->compareTo($quantity->toScale((int) $part->unit->quantity_scale, RoundingMode::Unnecessary)) !== 0) {
                throw ValidationException::withMessages(['quantity' => __('pos.quantity_precision', [
                    'product' => $part->name,
                    'scale' => $part->unit->quantity_scale,
                ])]);
            }

            $alreadyReturned = DB::table('stock_return_items')
                ->join('stock_returns', 'stock_return_items.stock_return_id', '=', 'stock_returns.id')
                ->where('stock_return_items.pos_sale_item_id', $saleItem->getKey())
                ->where('stock_returns.status', DocumentStatus::Finalized->value)
                ->sum('stock_return_items.quantity');
            $remaining = BigDecimal::of((string) $saleItem->quantity)->minus((string) $alreadyReturned)->toScale(3);
            if (! $quantity->isGreaterThan(0) || $quantity->isGreaterThan($remaining)) {
                throw ValidationException::withMessages(['quantity' => __('pos.return_quantity_exceeded', [
                    'remaining' => (string) $remaining,
                ])]);
            }

            $refund = BigDecimal::of((string) ($data['refund_amount'] ?? '0.00'))->toScale(2);
            $refundable = BigDecimal::of((string) $saleItem->line_total)
                ->multipliedBy($quantity)
                ->dividedBy((string) $saleItem->quantity, 2, RoundingMode::HalfUp)
                ->toScale(2);
            if ($refund->isGreaterThan(0)) {
                abort_unless($actor->can('pos.refund') && $actor->can('payments.reverse'), 403);
                if ($refund->isGreaterThan($refundable)) {
                    throw ValidationException::withMessages(['refund_amount' => __('pos.refund_exceeds_return_value', [
                        'maximum' => (string) $refundable,
                    ])]);
                }
            }

            $return = StockReturn::query()->create([
                'return_number' => $this->returnNumber(),
                'return_type' => 'pos_return',
                'pos_sale_id' => $sale->getKey(),
                'returned_at' => now(config('business.timezone', 'Asia/Colombo')),
                'recorded_by_user_id' => $actor->getKey(),
                'status' => DocumentStatus::Finalized->value,
                'reason' => trim($data['reason']),
                'idempotency_key' => $data['idempotency_key'],
                'idempotency_hash' => $idempotencyHash,
                'refund_amount' => (string) $refund,
            ]);
            $returnItem = StockReturnItem::query()->create([
                'stock_return_id' => $return->getKey(),
                'spare_part_id' => $saleItem->spare_part_id,
                'location_id' => $saleItem->location_id,
                'quantity' => (string) $quantity,
                'unit_cost' => $saleItem->unit_cost,
                'condition' => $data['condition'],
                'notes' => trim($data['reason']),
                'pos_sale_item_id' => $saleItem->getKey(),
                'refund_amount' => (string) $refund,
            ]);

            if ($data['condition'] === 'resalable') {
                $movement = $this->inventory->recordResalableReturn($part, $location, $returnItem, $actor, $return->return_number);
            } else {
                $movement = null;
            }

            if ($refund->isGreaterThan(0)) {
                $payment = Payment::query()
                    ->whereHas('paymentAllocations', fn ($query) => $query
                        ->where('target_type', 'pos_sale')
                        ->where('pos_sale_id', $sale->getKey()))
                    ->first();
                if ($payment === null) {
                    throw ValidationException::withMessages(['refund_amount' => __('pos.sale_payment_missing')]);
                }
                $reversal = $this->payments->reverse($payment, [
                    'reversal_type' => 'refund',
                    'amount' => (string) $refund,
                    'method' => $data['refund_method'],
                    'external_reference' => null,
                    'reason' => trim($data['reason']),
                    'idempotency_key' => $data['idempotency_key'],
                ], $actor);
                $return->forceFill(['refund_payment_reversal_id' => $reversal->getKey()])->save();
                $reversed = BigDecimal::of((string) $payment->paymentReversals()->sum('amount'))->toScale(2);
                $sale->forceFill([
                    'payment_status' => $reversed->compareTo((string) $payment->amount) >= 0 ? 'refunded' : 'partially_refunded',
                ])->save();
            }

            BusinessAudit::record($actor, 'pos.sale.returned', StockReturn::class, (int) $return->getKey(), newValues: [
                'return_number' => $return->return_number,
                'sale_number' => $sale->sale_number,
                'sale_item_id' => $saleItem->getKey(),
                'quantity' => (string) $quantity,
                'condition' => $data['condition'],
                'refund_amount' => (string) $refund,
                'stock_movement_id' => $movement?->getKey(),
            ]);

            return $return->load(['posSale', 'items.posSaleItem', 'items.stockMovements', 'refundPaymentReversal']);
        }, 3);
    }

    private function returnNumber(): string
    {
        $sequence = DB::table('document_sequences')->where('scope', 'pos_return')->lockForUpdate()->firstOrFail();
        $number = $sequence->prefix.str_pad((string) $sequence->next_value, (int) $sequence->padding, '0', STR_PAD_LEFT);
        DB::table('document_sequences')->where('id', $sequence->id)->increment('next_value');

        return $number;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function idempotencyHash(array $data): string
    {
        return hash('sha256', json_encode([
            'pos_sale_item_id' => (int) $data['pos_sale_item_id'],
            'quantity' => (string) BigDecimal::of((string) $data['quantity'])->toScale(3, RoundingMode::Unnecessary),
            'condition' => (string) $data['condition'],
            'refund_amount' => (string) BigDecimal::of((string) ($data['refund_amount'] ?? '0.00'))->toScale(2, RoundingMode::HalfUp),
            'refund_method' => $data['refund_method'] ?? null,
            'reason' => trim((string) $data['reason']),
        ], JSON_THROW_ON_ERROR));
    }

    private function assertIdempotentReplay(StockReturn $return, string $idempotencyHash): void
    {
        if (! is_string($return->idempotency_hash) || ! hash_equals($return->idempotency_hash, $idempotencyHash)) {
            throw ValidationException::withMessages(['idempotency_key' => __('pos.idempotency_conflict')]);
        }
    }
}
