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

    public function rules(): array
    {
        $isCreate = $this->isMethod('post');
        $requiredForCreate = $isCreate ? 'required' : 'sometimes';

        return [
            'career_profile_id' => ['sometimes', 'nullable', 'integer', 'exists:candidate_profiles,id'],
            'title' => [$requiredForCreate, 'string', 'max:255'],
            'goal' => ['sometimes', 'nullable', 'string'],
            'target_roles' => ['sometimes', 'nullable', 'array'],
            'target_roles.*' => ['string', 'max:255'],
            'target_fields' => ['sometimes', 'nullable', 'array'],
            'target_fields.*' => ['string', 'max:255'],
            'preferred_locations' => ['sometimes', 'nullable', 'array'],
            'preferred_locations.*' => ['string', 'max:255'],
            'work_modes' => ['sometimes', 'nullable', 'array'],
            'work_modes.*' => [Rule::in(['remote', 'hybrid', 'onsite', 'any'])],
            'employment_types' => ['sometimes', 'nullable', 'array'],
            'employment_types.*' => [Rule::in(['full-time', 'part-time', 'contract', 'freelance', 'internship'])],
            'must_have_keywords' => ['sometimes', 'nullable', 'array'],
            'must_have_keywords.*' => ['string', 'max:255'],
            'nice_to_have_keywords' => ['sometimes', 'nullable', 'array'],
            'nice_to_have_keywords.*' => ['string', 'max:255'],
            'avoid_keywords' => ['sometimes', 'nullable', 'array'],
            'avoid_keywords.*' => ['string', 'max:255'],
            'min_fit_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'min_apply_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'auto_collect_enabled' => ['sometimes', 'boolean'],
            'scan_interval_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:168'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
