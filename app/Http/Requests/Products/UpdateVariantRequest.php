<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVariantRequest extends FormRequest
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
            'sku' => ['sometimes', 'nullable', 'string'],
            'options' => ['sometimes', 'array'],
            'priceInPHP' => ['sometimes', 'numeric', 'min:0'],
            'compareAtPrice' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stockQuantity' => ['sometimes', 'integer', 'min:0'],
            'weightInGrams' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['string'],
            'isActive' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer'],
        ];
    }
}
