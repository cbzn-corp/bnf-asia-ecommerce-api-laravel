<?php

namespace App\Http\Requests\Bundles;

use Illuminate\Foundation\Http\FormRequest;

class CreateBundleRequest extends FormRequest
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
            'name' => ['required', 'string'],
            'slug' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'discountPercent' => ['sometimes', 'numeric', 'min:0'],
            'imageUrl' => ['sometimes', 'nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'string'],
            'items.*.variantId' => ['sometimes', 'nullable', 'string'],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
