<?php

namespace App\Modules\Applications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'status' => ['sometimes', 'string', 'in:draft,ready_to_apply,applied,rejected,interview,offer'],
            'applied_at' => ['nullable', 'date'],
            'follow_up_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'company_response' => ['nullable', 'string'],
            'interview_date' => ['nullable', 'date'],
        ];
    }
}
