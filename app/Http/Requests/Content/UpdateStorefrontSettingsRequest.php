<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStorefrontSettingsRequest extends FormRequest
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
            'siteName' => ['sometimes', 'string'],
            'logoUrl' => ['sometimes', 'nullable', 'string'],
            'faviconUrl' => ['sometimes', 'nullable', 'string'],
            'tagline' => ['sometimes', 'string'],
            'copyright' => ['sometimes', 'string'],
            'seo' => ['sometimes', 'array'],
            'analytics' => ['sometimes', 'array'],
            'socialLinks' => ['sometimes', 'array'],
            'headerLinks' => ['sometimes', 'array'],
            'navLinks' => ['sometimes', 'array'],
            'footerShopLinks' => ['sometimes', 'array'],
            'footerHelpLinks' => ['sometimes', 'array'],
            'productTrustBullets' => ['sometimes', 'array', 'max:100'],
            'pdpFreeShippingNote' => ['sometimes', 'nullable', 'string'],
            'promoBar' => ['sometimes', 'string'],
            'phone' => ['sometimes', 'string'],
            'listingPages' => ['sometimes', 'array'],
            'pageCopy' => ['sometimes', 'array'],
            'chatWidget' => ['sometimes', 'array'],
            'chatWidget.enabled' => ['sometimes', 'boolean'],
            'chatWidget.greeting' => ['sometimes', 'string', 'max:200'],
            'chatWidget.messengers' => ['sometimes', 'array', 'max:20'],
            'chatWidget.messengers.*.type' => ['sometimes', 'string', 'in:whatsapp,messenger,viber,telegram,line,email,phone,custom'],
            'chatWidget.messengers.*.label' => ['sometimes', 'string', 'max:80'],
            'chatWidget.messengers.*.url' => ['sometimes', 'string', 'max:500'],
        ];
    }
}
