<?php

namespace App\Modules\Approvals\Services;

use App\Models\User;
use App\Modules\Approvals\Models\ApprovalRequest;
use App\Modules\Inventory\Models\SparePart;
use App\Modules\POS\Services\POSCalculationService;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

class DiscountApprovalService
{
    public function context(array $cart, ?int $customerId, string $amount): array
    {
        if ($cart === [] || count($cart) > 100) {
            throw ValidationException::withMessages(['cart' => 'Add products to the POS cart before requesting a discount.']);
        }
        ksort($cart, SORT_NUMERIC);
        $parts = SparePart::with('unit')->whereIn('id', array_keys($cart))->get()->keyBy('id');
        $summary = app(POSCalculationService::class)->calculate($parts, $cart, $amount);
        if (! $summary['total']->isPositive() || ! BigDecimal::of($amount)->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'The discount must be positive and leave a positive sale total.']);
        }

        return [
            'customer_id' => $customerId,
            'cart' => collect($cart)->map(fn ($quantity) => (string) BigDecimal::of((string) $quantity)->toScale(3))->all(),
            'prices' => $parts->sortKeys()->map(fn ($part) => [$part->selling_price, $part->default_location_id])->all(),
            'amount' => (string) BigDecimal::of($amount)->toScale(2),
        ];
    }

    public function consume(int $id, array $cart, array $data, User $actor): void
    {
        $approval = ApprovalRequest::whereKey($id)->lockForUpdate()->first();
        abort_unless($approval && $approval->kind === 'pos_discount' && (int) $approval->requested_by_user_id === (int) $actor->id, 403);
        $context = $this->context($cart, empty($data['customer_id']) ? null : (int) $data['customer_id'], (string) $data['discount_amount']);
        if ($approval->status !== 'approved' || $approval->consumed_at || ! hash_equals($approval->payload['context_hash'], hash('sha256', json_encode($context, JSON_THROW_ON_ERROR)))) {
            throw ValidationException::withMessages(['discount_approval_id' => 'Use an unused approved discount for this cashier, customer, cart, prices and amount. Request another approval if the sale has changed.']);
        }
        $approval->update(['consumed_at' => now()]);
    }
}
