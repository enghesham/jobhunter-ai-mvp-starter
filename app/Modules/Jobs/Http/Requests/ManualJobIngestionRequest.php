<?php

namespace App\Modules\Jobs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManualJobIngestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'jobs' => ['required', 'array', 'min:1'],
            'jobs.*.external_id' => ['nullable', 'string', 'max:255'],
            'jobs.*.title' => ['required', 'string', 'max:255'],
            'jobs.*.company' => ['nullable', 'string', 'max:255'],
            'jobs.*.company_name' => ['nullable', 'string', 'max:255'],
            'jobs.*.location' => ['nullable', 'string', 'max:255'],
            'jobs.*.is_remote' => ['sometimes', 'boolean'],
            'jobs.*.remote_type' => ['nullable', 'string', 'max:100'],
            'jobs.*.employment_type' => ['nullable', 'string', 'max:100'],
            'jobs.*.url' => ['required', 'url', 'max:2048'],
            'jobs.*.description' => ['nullable', 'string'],
            'jobs.*.status' => ['sometimes', 'string', 'in:new,ingested,analyzed,matched,archived'],
            'jobs.*.posted_at' => ['nullable', 'date'],
            'jobs.*.raw_payload' => ['nullable', 'array'],
        ];
    }
}
