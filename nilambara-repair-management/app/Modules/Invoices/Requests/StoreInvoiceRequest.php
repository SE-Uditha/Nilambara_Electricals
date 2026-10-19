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
        ];
    }
}
