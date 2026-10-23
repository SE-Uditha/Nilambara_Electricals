<?php

namespace App\Modules\RepairJobs\Requests;

use App\Modules\Approvals\Services\ApprovalAccess;

class RequestRepairJobEditRequest extends UpdateRepairJobRequest
{
    public function authorize(): bool
    {
        return ApprovalAccess::canRequestChange($this->user(), 'job_update');
    }

    public function rules(): array
    {
        return parent::rules() + [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
