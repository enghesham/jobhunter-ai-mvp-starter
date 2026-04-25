<?php

namespace App\Modules\Jobs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:'.implode(',', config('jobhunter.allowed_sources', ['custom', 'greenhouse', 'lever']))],
            'url' => ['required', 'url', 'max:2048'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'config' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'url' => $this->input('url', $this->input('base_url')),
            'config' => $this->input('config', $this->input('meta')),
        ]);
    }

    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated();
        $validated['base_url'] = $validated['url'];
        $validated['meta'] = $validated['config'] ?? null;
        unset($validated['url'], $validated['config']);

        if ($key !== null) {
            return data_get($validated, $key, $default);
        }

        return $validated;
    }
}
