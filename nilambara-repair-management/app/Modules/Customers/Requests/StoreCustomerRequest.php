<?php

namespace App\Modules\Customers\Requests;

use App\Enums\CustomerType;
use App\Modules\Customers\Models\Customer;
use App\Rules\DecimalValue;
use App\Rules\PhoneNumber;
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
            'mobile_number' => ['required', 'string', 'max:30', new PhoneNumber],
            'alternate_phone' => ['nullable', 'string', 'max:30', new PhoneNumber],
            'email' => ['nullable', 'email', 'max:255'],
            'opening_balance' => $this->user()?->can('customers.statement') === true
                ? ['sometimes', 'required', 'numeric', 'decimal:0,2', new DecimalValue]
                : ['prohibited'],
            'special_notes' => ['nullable', 'string', 'max:10000'],
            'registration_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'is_active' => $this->user()?->can('customers.deactivate') === true
                ? ['sometimes', 'boolean']
                : ['prohibited'],
        ];
    }
}
