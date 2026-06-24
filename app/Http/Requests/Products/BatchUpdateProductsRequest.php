<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class BatchUpdateProductsRequest extends FormRequest
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
            'updates' => ['required', 'array', 'min:1', 'max:500'],
            'updates.*.id' => ['required', 'string'],
            'updates.*.name' => ['sometimes', 'nullable', 'string'],
            'updates.*.shortDescription' => ['sometimes', 'nullable', 'string'],
            'updates.*.sku' => ['sometimes', 'nullable', 'string'],
            'updates.*.priceInPHP' => ['sometimes', 'numeric', 'min:0'],
            'updates.*.compareAtPrice' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'updates.*.stockQuantity' => ['sometimes', 'integer', 'min:0'],
            'updates.*.weightInGrams' => ['sometimes', 'numeric', 'min:0'],
            'updates.*.isFeatured' => ['sometimes', 'boolean'],
            'updates.*.isNew' => ['sometimes', 'boolean'],
            'updates.*.isBestSeller' => ['sometimes', 'boolean'],
            'updates.*.isOnSale' => ['sometimes', 'boolean'],
            'updates.*.rating' => ['sometimes', 'numeric'],
            'updates.*.reviewCount' => ['sometimes', 'integer'],
            'updates.*.categoryId' => ['sometimes', 'nullable', 'string'],
            'updates.*.categoryName' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
