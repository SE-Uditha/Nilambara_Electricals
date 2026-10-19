<?php

namespace App\Modules\Quotations\Requests;

use App\Modules\Quotations\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordQuotationDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');

        return $quotation instanceof Quotation
            && $this->user()?->can('recordDecision', $quotation) === true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_date' => ['required', 'date', 'before_or_equal:now'],
            'customer_contact_name' => ['required', 'string', 'max:255'],
            'approval_method' => ['required', Rule::in(['in_person', 'telephone', 'email', 'other'])],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }
}
