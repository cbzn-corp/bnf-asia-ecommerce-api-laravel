<?php

namespace App\Http\Requests\Collections;

use App\Enums\CollectionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollectionRequest extends FormRequest
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
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', Rule::enum(CollectionType::class)],
            'rules' => ['sometimes', 'nullable', 'array'],
            'imageUrl' => ['sometimes', 'nullable', 'string'],
            'isActive' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer'],
            'productIds' => ['sometimes', 'array'],
            'productIds.*' => ['string'],
        ];
    }
}
