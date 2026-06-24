<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class BatchCreateVariantsRequest extends FormRequest
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
            'items.*.productSku' => ['required', 'string'],
            'items.*.name' => ['required', 'string'],
            'items.*.sku' => ['sometimes', 'nullable', 'string'],
            'items.*.priceInPHP' => ['required', 'numeric', 'min:0'],
            'items.*.compareAtPrice' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'items.*.stockQuantity' => ['required', 'integer', 'min:0'],
            'items.*.isActive' => ['sometimes', 'boolean'],
            'items.*.optionColor' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
