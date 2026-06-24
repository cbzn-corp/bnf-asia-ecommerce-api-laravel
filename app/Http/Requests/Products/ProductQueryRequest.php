<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $booleanFields = ['featured', 'onSale', 'isNew', 'bestSeller'];
        $merged = [];

        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $value = $this->input($field);
                $merged[$field] = $value === 'true' || $value === true || $value === '1' || $value === 1;
            }
        }

        if ($merged) {
            $this->merge($merged);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'nullable', 'string'],
            'featured' => ['sometimes', 'boolean'],
            'onSale' => ['sometimes', 'boolean'],
            'isNew' => ['sometimes', 'boolean'],
            'bestSeller' => ['sometimes', 'boolean'],
            'deals' => ['sometimes', 'nullable', 'string'],
            'minPrice' => ['sometimes', 'numeric', 'min:0'],
            'maxPrice' => ['sometimes', 'numeric', 'min:0'],
            'sort' => ['sometimes', 'nullable', Rule::in(['newest', 'price-asc', 'price-desc', 'popular'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
