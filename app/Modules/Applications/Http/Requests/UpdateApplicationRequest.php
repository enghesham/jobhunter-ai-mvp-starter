<?php

namespace App\Modules\Applications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:draft,ready_to_apply,applied,rejected,interview,offer'],
            'applied_at' => ['nullable', 'date'],
            'follow_up_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'company_response' => ['nullable', 'string'],
            'interview_date' => ['nullable', 'date'],
        ];
    }
}
