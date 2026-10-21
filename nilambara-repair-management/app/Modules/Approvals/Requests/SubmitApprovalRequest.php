<?php

namespace App\Modules\Approvals\Requests;

use App\Modules\Approvals\Services\ApprovalAccess;
use App\Rules\DecimalValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && is_string($this->input('kind')) && array_key_exists($this->input('kind'), ApprovalAccess::requestKinds($this->user()));
    }

    public function rules(): array
    {
        $kind = $this->input('kind');
        $rules = ['kind' => ['required', Rule::in(array_keys(ApprovalAccess::REQUEST))], 'reason' => ['required', 'string', 'min:5', 'max:2000'], 'idempotency_key' => ['required', 'uuid']];
        if (in_array($kind, ['payment_refund', 'payment_reversal'], true)) {
            $rules += ['payment_id' => ['required', 'integer', 'exists:payments,id'], 'amount' => ['required', 'numeric', 'gt:0', new DecimalValue], 'method' => [$kind === 'payment_refund' ? 'required' : 'nullable', Rule::in(['cash', 'bank_transfer', 'cheque'])], 'external_reference' => ['nullable', 'string', 'max:100']];
        } elseif (in_array($kind, ['stock_adjustment', 'stock_count'], true)) {
            $rules += ['spare_part_id' => ['required', 'integer', 'exists:spare_parts,id'], 'location_id' => ['required', 'integer', 'exists:stock_locations,id'], 'quantity' => ['required', 'numeric', 'decimal:0,3', 'between:-999999999999.999,999999999999.999'], 'adjustment_type' => ['required', Rule::in(['correction', 'damaged'])]];
        } elseif ($kind === 'pos_discount') {
            $rules += ['customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('is_active', true)], 'amount' => ['required', 'numeric', 'gt:0', new DecimalValue]];
        }

        return $rules;
    }
}
