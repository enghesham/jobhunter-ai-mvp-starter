<?php

namespace App\Modules\Copilot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuggestJobPathsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'career_profile_id' => ['sometimes', 'nullable', 'integer', 'exists:candidate_profiles,id'],
        ];
    }
}
