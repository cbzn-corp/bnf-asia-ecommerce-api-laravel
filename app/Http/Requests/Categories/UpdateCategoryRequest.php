<?php

namespace App\Http\Requests\Categories;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
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
            'slug' => ['sometimes', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'imageUrl' => ['sometimes', 'nullable', 'string'],
            'parentId' => ['sometimes', 'nullable', 'string'],
            'sortOrder' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
