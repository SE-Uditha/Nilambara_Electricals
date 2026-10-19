<?php

namespace App\Modules\RepairJobs\Requests;

use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveRepairInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $job = $this->route('job');

        return $job instanceof RepairJob && $this->user()?->can('repair', $job) === true;
    }

    public function rules(): array
    {
        return [
            'work_performed' => ['required', 'string', 'max:10000'],
            'parts_used' => ['nullable', 'string', 'max:10000'],
            'parts_quantity' => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'parts_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999999999999.99'],
            'labor_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999999999999.99'],
            'repair_completed_at' => ['nullable', 'date'],
            'testing_details' => ['nullable', 'string', 'max:10000'],
            'special_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $value = $this->input('repair_completed_at');
                $job = $this->route('job');

                $timestamp = is_string($value) ? strtotime($value) : false;

                if ($timestamp !== false && $job instanceof RepairJob && $timestamp < $job->received_at->timestamp) {
                    $validator->errors()->add('repair_completed_at', __('ui.completion_date_before_received'));
                }
            },
        ];
    }
}
