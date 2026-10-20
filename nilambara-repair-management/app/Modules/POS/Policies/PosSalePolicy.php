<?php

namespace App\Modules\POS\Policies;

use App\Models\User;
use App\Modules\POS\Models\PosSale;

class PosSalePolicy
{
    public function view(User $user, PosSale $sale): bool
    {
        return $user->hasActiveAccount() && $user->can('pos.sales.view');
    }

    public function print(User $user, PosSale $sale): bool
    {
        return $user->hasActiveAccount() && $user->can('pos.receipt.print');
    }

    public function verifyPayment(User $user, PosSale $sale): bool
    {
        return $user->hasActiveAccount() && $user->can('pos.payment.verify');
    }

    public function cancel(User $user, PosSale $sale): bool
    {
        return $user->hasActiveAccount() && $user->can('pos.sale.cancel');
    }

    public function returnItems(User $user, PosSale $sale): bool
    {
        return $user->hasActiveAccount() && $user->can('pos.return');
    }
}
