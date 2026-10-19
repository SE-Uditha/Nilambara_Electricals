<?php

namespace App\Modules\POS\Requests;

use App\Enums\PaymentMethod;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class POSReturnRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('idempotency_key')) {
            $this->merge(['idempotency_key' => (string) Str::uuid()]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('pos.return') === true;
    }

    public function rules(): array
    {
        return [
            'pos_sale_item_id' => ['required', 'integer', 'exists:pos_sale_items,id'],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999999999.999'],
            'condition' => ['required', Rule::in(['resalable', 'damaged'])],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'refund_amount' => ['nullable', 'numeric', 'decimal:0,2', 'gte:0', 'max:99999999999999999.99'],
            'refund_method' => [
                Rule::requiredIf(fn (): bool => is_numeric($this->input('refund_amount'))
                    && BigDecimal::of((string) $this->input('refund_amount'))->isGreaterThan(0)),
                'nullable',
                Rule::enum(PaymentMethod::class),
            ],
            'idempotency_key' => ['required', 'uuid', 'max:100'],
        ];
    }
}
