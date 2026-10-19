<?php

namespace App\Modules\RepairReceipts\Requests;

use App\Modules\RepairJobs\Enums\RepairJobType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRepairReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('receipts.create') === true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'job_number' => ['required', 'string', 'max:60', 'unique:repair_jobs,job_number'],
            'job_type' => ['required', Rule::enum(RepairJobType::class)],
            'item_name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'required_without:model', 'string', 'max:150'],
            'model' => ['nullable', 'required_without:brand', 'string', 'max:150'],
            'item_description' => ['nullable', 'string', 'max:10000'],
            'reported_fault' => ['nullable', 'string', 'max:10000'],
            'accessories_received' => ['nullable', 'string', 'max:5000'],
            'physical_condition' => ['nullable', 'string', 'max:5000'],
            'visible_damage' => ['nullable', 'string', 'max:5000'],
            'advance_payment' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999999999999.99'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank_transfer', 'cheque'])],
            'labor_cost' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999999999999.99'],
            'received_by_employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'special_notes' => ['nullable', 'string', 'max:10000'],
            'intent' => ['nullable', Rule::in(['save', 'print'])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $amount = $this->input('advance_payment');

                if (is_numeric($amount) && (float) $amount > 0 && ! in_array($this->input('payment_method'), ['cash', 'bank_transfer', 'cheque'], true)) {
                    $validator->errors()->add('payment_method', __('ui.payment_method_required_for_advance'));
                }
            },
        ];
    }
}
