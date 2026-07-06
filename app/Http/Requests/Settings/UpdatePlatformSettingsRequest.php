<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
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
            'phpPerUsd' => ['sometimes', 'numeric', 'min:0'],
            'freeShippingEnabled' => ['sometimes', 'boolean'],
            'freeShippingMinPHP' => ['sometimes', 'numeric', 'min:0'],
            'vatRatePercent' => ['sometimes', 'numeric', 'min:0'],
            'vatEnabled' => ['sometimes', 'boolean'],
            'paymongoEnabled' => ['sometimes', 'boolean'],
            'paymongoPublicKey' => ['sometimes', 'nullable', 'string'],
            'stripeEnabled' => ['sometimes', 'boolean'],
            'stripePublishableKey' => ['sometimes', 'nullable', 'string'],
            'bnplEnabled' => ['sometimes', 'boolean'],
            'abandonedCartEnabled' => ['sometimes', 'boolean'],
            'abandonedCartHours' => ['sometimes', 'integer', 'min:1'],
            'lowStockThreshold' => ['sometimes', 'integer', 'min:0'],
            'supportAssistedCheckoutEnabled' => ['sometimes', 'boolean'],
            'customerChatEnabled' => ['sometimes', 'boolean'],
            'quoteStaleAlertDays' => ['sometimes', 'integer', 'min:1'],
            'storeName' => ['sometimes', 'nullable', 'string'],
            'storeEmail' => ['sometimes', 'nullable', 'string'],
            'storePhone' => ['sometimes', 'nullable', 'string'],
            'storeAddress' => ['sometimes', 'nullable', 'string'],
            'checkoutOrderNotesEnabled' => ['sometimes', 'boolean'],
            'guestCheckoutEnabled' => ['sometimes', 'boolean'],
            'compareEnabled' => ['sometimes', 'boolean'],
            'codEnabled' => ['sometimes', 'boolean'],
            'bankTransferEnabled' => ['sometimes', 'boolean'],
            'paymongoGcashEnabled' => ['sometimes', 'boolean'],
            'paymongoMayaEnabled' => ['sometimes', 'boolean'],
            'pricesIncludeVat' => ['sometimes', 'boolean'],
            'deliveryFeeAtCheckoutEnabled' => ['sometimes', 'boolean'],
            'abandonedCartDiscountCode' => ['sometimes', 'nullable', 'string'],
            'maintenanceModeEnabled' => ['sometimes', 'boolean'],
            'maintenanceMessage' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'maintenanceWhitelistIps' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'maintenanceBypassSecret' => ['sometimes', 'nullable', 'string', 'max:128'],
        ];
    }
}
