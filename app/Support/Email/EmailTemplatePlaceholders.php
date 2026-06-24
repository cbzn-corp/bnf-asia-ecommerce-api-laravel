<?php

declare(strict_types=1);

namespace App\Support\Email;

final class EmailTemplatePlaceholders
{
    /**
     * @return array{
     *     shared: list<array{token: string, label: string, description: string}>,
     *     templates: array<string, array{text: list<array{token: string, label: string, description: string}>, html: list<array{token: string, label: string, description: string}>}>
     * }
     */
    public static function catalog(): array
    {
        $shared = [
            ['token' => '{{storeName}}', 'label' => 'Store name', 'description' => 'Platform store name'],
            ['token' => '{{storeEmail}}', 'label' => 'Store email', 'description' => 'Support / contact email'],
            ['token' => '{{storePhone}}', 'label' => 'Store phone', 'description' => 'Store phone number'],
            ['token' => '{{storeAddress}}', 'label' => 'Store address', 'description' => 'Store address line'],
            ['token' => '{{storefrontUrl}}', 'label' => 'Storefront URL', 'description' => 'Public shop homepage'],
            ['token' => '{{customerEmail}}', 'label' => 'Customer email', 'description' => 'Recipient email address'],
            ['token' => '{{year}}', 'label' => 'Current year', 'description' => 'Four-digit year'],
        ];

        $orderText = [
            ['token' => '{{orderNumber}}', 'label' => 'Order number', 'description' => 'Order reference'],
            ['token' => '{{total}}', 'label' => 'Order total', 'description' => 'Formatted order total'],
            ['token' => '{{paymentMethod}}', 'label' => 'Payment method code', 'description' => 'Raw payment method value'],
            ['token' => '{{paymentMethodLabel}}', 'label' => 'Payment method label', 'description' => 'Human-readable payment method'],
            ['token' => '{{accountUrl}}', 'label' => 'Order URL', 'description' => 'Link to order in customer account'],
            ['token' => '{{customerName}}', 'label' => 'Customer name', 'description' => 'Customer display name when available'],
            ['token' => '{{orderDate}}', 'label' => 'Order date', 'description' => 'Formatted order date'],
        ];

        $orderHtml = [
            ['token' => '{{detailTable}}', 'label' => 'Order details table', 'description' => 'HTML summary table (HTML only)'],
            ['token' => '{{viewOrderButton}}', 'label' => 'View order button', 'description' => 'CTA button linking to the order (HTML only)'],
        ];

        $shippedText = [
            ['token' => '{{carrier}}', 'label' => 'Carrier', 'description' => 'Shipping carrier name'],
            ['token' => '{{trackingNumber}}', 'label' => 'Tracking number', 'description' => 'Shipment tracking number'],
        ];

        $statusText = [
            ['token' => '{{shippingStatus}}', 'label' => 'Shipping status', 'description' => 'Current shipping status'],
            ['token' => '{{paymentStatus}}', 'label' => 'Payment status', 'description' => 'Current payment status'],
        ];

        $cartText = [
            ['token' => '{{recoveryUrl}}', 'label' => 'Cart recovery URL', 'description' => 'Link to restore abandoned cart'],
            ['token' => '{{email}}', 'label' => 'Customer email', 'description' => 'Cart owner email'],
            ['token' => '{{discountCode}}', 'label' => 'Discount code', 'description' => 'Optional promo code'],
        ];

        $cartHtml = [
            ['token' => '{{completeOrderButton}}', 'label' => 'Complete order button', 'description' => 'CTA button for cart recovery (HTML only)'],
        ];

        $resetText = [
            ['token' => '{{resetLink}}', 'label' => 'Reset link', 'description' => 'Password reset URL'],
        ];

        $resetHtml = [
            ['token' => '{{resetPasswordButton}}', 'label' => 'Reset password button', 'description' => 'CTA button for password reset (HTML only)'],
        ];

        return [
            'shared' => $shared,
            'templates' => [
                'order_confirmation' => [
                    'text' => [...$orderText],
                    'html' => [...$orderHtml],
                ],
                'payment_reminder' => [
                    'text' => [...$orderText],
                    'html' => [...$orderHtml],
                ],
                'order_shipped' => [
                    'text' => [...$orderText, ...$shippedText],
                    'html' => [...$orderHtml],
                ],
                'order_status' => [
                    'text' => [...$orderText, ...$statusText],
                    'html' => [...$orderHtml],
                ],
                'abandoned_cart' => [
                    'text' => [...$cartText],
                    'html' => [...$cartHtml],
                ],
                'password_reset' => [
                    'text' => [...$resetText],
                    'html' => [...$resetHtml],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $vars
     */
    public static function expandHtmlFragments(string $key, string $html, array $vars): string
    {
        return EmailTemplateDefaults::expandFragments($key, $html, $vars);
    }

    /**
     * @param  array<string, string>  $vars
     */
    public static function expandTextFragments(string $key, string $text, array $vars): string
    {
        $detailLines = match ($key) {
            'order_confirmation' => self::detailLines([
                ['Order', $vars['orderNumber'] ?? '{{orderNumber}}'],
                ['Total', $vars['total'] ?? '{{total}}'],
                ['Payment', $vars['paymentMethodLabel'] ?? $vars['paymentMethod'] ?? '{{paymentMethod}}'],
            ]),
            'order_shipped' => self::detailLines([
                ['Order', $vars['orderNumber'] ?? '{{orderNumber}}'],
                ['Carrier', $vars['carrier'] ?? '{{carrier}}'],
                ['Tracking', $vars['trackingNumber'] ?? '{{trackingNumber}}'],
            ]),
            'order_status' => self::detailLines([
                ['Order', $vars['orderNumber'] ?? '{{orderNumber}}'],
                ['Shipping', $vars['shippingStatus'] ?? '{{shippingStatus}}'],
                ['Payment', $vars['paymentStatus'] ?? '{{paymentStatus}}'],
            ]),
            'payment_reminder' => self::detailLines([
                ['Order', $vars['orderNumber'] ?? '{{orderNumber}}'],
                ['Total due', $vars['total'] ?? '{{total}}'],
                ['Payment', $vars['paymentMethodLabel'] ?? $vars['paymentMethod'] ?? '{{paymentMethod}}'],
            ]),
            default => '',
        };

        $replacements = [
            '{{detailTable}}' => $detailLines,
            '{{viewOrderButton}}' => self::textLink('View order', $vars['accountUrl'] ?? '{{accountUrl}}'),
            '{{completeOrderButton}}' => self::textLink('Complete your order', $vars['recoveryUrl'] ?? '{{recoveryUrl}}'),
            '{{resetPasswordButton}}' => self::textLink('Reset password', $vars['resetLink'] ?? '{{resetLink}}'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $rows
     */
    private static function detailLines(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        return implode("\n", array_map(
            static fn (array $row) => "{$row[0]}: {$row[1]}",
            $rows,
        ));
    }

    private static function textLink(string $label, string $url): string
    {
        return "{$label}: {$url}";
    }
}
