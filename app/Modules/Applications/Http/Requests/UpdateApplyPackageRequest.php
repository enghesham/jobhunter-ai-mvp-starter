<?php

namespace App\Modules\Applications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplyPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cover_letter' => ['sometimes', 'nullable', 'string'],
            'application_answers' => ['sometimes', 'nullable', 'array'],
            'application_answers.*.key' => ['required_with:application_answers', 'string', 'max:100'],
            'application_answers.*.question' => ['required_with:application_answers', 'string', 'max:500'],
            'application_answers.*.answer' => ['required_with:application_answers', 'string'],
            'salary_answer' => ['sometimes', 'nullable', 'string'],
            'notice_period_answer' => ['sometimes', 'nullable', 'string'],
            'interest_answer' => ['sometimes', 'nullable', 'string'],
            'strengths' => ['sometimes', 'nullable', 'array'],
            'strengths.*' => ['string', 'max:255'],
            'gaps' => ['sometimes', 'nullable', 'array'],
            'gaps.*' => ['string', 'max:255'],
            'interview_questions' => ['sometimes', 'nullable', 'array'],
            'interview_questions.*' => ['string', 'max:500'],
            'follow_up_email' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(['draft', 'ready', 'used', 'archived'])],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
