<?php

namespace App\Modules\Payments\Requests;

use App\Enums\PaymentMethod;
use App\Modules\Employees\Models\Employee;
use App\Rules\DecimalValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('idempotency_key')) {
            $this->merge(['idempotency_key' => (string) Str::uuid()]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('payments.create') === true
            && $this->user()?->can('payments.receive') === true;
    }

    public function rules(): array
    {
        return [
            'payment_type' => ['required', Rule::in(['invoice', 'advance'])],
            'invoice_id' => ['required_if:payment_type,invoice', 'nullable', 'integer', 'exists:invoices,id'],
            'customer_id' => ['required_if:payment_type,advance', 'nullable', 'integer', 'exists:customers,id'],
            'repair_job_id' => ['nullable', 'integer', 'exists:repair_jobs,id'],
            'received_by_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required_unless:method,credit', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', new DecimalValue],
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::selectable()))],
            'reference_number' => ['nullable', 'string', 'max:100', 'required_if:method,bank_transfer', 'required_if:method,cheque'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'cheque_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'idempotency_key' => ['required', 'uuid', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if ($validator->errors()->any()) {
                    return;
                }

                if ($this->input('payment_type') === 'invoice' && $this->filled('repair_job_id')) {
                    $validator->errors()->add('repair_job_id', __('payments.invoice_payment_job_not_allowed'));
                }
                if ($this->input('payment_type') === 'advance' && $this->filled('invoice_id')) {
                    $validator->errors()->add('invoice_id', __('payments.advance_invoice_not_allowed'));
                }
                if ($this->input('method') === PaymentMethod::Credit->value && $this->input('payment_type') !== 'invoice') {
                    $validator->errors()->add('method', __('payments.credit_only_for_invoices'));
                }
                if ($this->input('method') !== PaymentMethod::Cheque->value && $this->filled('cheque_date')) {
                    $validator->errors()->add('cheque_date', __('payments.cheque_date_only_for_cheques'));
                }
                if ($this->filled('received_by_employee_id')) {
                    $actingEmployeeId = Employee::query()
                        ->where('user_id', $this->user()?->getKey())
                        ->where('is_active', true)
                        ->value('id');
                    if ((int) $this->input('received_by_employee_id') !== (int) $actingEmployeeId
                        && ! $this->user()?->can('payments.receive-for-others')) {
                        $validator->errors()->add('received_by_employee_id', __('payments.receiver_change_not_allowed'));
                    } elseif (! Employee::query()
                        ->whereKey($this->input('received_by_employee_id'))
                        ->where('is_active', true)
                        ->exists()) {
                        $validator->errors()->add('received_by_employee_id', __('payments.active_receiver_required'));
                    }
                }
            },
        ];
    }
}
