<?php

namespace App\Modules\RepairJobs\Requests;

use App\Enums\JobStatus;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $job = $this->route('job');

        return $job instanceof RepairJob && $this->user()?->can('updateStatus', $job) === true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(JobStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'cancellation_reason' => [
                Rule::requiredIf(fn (): bool => $this->input('status') === JobStatus::Cancelled->value),
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('status') === JobStatus::Cancelled->value && ! $this->user()?->can('jobs.cancel')) {
                $validator->errors()->add('status', __('ui.cancellation_not_authorized'));
            }

            if ($this->input('status') === JobStatus::RepairCompleted->value && ! $this->user()?->can('jobs.complete')) {
                $validator->errors()->add('status', __('ui.completion_not_authorized'));
            }
        });
    }
}
