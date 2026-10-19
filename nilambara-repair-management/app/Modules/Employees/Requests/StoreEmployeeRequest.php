<?php

namespace App\Modules\Employees\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreEmployeeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => strtolower(trim($this->input('username')))]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('employees.create') === true
            && (! $this->boolean('create_account')
                || ($this->user()?->can('users.create') === true && $this->user()?->can('roles.manage') === true));
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
            'create_account' => ['sometimes', 'boolean'],
            'username' => [
                Rule::requiredIf($this->boolean('create_account')),
                'nullable',
                'string',
                'min:3',
                'max:64',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username'),
            ],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'role_id' => [
                Rule::requiredIf($this->boolean('create_account')),
                'nullable',
                Rule::exists('roles', 'id')->where('guard_name', 'web')->where('is_active', true),
            ],
            'password' => [
                Rule::requiredIf($this->boolean('create_account')),
                'nullable',
                'string',
                Password::min(12)->mixedCase()->letters()->numbers(),
                'confirmed',
            ],
        ];
    }
}
