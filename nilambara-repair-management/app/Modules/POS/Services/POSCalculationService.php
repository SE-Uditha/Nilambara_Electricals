<?php

namespace App\Modules\POS\Services;

use App\Modules\Inventory\Models\SparePart;
use App\Modules\Inventory\Services\InventoryService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class POSCalculationService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @param  array<int, string>  $quantities
     * @return array{subtotal: BigDecimal, total: BigDecimal, items: array<int, array<string, mixed>>}
     */
    public function calculate(Collection $parts, array $quantities, string $discount = '0.00'): array
    {
        $items = [];
        $subtotal = BigDecimal::zero()->toScale(2);

        foreach ($quantities as $id => $quantityString) {
            /** @var SparePart|null $part */
            $part = $parts->get((int) $id);
            if ($part === null || ! $part->is_active) {
                throw ValidationException::withMessages(['cart' => __('pos.product_unavailable')]);
            }
            $quantity = BigDecimal::of($quantityString)->toScale((int) $part->unit->quantity_scale);
            if (! $quantity->isGreaterThan(0)) {
                throw ValidationException::withMessages(['cart' => __('pos.quantity_positive')]);
            }
            $unitPrice = BigDecimal::of((string) $part->selling_price)->toScale(4);
            $lineTotal = $quantity->multipliedBy($unitPrice)->toScale(2, RoundingMode::HalfUp);
            $subtotal = $subtotal->plus($lineTotal)->toScale(2);
            $items[] = [
                'part' => $part,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'available' => $this->inventory->availableAtDefaultLocation($part),
            ];
        }

        $discountAmount = BigDecimal::of($discount)->toScale(2, RoundingMode::HalfUp);
        if ($discountAmount->isLessThan(0) || $discountAmount->isGreaterThan($subtotal)) {
            throw ValidationException::withMessages(['discount_amount' => __('pos.discount_invalid')]);
        }

        return [
            'subtotal' => $subtotal,
            'total' => $subtotal->minus($discountAmount)->toScale(2),
            'items' => $items,
        ];
    }
}
