<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class CreateShippingRateRequest extends FormRequest
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
            'label' => ['required', 'string'],
            'region' => ['required', 'string'],
            'feeInPHP' => ['required', 'numeric', 'min:0'],
            'zone' => ['sometimes', 'nullable', 'string'],
            'estimatedDays' => ['sometimes', 'nullable', 'string'],
            'minWeightGrams' => ['sometimes', 'nullable', 'numeric'],
            'maxWeightGrams' => ['sometimes', 'nullable', 'numeric'],
            'isActive' => ['sometimes', 'boolean'],
            'sortOrder' => ['sometimes', 'integer'],
        ];
    }
}
