<?php

namespace App\Modules\Applications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_id' => ['required', 'integer', 'exists:jobs,id'],
            'profile_id' => ['required', 'integer', 'exists:candidate_profiles,id'],
            'job_match_id' => ['nullable', 'integer', 'exists:job_matches,id'],
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
