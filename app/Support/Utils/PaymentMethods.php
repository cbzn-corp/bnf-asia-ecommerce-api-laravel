<?php

declare(strict_types=1);

namespace App\Support\Utils;

final class PaymentMethods
{
    public const PAYMONGO = 'PAYMONGO';

    public const PAYMONGO_GCASH = 'PAYMONGO_GCASH';

    public const PAYMONGO_MAYA = 'PAYMONGO_MAYA';

    public const STRIPE_CARD = 'STRIPE_CARD';

    public const COD = 'COD';

    public const BANK_TRANSFER = 'BANK_TRANSFER';

    public const BNPL_INSTALLMENT = 'BNPL_INSTALLMENT';

    public const SUPPORT_ASSISTED = 'SUPPORT_ASSISTED';

    /** @var list<string> */
    public const PAYMONGO_CHECKOUT_TYPES = [
        'qrph',
        'dob',
        'gcash',
        'paymaya',
        'grab_pay',
        'card',
        'shopee_pay',
        'brankas',
        'billease',
    ];

    /** @var list<string> */
    public const DEFAULT_PAYMONGO_PAYMENT_METHOD_TYPES = ['qrph', 'dob'];

    /**
     * @param  array{
     *     bnplEnabled?: bool,
     *     supportAssistedCheckoutEnabled?: bool,
     *     codEnabled?: bool,
     *     bankTransferEnabled?: bool,
     *     paymongoGcashEnabled?: bool,
     *     paymongoMayaEnabled?: bool,
     *     paymongoEnabled?: bool,
     *     stripeEnabled?: bool,
     *     paymongoPaymentMethodTypes?: list<string>|null,
     * }  $settings
     * @return list<string>
     */
    public static function getPaymentMethodsForRegion(string $region, array $settings = []): array
    {
        $bnplEnabled = $settings['bnplEnabled'] ?? false;
        $supportAssistedCheckoutEnabled = $settings['supportAssistedCheckoutEnabled'] ?? false;
        $codEnabled = $settings['codEnabled'] ?? true;
        $bankTransferEnabled = $settings['bankTransferEnabled'] ?? true;
        $paymongoEnabled = $settings['paymongoEnabled'] ?? false;
        $stripeEnabled = $settings['stripeEnabled'] ?? false;

        if ($region === ShippingRegion::REGION_PH) {
            $methods = [];

            if ($bnplEnabled) {
                $methods[] = self::BNPL_INSTALLMENT;
            }

            if ($paymongoEnabled && self::resolvePaymongoPaymentMethodTypes($settings) !== []) {
                $methods[] = self::PAYMONGO;
            }

            if ($codEnabled) {
                $methods[] = self::COD;
            }

            if ($bankTransferEnabled) {
                $methods[] = self::BANK_TRANSFER;
            }

            if ($supportAssistedCheckoutEnabled) {
                $methods[] = self::SUPPORT_ASSISTED;
            }

            return $methods;
        }

        return $stripeEnabled ? [self::STRIPE_CARD] : [];
    }

    /**
     * Payment methods staff may assign when creating orders manually.
     * Broader than storefront checkout — gateways need not be enabled.
     *
     * @param  array{
     *     bnplEnabled?: bool,
     *     supportAssistedCheckoutEnabled?: bool,
     * }  $settings
     * @return list<string>
     */
    public static function getManualOrderPaymentMethods(string $region, array $settings = []): array
    {
        if ($region === ShippingRegion::REGION_PH) {
            $methods = [
                self::COD,
                self::BANK_TRANSFER,
                self::PAYMONGO,
                self::PAYMONGO_GCASH,
                self::PAYMONGO_MAYA,
            ];

            if ($settings['bnplEnabled'] ?? false) {
                $methods[] = self::BNPL_INSTALLMENT;
            }

            if ($settings['supportAssistedCheckoutEnabled'] ?? false) {
                $methods[] = self::SUPPORT_ASSISTED;
            }

            return $methods;
        }

        return [self::STRIPE_CARD];
    }

    /**
     * Resolve PayMongo Checkout `payment_method_types` from platform settings.
     *
     * @param  array{paymongoPaymentMethodTypes?: list<string>|null}  $settings
     * @return list<string>
     */
    public static function resolvePaymongoPaymentMethodTypes(array $settings = []): array
    {
        $configured = $settings['paymongoPaymentMethodTypes'] ?? null;
        if (! is_array($configured) || $configured === []) {
            return self::DEFAULT_PAYMONGO_PAYMENT_METHOD_TYPES;
        }

        $types = [];
        foreach ($configured as $type) {
            if (! is_string($type)) {
                continue;
            }
            $normalized = strtolower(trim($type));
            if (in_array($normalized, self::PAYMONGO_CHECKOUT_TYPES, true)) {
                $types[] = $normalized;
            }
        }

        return $types !== []
            ? array_values(array_unique($types))
            : self::DEFAULT_PAYMONGO_PAYMENT_METHOD_TYPES;
    }

    /**
     * @param  array{
     *     paymongoSecretKey?: string|null,
     *     paymongoEnabled?: bool,
     * }  $config
     */
    public static function isPaymongoTestMode(array $config): bool
    {
        $paymongo = trim($config['paymongoSecretKey'] ?? '');

        return (bool) ($config['paymongoEnabled'] ?? false)
            && $paymongo !== ''
            && (str_starts_with($paymongo, 'sk_test_') || str_contains($paymongo, '_test_'));
    }

    /**
     * @param  array{
     *     stripeSecretKey?: string|null,
     *     stripeEnabled?: bool,
     * }  $config
     */
    public static function isStripeTestMode(array $config): bool
    {
        $stripe = trim($config['stripeSecretKey'] ?? '');

        return (bool) ($config['stripeEnabled'] ?? false)
            && $stripe !== ''
            && str_starts_with($stripe, 'sk_test_');
    }

    /**
     * @param  array{
     *     paymongoSecretKey?: string|null,
     *     stripeSecretKey?: string|null,
     *     paymongoEnabled?: bool,
     *     stripeEnabled?: bool,
     * }  $config
     */
    public static function isPaymentGatewayTestMode(array $config): bool
    {
        return self::isPaymongoTestMode($config) || self::isStripeTestMode($config);
    }

    public static function labelForPaymongoType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        return match (strtolower($type)) {
            'qrph' => 'QRPh',
            'dob' => 'Online banking (BPI / UnionBank)',
            'gcash' => 'GCash',
            'paymaya' => 'Maya',
            'grab_pay' => 'GrabPay',
            'card' => 'Card',
            'shopee_pay' => 'ShopeePay',
            'brankas' => 'Online banking (Brankas)',
            'billease' => 'BillEase',
            default => strtoupper($type),
        };
    }
}
