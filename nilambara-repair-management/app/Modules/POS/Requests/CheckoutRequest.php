<?php

namespace App\Modules\POS\Requests;

use App\Enums\PaymentMethod;
use App\Rules\DecimalValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('idempotency_key')) {
            $this->merge(['idempotency_key' => (string) Str::uuid()]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('pos.sale') === true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount_tendered' => ['required_if:payment_method,cash', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', new DecimalValue],
            'payment_reference' => ['required_if:payment_method,bank_transfer,cheque', 'nullable', 'string', 'max:100'],
            'cheque_date' => ['required_if:payment_method,cheque', 'nullable', 'date'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'discount_amount' => ['nullable', 'numeric', 'decimal:0,2', 'gte:0', new DecimalValue],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'uuid', 'max:100'],
        ];
    }
}
