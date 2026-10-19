<?php

namespace App\Modules\Payments\Policies;

use App\Models\User;
use App\Modules\Payments\Models\Payment;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('payments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('payments.create') && $user->can('payments.receive');
    }

    public function reverse(User $user, Payment $payment): bool
    {
        return $user->can('payments.reverse') && $payment->payment_type !== 'pos';
    }

    public function print(User $user, Payment $payment): bool
    {
        return $user->can('payments.view') && $user->can('payments.print');
    }

    public function export(User $user, Payment $payment): bool
    {
        return $user->can('payments.view') && $user->can('payments.export');
    }
}
