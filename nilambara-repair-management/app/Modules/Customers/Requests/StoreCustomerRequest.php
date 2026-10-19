<?php

namespace App\Modules\Customers\Requests;

use App\Enums\CustomerType;
use App\Modules\Customers\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) === true;
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
            'opening_balance' => $this->user()?->can('customers.statement') === true
                ? ['sometimes', 'required', 'numeric', 'decimal:0,2']
                : ['prohibited'],
            'special_notes' => ['nullable', 'string', 'max:10000'],
            'registration_date' => ['required', 'date'],
            'is_active' => $this->user()?->can('customers.deactivate') === true
                ? ['sometimes', 'boolean']
                : ['prohibited'],
        ];
    }
}
