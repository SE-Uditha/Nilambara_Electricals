<?php

namespace App\Modules\Quotations\Services;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class QuotationCalculationService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, array<string, mixed>>, parts_total: string, labour_charge: string, total: string}
     */
    public function calculate(array $items, string|int $labourCharge): array
    {
        try {
            $partsTotal = BigDecimal::zero()->toScale(2);
            $calculatedItems = [];

            foreach ($items as $index => $item) {
                $quantity = BigDecimal::of((string) $item['quantity'])->toScale(3);
                $unitPrice = BigDecimal::of((string) $item['unit_price'])->toScale(4);
                $lineTotal = $quantity->multipliedBy($unitPrice)->toScale(2, RoundingMode::HalfUp);
                $partsTotal = $partsTotal->plus($lineTotal);

                $calculatedItems[] = [
                    ...$item,
                    'display_order' => $index + 1,
                    'quantity' => (string) $quantity,
                    'unit_price' => (string) $unitPrice,
                    'line_total' => (string) $lineTotal,
                ];
            }

            $labour = BigDecimal::of($labourCharge)->toScale(2);
            $total = $partsTotal->plus($labour)->toScale(2);
            $maximum = BigDecimal::of('99999999999999999.99');
            if ($partsTotal->isGreaterThan($maximum) || $total->isGreaterThan($maximum)) {
                throw new \InvalidArgumentException('Quotation total exceeds DECIMAL(19,2) capacity.');
            }
            foreach ($calculatedItems as $item) {
                if (BigDecimal::of($item['line_total'])->isGreaterThan($maximum)) {
                    throw new \InvalidArgumentException('Quotation line exceeds DECIMAL(19,2) capacity.');
                }
            }
        } catch (\InvalidArgumentException|MathException $exception) {
            throw ValidationException::withMessages([
                'items' => __('ui.quotation_invalid_amount'),
            ]);
        }

        return [
            'items' => $calculatedItems,
            'parts_total' => (string) $partsTotal,
            'labour_charge' => (string) $labour,
            'total' => (string) $total,
        ];
    }
}
