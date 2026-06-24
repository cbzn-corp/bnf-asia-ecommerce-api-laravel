<?php

namespace App\Http\Requests\AbandonedCarts;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAbandonedCartRequest extends FormRequest
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
            'email' => ['sometimes', 'nullable', 'email'],
            'userId' => ['sometimes', 'nullable', 'string'],
            'items' => ['required', 'array'],
        ];
    }
}
