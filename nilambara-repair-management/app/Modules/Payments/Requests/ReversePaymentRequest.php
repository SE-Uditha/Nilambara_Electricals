<?php

namespace App\Modules\Payments\Requests;

use App\Enums\PaymentMethod;
use App\Rules\DecimalValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ReversePaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('idempotency_key')) {
            $this->merge(['idempotency_key' => (string) Str::uuid()]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('payments.reverse') === true;
    }

    public function rules(): array
    {
        return [
            'reversal_type' => ['required', Rule::in(['refund', 'reversal'])],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', new DecimalValue],
            'method' => ['required_if:reversal_type,refund', 'nullable', Rule::enum(PaymentMethod::class)],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'idempotency_key' => ['required', 'uuid', 'max:100'],
        ];
    }
}
