<?php

namespace App\Modules\RepairJobs\Requests;

use App\Modules\RepairJobs\Enums\RepairJobType;
use App\Modules\RepairJobs\Models\RepairJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRepairJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        $job = $this->route('job');

        return $job instanceof RepairJob && $this->user()?->can('update', $job) === true;
    }

    public function rules(): array
    {
        return [
            'job_type' => ['required', Rule::enum(RepairJobType::class)],
            'item_name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'required_without:model', 'string', 'max:150'],
            'model' => ['nullable', 'required_without:brand', 'string', 'max:150'],
            'item_description' => ['nullable', 'string', 'max:10000'],
            'reported_fault' => ['nullable', 'string', 'max:10000'],
            'accessories_received' => ['nullable', 'string', 'max:5000'],
            'physical_condition' => ['nullable', 'string', 'max:5000'],
            'visible_damage' => ['nullable', 'string', 'max:5000'],
            'special_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
