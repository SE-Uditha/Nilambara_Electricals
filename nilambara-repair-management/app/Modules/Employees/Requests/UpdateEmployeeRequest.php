<?php

namespace App\Modules\Employees\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('employees.update') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
            'mobile_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+(). -]{7,30}$/'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'date_joined' => ['nullable', 'date', 'before_or_equal:today'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
