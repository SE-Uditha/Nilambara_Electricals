<?php

namespace App\Modules\Approvals\Services;

use App\Models\User;

class ApprovalAccess
{
    public const LABELS = [
        'purchase' => 'Purchase approval',
        'payment_refund' => 'Payment refund', 'payment_reversal' => 'Payment reversal',
        'stock_adjustment' => 'Stock correction / damaged stock',
        'stock_count' => 'Physical stock count',
    ];

    public const REVIEW = [
        'purchase' => ['purchases.view', 'purchases.approve'],
        'payment_refund' => ['payments.view', 'payments.reverse'],
        'payment_reversal' => ['payments.view', 'payments.reverse'],
        'stock_adjustment' => ['inventory.view', 'inventory.adjust'],
        'stock_count' => ['inventory.view', 'inventory.stock-count'],
    ];

    public const REQUEST = [
        'payment_refund' => 'payments.view', 'payment_reversal' => 'payments.view',
        'stock_adjustment' => 'inventory.view',
        'stock_count' => 'inventory.view',
    ];

    public static function canReview(User $user, string $kind): bool
    {
        return $user->hasActiveAccount() && $user->hasAnyRole(['administrator', 'manager'])
            && isset(self::REVIEW[$kind]) && collect(self::REVIEW[$kind])->every(fn ($permission) => $user->can($permission));
    }

    public static function reviewKinds(User $user): array
    {
        return array_values(array_filter(array_keys(self::REVIEW), fn ($kind) => self::canReview($user, $kind)));
    }

    public static function requestKinds(User $user): array
    {
        return array_filter(self::REQUEST, fn ($permission) => $user->hasActiveAccount() && $user->can($permission));
    }
}
