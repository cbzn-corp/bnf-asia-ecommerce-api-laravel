<?php

declare(strict_types=1);

namespace App\Support\Email;

final class EmailTemplateDefaults
{
    /**
     * @return array<string, string>
     */
    public static function allHtml(): array
    {
        return [
            'order_confirmation' => self::orderConfirmationHtml(),
            'abandoned_cart' => self::abandonedCartHtml(),
            'order_shipped' => self::orderShippedHtml(),
            'order_status' => self::orderStatusHtml(),
            'payment_reminder' => self::paymentReminderHtml(),
            'password_reset' => self::passwordResetHtml(),
        ];
    }

    public static function htmlFor(string $key): ?string
    {
        return self::allHtml()[$key] ?? null;
    }

    public static function headlineFor(string $key): ?string
    {
        return match ($key) {
            'order_confirmation' => 'Order confirmed',
            'abandoned_cart' => 'Items waiting in your cart',
            'order_shipped' => 'Your order is on the way',
            'order_status' => 'Order status updated',
            'payment_reminder' => 'Payment reminder',
            'password_reset' => 'Reset your password',
            default => null,
        };
    }

    private static function orderConfirmationHtml(): string
    {
        return <<<'HTML'
<p style="margin:0 0 16px;color:#333333;">Thank you for your order. We&rsquo;ve received it and will start processing it shortly.</p>
{{detailTable}}
<p style="margin:16px 0 0;color:#333333;">We&rsquo;ll send another email when your order ships. Your invoice may be attached to this message.</p>
{{viewOrderButton}}
HTML;
    }

    private static function abandonedCartHtml(): string
    {
        return <<<'HTML'
<p style="margin:0 0 16px;color:#333333;">You left items in your cart. Complete checkout whenever you&rsquo;re ready.</p>
{{completeOrderButton}}
HTML;
    }

    private static function orderShippedHtml(): string
    {
        return <<<'HTML'
<p style="margin:0 0 16px;color:#333333;">Good news &mdash; your order is on the way.</p>
{{detailTable}}
HTML;
    }

    private static function orderStatusHtml(): string
    {
        return <<<'HTML'
<p style="margin:0 0 16px;color:#333333;">Your order has been updated.</p>
{{detailTable}}
HTML;
    }

    private static function paymentReminderHtml(): string
    {
        return <<<'HTML'
<p style="margin:0 0 16px;color:#333333;">Your order is ready for payment. Complete checkout to confirm your purchase.</p>
{{detailTable}}
{{viewOrderButton}}
HTML;
    }

    private static function passwordResetHtml(): string
    {
        return <<<'HTML'
<p style="margin:0 0 16px;color:#333333;">We received a request to reset your password. Use the button below to choose a new one.</p>
{{resetPasswordButton}}
<p style="margin:16px 0 0;color:#333333;">This link expires in 1 hour. If you did not request a reset, you can ignore this email.</p>
HTML;
    }

    /**
     * @param  array<string, string>  $vars
     */
    public static function expandFragments(string $key, string $html, array $vars): string
    {
        $detailRows = match ($key) {
            'order_confirmation' => [
                ['Order', $vars['orderNumber'] ?? '{{orderNumber}}', true],
                ['Total', $vars['total'] ?? '{{total}}', true],
                ['Payment', $vars['paymentMethodLabel'] ?? $vars['paymentMethod'] ?? '{{paymentMethod}}', false],
            ],
            'order_shipped' => [
                ['Order', $vars['orderNumber'] ?? '{{orderNumber}}', true],
                ['Carrier', $vars['carrier'] ?? '{{carrier}}', false],
                ['Tracking', $vars['trackingNumber'] ?? '{{trackingNumber}}', true],
            ],
            'order_status' => [
                ['Order', $vars['orderNumber'] ?? '{{orderNumber}}', true],
                ['Shipping', $vars['shippingStatus'] ?? '{{shippingStatus}}', false],
                ['Payment', $vars['paymentStatus'] ?? '{{paymentStatus}}', false],
            ],
            'payment_reminder' => [
                ['Order', $vars['orderNumber'] ?? '{{orderNumber}}', true],
                ['Total due', $vars['total'] ?? '{{total}}', true],
                ['Payment', $vars['paymentMethodLabel'] ?? $vars['paymentMethod'] ?? '{{paymentMethod}}', false],
            ],
            default => [],
        };

        $replacements = [
            '{{detailTable}}' => $detailRows !== []
                ? EmailHtmlLayout::detailTable($detailRows)
                : '',
            '{{viewOrderButton}}' => str_contains($html, '{{viewOrderButton}}')
                ? EmailHtmlLayout::button($vars['accountUrl'] ?? '{{accountUrl}}', 'View order')
                : '',
            '{{completeOrderButton}}' => str_contains($html, '{{completeOrderButton}}')
                ? EmailHtmlLayout::button($vars['recoveryUrl'] ?? '{{recoveryUrl}}', 'Complete your order')
                : '',
            '{{resetPasswordButton}}' => str_contains($html, '{{resetPasswordButton}}')
                ? EmailHtmlLayout::button($vars['resetLink'] ?? '{{resetLink}}', 'Reset password')
                : '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }
}
