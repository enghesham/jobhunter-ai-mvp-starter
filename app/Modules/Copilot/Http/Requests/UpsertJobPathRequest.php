<?php

namespace App\Modules\Copilot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertJobPathRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $aliases = [];

        if (! $this->has('name') && $this->has('title')) {
            $aliases['name'] = $this->input('title');
        }

        if (! $this->has('description') && $this->has('goal')) {
            $aliases['description'] = $this->input('goal');
        }

        if (! $this->has('target_domains') && $this->has('target_fields')) {
            $aliases['target_domains'] = $this->input('target_fields');
        }

        if (! $this->has('preferred_job_types') && $this->has('employment_types')) {
            $aliases['preferred_job_types'] = $this->input('employment_types');
        }

        if (! $this->has('remote_preference') && $this->has('work_modes') && is_array($this->input('work_modes'))) {
            $aliases['remote_preference'] = $this->input('work_modes.0');
        }

        if (! $this->has('include_keywords') && $this->has('must_have_keywords')) {
            $aliases['include_keywords'] = $this->input('must_have_keywords');
        }

        if (! $this->has('exclude_keywords') && $this->has('avoid_keywords')) {
            $aliases['exclude_keywords'] = $this->input('avoid_keywords');
        }

        if (! $this->has('required_skills') && $this->has('must_have_keywords')) {
            $aliases['required_skills'] = $this->input('must_have_keywords');
        }

        if (! $this->has('optional_skills') && $this->has('nice_to_have_keywords')) {
            $aliases['optional_skills'] = $this->input('nice_to_have_keywords');
        }

        if (! $this->has('min_relevance_score') && $this->has('min_fit_score')) {
            $aliases['min_relevance_score'] = $this->input('min_fit_score');
        }

        if (! $this->has('min_match_score') && $this->has('min_apply_score')) {
            $aliases['min_match_score'] = $this->input('min_apply_score');
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
            'career_profile_id' => ['sometimes', 'nullable', 'integer', 'exists:candidate_profiles,id'],
            'name' => [$requiredForCreate, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'target_roles' => ['sometimes', 'nullable', 'array'],
            'target_roles.*' => ['string', 'max:255'],
            'target_domains' => ['sometimes', 'nullable', 'array'],
            'target_domains.*' => ['string', 'max:255'],
            'include_keywords' => ['sometimes', 'nullable', 'array'],
            'include_keywords.*' => ['string', 'max:255'],
            'exclude_keywords' => ['sometimes', 'nullable', 'array'],
            'exclude_keywords.*' => ['string', 'max:255'],
            'required_skills' => ['sometimes', 'nullable', 'array'],
            'required_skills.*' => ['string', 'max:255'],
            'optional_skills' => ['sometimes', 'nullable', 'array'],
            'optional_skills.*' => ['string', 'max:255'],
            'seniority_levels' => ['sometimes', 'nullable', 'array'],
            'seniority_levels.*' => ['string', 'max:100'],
            'preferred_locations' => ['sometimes', 'nullable', 'array'],
            'preferred_locations.*' => ['string', 'max:255'],
            'preferred_countries' => ['sometimes', 'nullable', 'array'],
            'preferred_countries.*' => ['string', 'max:100'],
            'preferred_job_types' => ['sometimes', 'nullable', 'array'],
            'preferred_job_types.*' => [Rule::in(['full-time', 'part-time', 'contract', 'freelance', 'internship'])],
            'remote_preference' => ['sometimes', 'nullable', Rule::in(['remote', 'hybrid', 'onsite', 'any'])],
            'min_relevance_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'min_match_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'salary_min' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999999999'],
            'salary_currency' => ['sometimes', 'nullable', 'string', 'max:12'],
            'is_active' => ['sometimes', 'boolean'],
            'auto_collect_enabled' => ['sometimes', 'boolean'],
            'notifications_enabled' => ['sometimes', 'boolean'],
            'scan_interval_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:168'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
