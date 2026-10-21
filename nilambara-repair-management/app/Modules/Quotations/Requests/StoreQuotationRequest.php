<?php

namespace App\Modules\Quotations\Requests;

use App\Rules\DecimalValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('quotations.create') === true;
    }

    public function rules(): array
    {
        return [
            'repair_job_id' => ['required', 'integer', 'exists:repair_jobs,id'],
            'quotation_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:quotation_date'],
            'proposed_repair_description' => ['required', 'string', 'max:10000'],
            'labour_charge' => ['required', 'numeric', 'min:0', 'decimal:0,2', new DecimalValue],
            'terms_and_conditions' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.spare_part_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
            'items.*.description' => ['nullable', 'string', 'max:1000', 'required_without:items.*.spare_part_id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999.999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4', 'max:99999999.9999', 'required_without:items.*.spare_part_id'],
            'intent' => ['sometimes', Rule::in(['draft', 'preview', 'issue'])],
        ];
    }
}
