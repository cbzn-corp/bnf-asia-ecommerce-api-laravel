<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'sku' => ['sometimes', 'nullable', 'string'],
            'shortDescription' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priceInPHP' => ['sometimes', 'numeric', 'min:0'],
            'compareAtPrice' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'weightInGrams' => ['sometimes', 'numeric', 'min:0'],
            'stockQuantity' => ['sometimes', 'integer', 'min:0'],
            'images' => ['sometimes', 'array'],
            'images.*' => ['string'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['string'],
            'isFeatured' => ['sometimes', 'boolean'],
            'isNew' => ['sometimes', 'boolean'],
            'isBestSeller' => ['sometimes', 'boolean'],
            'isOnSale' => ['sometimes', 'boolean'],
            'isPublished' => ['sometimes', 'boolean'],
            'hideWhenOutOfStock' => ['sometimes', 'boolean'],
            'installationAvailable' => ['sometimes', 'boolean'],
            'installationFeeInPHP' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'rating' => ['sometimes', 'numeric'],
            'reviewCount' => ['sometimes', 'integer'],
            'categoryId' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
