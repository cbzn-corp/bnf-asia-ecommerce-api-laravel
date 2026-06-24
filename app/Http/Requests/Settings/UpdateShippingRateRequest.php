<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShippingRateRequest extends FormRequest
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
            'label' => ['sometimes', 'string'],
            'region' => ['sometimes', 'string'],
            'feeInPHP' => ['sometimes', 'numeric', 'min:0'],
            'zone' => ['sometimes', 'nullable', 'string'],
            'estimatedDays' => ['sometimes', 'nullable', 'string'],
            'minWeightGrams' => ['sometimes', 'nullable', 'numeric'],
            'maxWeightGrams' => ['sometimes', 'nullable', 'numeric'],
            'isActive' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer'],
        ];
    }
}
