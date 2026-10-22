<?php

namespace App\Modules\Quotations\Policies;

use App\Enums\ApprovalStatus;
use App\Enums\QuotationPreparationStatus;
use App\Models\User;
use App\Modules\Quotations\Models\Quotation;
use Illuminate\Support\Facades\Gate;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('quotations.view');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        if (! $user->can('quotations.view')) {
            return false;
        }

        if ($user->hasRole('cashier') && ! $user->can('quotations.approve')) {
            return $quotation->approval_status === ApprovalStatus::Approved
                && $quotation->preparation_status === QuotationPreparationStatus::Issued;
        }

        return Gate::allows('view', $quotation->repairJob);
    }

    public function create(User $user): bool
    {
        return $user->can('quotations.create');
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.update')
            && $quotation->preparation_status === QuotationPreparationStatus::Draft
            && Gate::allows('view', $quotation->repairJob);
    }

    public function issue(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.issue')
            && $quotation->preparation_status === QuotationPreparationStatus::Draft
            && Gate::allows('view', $quotation->repairJob);
    }

    public function recordDecision(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.approve')
            && $quotation->preparation_status === QuotationPreparationStatus::Issued
            && $quotation->approval_status === ApprovalStatus::Pending
            && Gate::allows('view', $quotation->repairJob);
    }

    public function revise(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.revise')
            && $quotation->preparation_status === QuotationPreparationStatus::Issued
            && Gate::allows('view', $quotation->repairJob);
    }

    public function cancel(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.cancel')
            && in_array($quotation->preparation_status, [
                QuotationPreparationStatus::Draft,
                QuotationPreparationStatus::Issued,
            ], true)
            && $quotation->approval_status !== ApprovalStatus::Approved
            && Gate::allows('view', $quotation->repairJob);
    }

    public function print(User $user, Quotation $quotation): bool
    {
        return $user->can('quotations.print')
            && $this->view($user, $quotation);
    }
}
