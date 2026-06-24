<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'string'],
            'bodyText' => ['sometimes', 'string'],
            'bodyHtml' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
