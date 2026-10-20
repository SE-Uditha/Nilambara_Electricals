<?php

namespace App\Modules\Inventory\Services;

use App\Models\User;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Models\StockLocation;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\POS\Models\PosSaleItem;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function availableAtDefaultLocation(SparePart $part): BigDecimal
    {
        if ($part->default_location_id === null) {
            return BigDecimal::zero()->toScale(3);
        }

        return $this->balance((int) $part->getKey(), (int) $part->default_location_id);
    }

    public function defaultLocationBalancesQuery(?string $asOf = null): Builder
    {
        return DB::table('spare_parts')
            ->leftJoinSub(
                DB::table('stock_movements')
                    ->when($asOf !== null, fn ($query) => $query->where('occurred_at', '<=', $asOf))
                    ->select('spare_part_id', 'location_id')
                    ->selectRaw('SUM(quantity_delta) as available_quantity')
                    ->groupBy('spare_part_id', 'location_id'),
                'stock_balances',
                function ($join): void {
                    $join->on('stock_balances.spare_part_id', '=', 'spare_parts.id')
                        ->on('stock_balances.location_id', '=', 'spare_parts.default_location_id');
                },
            )
            ->select('spare_parts.*')
            ->selectRaw('COALESCE(stock_balances.available_quantity, 0) as available_quantity');
    }

    /**
     * Lock products in a stable order before validating their shared stock ledger balances.
     *
     * @param  array<int, string>  $quantities  Product ID keyed to decimal quantity.
     * @return Collection<int, SparePart>
     */
    public function lockSaleableParts(array $quantities): Collection
    {
        $ids = array_map('intval', array_keys($quantities));
        sort($ids);

        $parts = SparePart::query()
            ->with(['unit', 'defaultLocation'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (SparePart $part): int => (int) $part->getKey());

        foreach ($ids as $id) {
            $part = $parts->get($id);
            if ($part === null || ! $part->is_active) {
                throw ValidationException::withMessages(['cart' => __('pos.product_unavailable')]);
            }
            if ($part->defaultLocation === null || ! $part->defaultLocation->is_active) {
                throw ValidationException::withMessages(['cart' => __('pos.sale_location_required', ['product' => $part->name])]);
            }

            $requested = BigDecimal::of($quantities[$id])->toScale(3);
            $available = $this->balance($id, (int) $part->default_location_id);
            if (! $requested->isGreaterThan(0) || $requested->isGreaterThan($available)) {
                throw ValidationException::withMessages(['cart' => __('pos.insufficient_stock', [
                    'product' => $part->name,
                    'available' => (string) $available,
                ])]);
            }
        }

        return $parts;
    }

    public function recordPosSale(PosSaleItem $item, User $actor): StockMovement
    {
        $existing = StockMovement::query()->where('pos_sale_item_id', $item->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }

        return StockMovement::query()->create([
            'spare_part_id' => $item->spare_part_id,
            'location_id' => $item->location_id,
            'movement_type' => 'pos_sale',
            'quantity_delta' => (string) BigDecimal::of((string) $item->quantity)->negated()->toScale(3),
            'unit_cost' => $item->unit_cost,
            'occurred_at' => now(config('business.timezone', 'Asia/Colombo')),
            'reference_number' => $item->posSale->sale_number,
            'recorded_by_user_id' => $actor->getKey(),
            'reason' => __('pos.stock_sale_reason', ['sale' => $item->posSale->sale_number]),
            'idempotency_key' => 'pos-stock-sale:'.$item->getKey(),
            'pos_sale_item_id' => $item->getKey(),
        ]);
    }

    public function recordResalableReturn(SparePart $part, StockLocation $location, $returnItem, User $actor, string $reference): StockMovement
    {
        return StockMovement::query()->create([
            'spare_part_id' => $part->getKey(),
            'location_id' => $location->getKey(),
            'movement_type' => 'customer_return',
            'quantity_delta' => (string) BigDecimal::of((string) $returnItem->quantity)->toScale(3),
            'unit_cost' => $returnItem->unit_cost,
            'occurred_at' => now(config('business.timezone', 'Asia/Colombo')),
            'reference_number' => $reference,
            'recorded_by_user_id' => $actor->getKey(),
            'reason' => __('pos.stock_return_reason', ['return' => $reference]),
            'idempotency_key' => 'pos-stock-return:'.$returnItem->getKey(),
            'return_item_id' => $returnItem->getKey(),
        ]);
    }

    private function balance(int $partId, int $locationId): BigDecimal
    {
        $query = DB::table('stock_movements')
            ->where('spare_part_id', $partId)
            ->where('location_id', $locationId);

        // Checkout may have opened a repeatable-read snapshot before waiting
        // for the product lock. A locking current read sees the winner's sale.
        $sum = DB::transactionLevel() > 0
            ? $query->lockForUpdate()->pluck('quantity_delta')
                ->reduce(fn (BigDecimal $sum, $quantity) => $sum->plus((string) $quantity), BigDecimal::zero())
            : $query->sum('quantity_delta');

        return BigDecimal::of((string) ($sum ?? '0'))->toScale(3);
    }
}
