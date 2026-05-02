<?php

namespace App\Modules\Copilot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertCareerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $aliases = [];

        if (! $this->has('display_name') && $this->has('full_name')) {
            $aliases['display_name'] = $this->input('full_name');
        }

        if (! $this->has('title') && $this->has('headline')) {
            $aliases['title'] = $this->input('headline');
        }

        if (! $this->has('professional_summary') && $this->has('base_summary')) {
            $aliases['professional_summary'] = $this->input('base_summary');
        }

        if (! $this->has('years_of_experience') && $this->has('years_experience')) {
            $aliases['years_of_experience'] = $this->input('years_experience');
        }

        if (! $this->has('skills') && $this->has('core_skills')) {
            $aliases['skills'] = $this->input('core_skills');
        }

        if (! $this->has('secondary_skills') && $this->has('nice_to_have_skills')) {
            $aliases['secondary_skills'] = $this->input('nice_to_have_skills');
        }

        if ($aliases !== []) {
            $this->merge($aliases);
        }
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('post');
        $requiredForCreate = $isCreate ? 'required' : 'sometimes';

        return [
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => [$requiredForCreate, 'string', 'max:255'],
            'professional_summary' => [$requiredForCreate, 'string'],
            'primary_role' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seniority_level' => ['sometimes', 'nullable', 'string', 'max:100'],
            'years_of_experience' => [$requiredForCreate, 'integer', 'min:0', 'max:60'],
            'skills' => [$requiredForCreate, 'array', 'min:1'],
            'skills.*' => ['string', 'max:255'],
            'secondary_skills' => ['sometimes', 'nullable', 'array'],
            'secondary_skills.*' => ['string', 'max:255'],
            'tools' => ['sometimes', 'nullable', 'array'],
            'tools.*' => ['string', 'max:255'],
            'industries' => ['sometimes', 'nullable', 'array'],
            'industries.*' => ['string', 'max:255'],
            'experiences' => ['sometimes', 'nullable', 'array'],
            'experiences.*.company' => ['required', 'string', 'max:255'],
            'experiences.*.title' => ['required', 'string', 'max:255'],
            'experiences.*.start_date' => ['nullable', 'date'],
            'experiences.*.end_date' => ['nullable', 'date'],
            'experiences.*.description' => ['required', 'string'],
            'experiences.*.achievements' => ['nullable', 'array'],
            'experiences.*.achievements.*' => ['string', 'max:500'],
            'experiences.*.skills' => ['nullable', 'array'],
            'experiences.*.skills.*' => ['string', 'max:255'],
            'projects' => ['sometimes', 'nullable', 'array'],
            'projects.*.name' => ['required', 'string', 'max:255'],
            'projects.*.description' => ['required', 'string'],
            'projects.*.skills' => ['nullable', 'array'],
            'projects.*.skills.*' => ['string', 'max:255'],
            'projects.*.url' => ['nullable', 'url', 'max:2048'],
            'education' => ['sometimes', 'nullable', 'array'],
            'certifications' => ['sometimes', 'nullable', 'array'],
            'languages' => ['sometimes', 'nullable', 'array'],
            'languages.*' => ['string', 'max:100'],
            'preferred_workplace_type' => ['sometimes', 'nullable', Rule::in(['remote', 'hybrid', 'onsite', 'any'])],
            'preferred_locations' => ['sometimes', 'nullable', 'array'],
            'preferred_locations.*' => ['string', 'max:255'],
            'salary_expectation' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'salary_currency' => ['sometimes', 'nullable', 'string', 'max:12'],
            'raw_cv_text' => ['sometimes', 'nullable', 'string'],
            'parsed_cv_data' => ['sometimes', 'nullable', 'array'],
            'source' => ['sometimes', 'nullable', Rule::in(['manual', 'cv_upload', 'ai_generated'])],
            'is_primary' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'github_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'portfolio_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
