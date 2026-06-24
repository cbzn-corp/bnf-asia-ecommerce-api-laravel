<?php

namespace App\Http\Requests\Addresses;

use Illuminate\Foundation\Http\FormRequest;

class CreateAddressRequest extends FormRequest
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
            'label' => ['sometimes', 'nullable', 'string'],
            'country' => ['required', 'string'],
            'street1' => ['required', 'string'],
            'street2' => ['sometimes', 'nullable', 'string'],
            'region' => ['sometimes', 'nullable', 'string'],
            'province' => ['sometimes', 'nullable', 'string'],
            'city' => ['sometimes', 'nullable', 'string'],
            'barangay' => ['sometimes', 'nullable', 'string'],
            'postalCode' => ['sometimes', 'nullable', 'string'],
            'isDefault' => ['sometimes', 'boolean'],
        ];
    }
}
