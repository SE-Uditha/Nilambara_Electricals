<?php

namespace App\Modules\RepairJobs\Requests;

use App\Modules\Employees\Models\Employee;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $job = $this->route('job');

        return $job instanceof RepairJob && $this->user()?->can('assign', $job) === true;
    }

    public function rules(): array
    {
        return [
            'technician_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->any()) {
                    return;
                }

                $employee = Employee::query()->with('user')->find($this->input('technician_id'));

                if ($employee === null || ! $employee->is_active || ! $employee->user?->hasActiveAccount() || ! $employee->user->hasRole('technician')) {
                    $validator->errors()->add('technician_id', __('ui.active_technician_required'));
                }
            },
        ];
    }
}
