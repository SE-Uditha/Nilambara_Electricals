<?php

namespace App\Modules\Customers\Requests;

use App\Enums\CustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('customer')) === true;
    }

    public function rules(): array
    {
        $type = $this->input('customer_type');

        return [
            'customer_type' => ['required', Rule::enum(CustomerType::class)],
            'customer_name' => [
                Rule::requiredIf($type === CustomerType::Individual->value),
                'nullable',
                'string',
                'max:255',
            ],
            'company_name' => [
                Rule::requiredIf($type === CustomerType::Company->value),
                'nullable',
                'string',
                'max:255',
            ],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:5000'],
            'mobile_number' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9().\s-]{5,28}[0-9]$/'],
            'alternate_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9().\s-]{5,28}[0-9]$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'special_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
