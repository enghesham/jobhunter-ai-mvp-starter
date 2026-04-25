<?php

namespace App\Modules\Resume\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profile_id' => ['sometimes', 'integer', 'exists:candidate_profiles,id'],
            'version_name' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
