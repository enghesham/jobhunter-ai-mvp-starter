<?php

namespace App\Modules\Jobs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MatchJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'profile_id' => ['sometimes', 'integer', 'exists:candidate_profiles,id'],
            'sync' => ['sometimes', 'boolean'],
        ];
    }
}
