<?php

namespace App\Modules\Copilot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOpportunityPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_min_relevance_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'default_min_match_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'quick_recommended_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'store_below_threshold' => ['sometimes', 'nullable', 'boolean'],
            'show_below_threshold' => ['sometimes', 'nullable', 'boolean'],
            'apply_to_existing_job_paths' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
