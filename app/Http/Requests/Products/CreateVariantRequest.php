<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class CreateVariantRequest extends FormRequest
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
            'sku' => ['sometimes', 'nullable', 'string'],
            'options' => ['sometimes', 'array'],
            'priceInPHP' => ['required', 'numeric', 'min:0'],
            'compareAtPrice' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'stockQuantity' => ['required', 'integer', 'min:0'],
            'weightInGrams' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['string'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
