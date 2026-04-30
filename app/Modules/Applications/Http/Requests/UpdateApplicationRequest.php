<?php

namespace App\Modules\Applications\Http\Requests;

use App\Modules\Applications\Domain\Enums\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_id' => ['sometimes', 'integer', 'exists:jobs,id'],
            'profile_id' => ['sometimes', 'integer', 'exists:candidate_profiles,id'],
            'tailored_resume_id' => ['nullable', 'integer', 'exists:tailored_resumes,id'],
            'status' => ['sometimes', 'string', Rule::in([...ApplicationStatus::values(), 'interview'])],
            'applied_at' => ['nullable', 'date'],
            'follow_up_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'company_response' => ['nullable', 'string'],
            'interview_date' => ['nullable', 'date'],
        ];
    }
}
