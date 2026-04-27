<?php

namespace App\Modules\Candidate\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertCandidateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'headline' => ['required', 'string', 'max:255'],
            'base_summary' => ['required', 'string'],
            'years_experience' => ['required', 'integer', 'min:0', 'max:60'],
            'preferred_roles' => ['nullable', 'array'],
            'preferred_roles.*' => ['string', 'max:255'],
            'preferred_locations' => ['nullable', 'array'],
            'preferred_locations.*' => ['string', 'max:255'],
            'preferred_job_types' => ['nullable', 'array'],
            'preferred_job_types.*' => ['string', 'max:255'],
            'core_skills' => ['nullable', 'array'],
            'core_skills.*' => ['string', 'max:255'],
            'nice_to_have_skills' => ['nullable', 'array'],
            'nice_to_have_skills.*' => ['string', 'max:255'],
            'resume_master_path' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:2048'],
            'github_url' => ['nullable', 'url', 'max:2048'],
            'portfolio_url' => ['nullable', 'url', 'max:2048'],
            'experiences' => ['nullable', 'array'],
            'experiences.*.company' => ['required', 'string', 'max:255'],
            'experiences.*.title' => ['required', 'string', 'max:255'],
            'experiences.*.start_date' => ['nullable', 'date'],
            'experiences.*.end_date' => ['nullable', 'date'],
            'experiences.*.description' => ['required', 'string'],
            'projects' => ['nullable', 'array'],
            'projects.*.name' => ['required', 'string', 'max:255'],
            'projects.*.description' => ['required', 'string'],
            'projects.*.skills' => ['nullable', 'array'],
            'projects.*.skills.*' => ['string', 'max:255'],
        ];
    }
}
