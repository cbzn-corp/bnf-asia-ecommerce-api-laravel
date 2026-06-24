<?php

namespace App\Http\Requests\Bundles;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBundleRequest extends FormRequest
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
            'description' => ['sometimes', 'nullable', 'string'],
            'discountPercent' => ['sometimes', 'numeric', 'min:0'],
            'imageUrl' => ['sometimes', 'nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.productId' => ['required_with:items', 'string'],
            'items.*.variantId' => ['sometimes', 'nullable', 'string'],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
