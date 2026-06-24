<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePickupLocationRequest extends FormRequest
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
            'name' => ['sometimes', 'string'],
            'address' => ['sometimes', 'string'],
            'city' => ['sometimes', 'string'],
            'province' => ['sometimes', 'string'],
            'phone' => ['sometimes', 'nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer'],
        ];
    }
}
