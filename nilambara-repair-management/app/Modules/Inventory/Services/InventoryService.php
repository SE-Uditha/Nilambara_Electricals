<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\SparePart;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function latestUnitCost(int $part, int $location): ?string
    {
        $query = DB::table('stock_movements')->where('spare_part_id', $part)->where('location_id', $location)
            ->whereIn('movement_type', ['purchase_receipt', 'opening'])->whereNotNull('unit_cost')
            ->orderByRaw("CASE WHEN movement_type = 'purchase_receipt' THEN 0 ELSE 1 END")
            ->orderByDesc('occurred_at')->orderByDesc('id');
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        $cost = $query->value('unit_cost');

        return $cost === null ? null : (string) BigDecimal::of((string) $cost)->toScale(4);
    }

    public function valuationCostQuery(?string $asOf = null): Builder
    {
        return DB::table('stock_movements as cost_movements')->select('unit_cost')
            ->whereColumn('cost_movements.spare_part_id', 'spare_parts.id')
            ->whereColumn('cost_movements.location_id', 'spare_parts.default_location_id')
            ->whereIn('cost_movements.movement_type', ['purchase_receipt', 'opening'])->whereNotNull('cost_movements.unit_cost')
            ->when($asOf !== null, fn ($query) => $query->where('cost_movements.occurred_at', '<=', $asOf))
            ->orderByRaw("CASE WHEN cost_movements.movement_type = 'purchase_receipt' THEN 0 ELSE 1 END")
            ->orderByDesc('cost_movements.occurred_at')->orderByDesc('cost_movements.id')->limit(1);
    }

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

    private function balance(int $partId, int $locationId): BigDecimal
    {
        $query = DB::table('stock_movements')
            ->where('spare_part_id', $partId)
            ->where('location_id', $locationId);

        // A stock operation may have opened a repeatable-read snapshot before waiting
        // for the product lock. A locking current read sees the latest stock balance.
        $sum = DB::transactionLevel() > 0
            ? $query->lockForUpdate()->pluck('quantity_delta')
                ->reduce(fn (BigDecimal $sum, $quantity) => $sum->plus((string) $quantity), BigDecimal::zero())
            : $query->sum('quantity_delta');

        return BigDecimal::of((string) ($sum ?? '0'))->toScale(3);
    }
}
