<?php

namespace App\Modules\Copilot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddOpportunityProfileSkillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'skills' => ['required', 'array', 'min:1', 'max:20'],
            'skills.*' => ['required', 'string', 'max:255'],
        ];
    }
}
