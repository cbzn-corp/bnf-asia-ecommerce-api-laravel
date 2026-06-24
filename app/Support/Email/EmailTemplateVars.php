<?php

declare(strict_types=1);

namespace App\Support\Email;

use App\Models\PlatformSetting;
use App\Support\Config\AppUrls;

final class EmailTemplateVars
{
    /**
     * @param  array<string, string>  $vars
     * @return array<string, string>
     */
    public static function enrich(string $templateKey, array $vars, ?string $recipientEmail = null): array
    {
        $settings = PlatformSetting::query()->find('default');
        $storefront = rtrim(AppUrls::getStorefrontUrl(), '/');

        $enriched = array_merge([
            'storeName' => trim((string) ($settings->storeName ?? '')) ?: 'BNF Asia',
            'storeEmail' => trim((string) ($settings->storeEmail ?? '')),
            'storePhone' => trim((string) ($settings->storePhone ?? '')),
            'storeAddress' => trim((string) ($settings->storeAddress ?? '')),
            'storefrontUrl' => $storefront,
            'year' => (string) date('Y'),
        ], $vars);

        if ($recipientEmail !== null && $recipientEmail !== '') {
            $enriched['customerEmail'] = $recipientEmail;
        } elseif (! empty($enriched['email'])) {
            $enriched['customerEmail'] = $enriched['email'];
        }

        if (! empty($enriched['paymentMethod'])) {
            $enriched['paymentMethodLabel'] = self::formatPaymentMethod($enriched['paymentMethod']);
        }

        if (! empty($enriched['orderNumber']) && empty($enriched['accountUrl'])) {
            $enriched['accountUrl'] = "{$storefront}/account/orders/{$enriched['orderNumber']}";
        }

        if (! empty($enriched['shippingStatus'])) {
            $enriched['shippingStatus'] = self::formatStatus($enriched['shippingStatus']);
        }

        if (! empty($enriched['paymentStatus'])) {
            $enriched['paymentStatus'] = self::formatStatus($enriched['paymentStatus']);
        }

        return $enriched;
    }

    /**
     * @return array<string, string>
     */
    public static function sampleVars(string $templateKey, string $recipientEmail): array
    {
        $storefront = rtrim(AppUrls::getStorefrontUrl(), '/');

        $samples = [
            'order_confirmation' => [
                'orderNumber' => 'ORD-2026-001234',
                'total' => '₱24,500.00',
                'paymentMethod' => 'GCASH',
                'customerName' => 'Maria Santos',
                'orderDate' => 'June 23, 2026',
                'accountUrl' => "{$storefront}/account/orders/ORD-2026-001234",
            ],
            'abandoned_cart' => [
                'recoveryUrl' => "{$storefront}/cart?recover=test-token",
                'email' => $recipientEmail,
                'discountCode' => 'WELCOME10',
            ],
            'order_shipped' => [
                'orderNumber' => 'ORD-2026-001234',
                'carrier' => 'LBC',
                'trackingNumber' => 'LBC123456789PH',
                'customerName' => 'Maria Santos',
                'orderDate' => 'June 23, 2026',
            ],
            'order_status' => [
                'orderNumber' => 'ORD-2026-001234',
                'shippingStatus' => 'SHIPPED',
                'paymentStatus' => 'PAID',
                'customerName' => 'Maria Santos',
                'orderDate' => 'June 23, 2026',
            ],
            'payment_reminder' => [
                'orderNumber' => 'ORD-2026-001234',
                'total' => '₱24,500.00',
                'paymentMethod' => 'GCASH',
                'customerName' => 'Maria Santos',
                'orderDate' => 'June 23, 2026',
                'accountUrl' => "{$storefront}/account/orders/ORD-2026-001234",
            ],
            'password_reset' => [
                'resetLink' => "{$storefront}/reset-password?token=sample-token",
            ],
        ];

        return self::enrich($templateKey, $samples[$templateKey] ?? ['email' => $recipientEmail], $recipientEmail);
    }

    private static function formatPaymentMethod(string $value): string
    {
        $normalized = strtoupper(trim($value));

        return match ($normalized) {
            'GCASH' => 'GCash',
            'MAYA' => 'Maya',
            'COD' => 'Cash on delivery',
            'BNPL' => 'Buy now, pay later',
            'BANK_TRANSFER' => 'Bank transfer',
            'CARD' => 'Card',
            'STRIPE' => 'Card (Stripe)',
            'SUPPORT_ASSISTED' => 'Support assisted',
            default => ucwords(strtolower(str_replace('_', ' ', $normalized))),
        };
    }

    private static function formatStatus(string $value): string
    {
        return ucwords(strtolower(str_replace('_', ' ', trim($value))));
    }
}
