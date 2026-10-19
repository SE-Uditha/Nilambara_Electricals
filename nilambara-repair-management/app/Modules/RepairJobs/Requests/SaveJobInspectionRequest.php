<?php

namespace App\Modules\RepairJobs\Requests;

use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveJobInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $job = $this->route('job');

        return $job instanceof RepairJob && $this->user()?->can('inspect', $job) === true;
    }

    public function rules(): array
    {
        return [
            'inspected_at' => ['nullable', 'date'],
            'existing_fault' => ['nullable', 'string', 'max:10000'],
            'inspection_result' => ['required', 'string', 'max:10000'],
            'technical_information' => ['nullable', 'string', 'max:10000'],
            'special_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $value = $this->input('inspected_at');
                $job = $this->route('job');

                $timestamp = is_string($value) ? strtotime($value) : false;

                if ($timestamp !== false && $job instanceof RepairJob && $timestamp < $job->received_at->timestamp) {
                    $validator->errors()->add('inspected_at', __('ui.inspection_date_before_received'));
                }
            },
        ];
    }
}
