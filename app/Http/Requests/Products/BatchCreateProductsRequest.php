<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class BatchCreateProductsRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.name' => ['required', 'string'],
            'items.*.slug' => ['sometimes', 'nullable', 'string'],
            'items.*.sku' => ['sometimes', 'nullable', 'string'],
            'items.*.shortDescription' => ['sometimes', 'nullable', 'string'],
            'items.*.description' => ['sometimes', 'nullable', 'string'],
            'items.*.priceInPHP' => ['required', 'numeric', 'min:0'],
            'items.*.compareAtPrice' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items.*.weightInGrams' => ['required', 'numeric', 'min:0'],
            'items.*.stockQuantity' => ['required', 'integer', 'min:0'],
            'items.*.isFeatured' => ['sometimes', 'boolean'],
            'items.*.isNew' => ['sometimes', 'boolean'],
            'items.*.isBestSeller' => ['sometimes', 'boolean'],
            'items.*.isOnSale' => ['sometimes', 'boolean'],
            'items.*.isPublished' => ['sometimes', 'boolean'],
            'items.*.installationFeeInPHP' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items.*.categoryName' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
