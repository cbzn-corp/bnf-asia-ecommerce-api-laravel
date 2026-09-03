<?php

namespace App\Http\Requests\CartEvents;

use Illuminate\Foundation\Http\FormRequest;

class RecordCartAddEventRequest extends FormRequest
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
            'productId' => ['required', 'string'],
            'variantId' => ['sometimes', 'nullable', 'string'],
            'productName' => ['required', 'string', 'max:500'],
            'variantName' => ['sometimes', 'nullable', 'string', 'max:500'],
            'slug' => ['required', 'string', 'max:500'],
            'qty' => ['required', 'integer', 'min:1'],
            'priceInPHP' => ['required', 'numeric', 'min:0'],
        ];
    }
}
