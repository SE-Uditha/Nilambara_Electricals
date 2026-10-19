<?php

namespace App\Modules\Invoices\Policies;

use App\Enums\DocumentStatus;
use App\Models\User;
use App\Modules\Invoices\Models\Invoice;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create');
    }

    public function updateDraft(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.update-draft')
            && $invoice->status === DocumentStatus::Draft;
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.issue')
            && $invoice->status === DocumentStatus::Draft;
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.cancel')
            && in_array($invoice->status, [DocumentStatus::Draft, DocumentStatus::Finalized], true);
    }

    public function print(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.print') && $this->view($user, $invoice);
    }

    public function export(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.export') && $this->view($user, $invoice);
    }
}
