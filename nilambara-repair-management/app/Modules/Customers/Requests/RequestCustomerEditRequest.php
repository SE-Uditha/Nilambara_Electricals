<?php

namespace App\Modules\Customers\Requests;

use App\Modules\Approvals\Services\ApprovalAccess;

class RequestCustomerEditRequest extends UpdateCustomerRequest
{
    public function authorize(): bool
    {
        return ApprovalAccess::canRequestChange($this->user(), 'customer_update');
    }

    public function rules(): array
    {
        return parent::rules() + [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
