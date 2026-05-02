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
        ];
    }
}
