<?php

namespace App\Modules\Jobs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanJobSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sync' => ['sometimes', 'boolean'],
        ];
    }
}
