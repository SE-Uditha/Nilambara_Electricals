<?php

namespace App\Modules\Quotations\Requests;

use App\Modules\Quotations\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');

        return $quotation instanceof Quotation
            && $this->user()?->can('update', $quotation) === true;
    }

    public function rules(): array
    {
        return [
            'quotation_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:quotation_date'],
            'proposed_repair_description' => ['required', 'string', 'max:10000'],
            'labour_charge' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:99999999999999999.99'],
            'terms_and_conditions' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.spare_part_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
            'items.*.description' => ['nullable', 'string', 'max:1000', 'required_without:items.*.spare_part_id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3', 'max:999999999.999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,4', 'max:99999999.9999', 'required_without:items.*.spare_part_id'],
            'intent' => ['sometimes', Rule::in(['draft', 'preview'])],
        ];
    }
}
