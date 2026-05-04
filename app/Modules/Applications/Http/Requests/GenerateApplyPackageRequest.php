<?php

namespace App\Modules\Applications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateApplyPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'career_profile_id' => ['sometimes', 'integer', 'exists:candidate_profiles,id'],
            'profile_id' => ['sometimes', 'integer', 'exists:candidate_profiles,id'],
            'job_path_id' => ['sometimes', 'nullable', 'integer', 'exists:job_paths,id'],
            'force' => ['sometimes', 'boolean'],
            'override_low_match' => ['sometimes', 'boolean'],
            'continue_anyway' => ['sometimes', 'boolean'],
            'override_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'sections' => ['sometimes', 'array', 'min:1'],
            'sections.*' => ['string', 'in:tailored_resume,cover_letter,application_answers,salary_answer,notice_period_answer,interest_answer,strengths_gaps,interview_questions,follow_up_email'],
        ];
    }
}
