<?php

namespace App\Http\Requests\Collections;

use App\Enums\CollectionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateCollectionRequest extends FormRequest
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
            'type' => ['sometimes', Rule::enum(CollectionType::class)],
            'rules' => ['sometimes', 'nullable', 'array'],
            'imageUrl' => ['sometimes', 'nullable', 'string'],
            'productIds' => ['sometimes', 'array'],
            'productIds.*' => ['string'],
        ];
    }
}
