<?php

namespace App\Modules\Customers\Policies;

use App\Models\User;
use App\Modules\Customers\Models\Customer;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.update');
    }

    public function activate(User $user, Customer $customer): bool
    {
        return $user->can('customers.update');
    }

    public function deactivate(User $user, Customer $customer): bool
    {
        return $user->can('customers.deactivate');
    }

    public function statement(User $user, Customer $customer): bool
    {
        return $user->can('customers.statement');
    }

    public function search(User $user): bool
    {
        return $user->can('customers.view');
    }
}
