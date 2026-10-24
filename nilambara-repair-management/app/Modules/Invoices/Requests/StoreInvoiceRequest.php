<?php

namespace App\Modules\Invoices\Requests;

use App\Enums\PaymentMethod;
use App\Enums\PaymentTerms;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The form offers one "method" list (cash, bank transfer, credit); credit is stored as the payment terms.
        $isCredit = $this->input('payment_method') === 'credit';
        $this->merge([
            'payment_terms' => $isCredit ? 'credit' : ($this->input('payment_terms') ?: 'cash'),
            'payment_method' => $isCredit ? null : $this->input('payment_method'),
        ]);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'repair_job_ids' => ['required', 'array', 'min:1', 'max:30'],
            'repair_job_ids.*' => ['required', 'integer', 'distinct', 'exists:repair_jobs,id'],
            'invoice_date' => ['required', 'date'],
            'payment_terms' => ['required', Rule::enum(PaymentTerms::class)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'collected_by' => ['nullable', 'string', 'max:255'],
            'vehicle_number' => ['nullable', 'string', 'max:40'],
            'delivery_at' => ['nullable', 'date'],
            'delivery_information' => ['nullable', 'string', 'max:5000'],
            'special_notes' => ['nullable', 'string', 'max:5000'],
            'intent' => ['required', Rule::in(['draft', 'issue', 'preview'])],
            'amount_received' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'payment_idempotency_key' => ['nullable', 'uuid'],
        ];
    }
}
