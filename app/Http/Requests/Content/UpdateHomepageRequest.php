<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHomepageRequest extends FormRequest
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
            'promoBar' => ['sometimes', 'string'],
            'phone' => ['sometimes', 'string'],
            'hero' => ['sometimes', 'array'],
            'collections' => ['sometimes', 'array'],
            'promoBanners' => ['sometimes', 'array'],
            'saleCountdown' => ['sometimes', 'array'],
            'serviceHighlights' => ['sometimes', 'array'],
            'shopBySection' => ['sometimes', 'array'],
            'shopCategories' => ['sometimes', 'array'],
            'categoryIconScroll' => ['sometimes', 'array'],
            'sectionVisibility' => ['sometimes', 'array'],
            'productRows' => ['sometimes', 'array'],
        ];
    }
}
