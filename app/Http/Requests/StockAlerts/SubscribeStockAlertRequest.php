<?php

namespace App\Http\Requests\StockAlerts;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeStockAlertRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'productId' => ['required', 'string'],
            'variantId' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
