<?php

declare(strict_types=1);

namespace App\Support\Utils;

final class PaymentMethods
{
    public const PAYMONGO_GCASH = 'PAYMONGO_GCASH';

    public const PAYMONGO_MAYA = 'PAYMONGO_MAYA';

    public const STRIPE_CARD = 'STRIPE_CARD';

    public const COD = 'COD';

    public const BANK_TRANSFER = 'BANK_TRANSFER';

    public const BNPL_INSTALLMENT = 'BNPL_INSTALLMENT';

    public const SUPPORT_ASSISTED = 'SUPPORT_ASSISTED';

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
     * }  $settings
     * @return list<string>
     */
    public static function getPaymentMethodsForRegion(string $region, array $settings = []): array
    {
        $bnplEnabled = $settings['bnplEnabled'] ?? false;
        $supportAssistedCheckoutEnabled = $settings['supportAssistedCheckoutEnabled'] ?? false;
        $codEnabled = $settings['codEnabled'] ?? true;
        $bankTransferEnabled = $settings['bankTransferEnabled'] ?? true;
        $paymongoGcashEnabled = $settings['paymongoGcashEnabled'] ?? true;
        $paymongoMayaEnabled = $settings['paymongoMayaEnabled'] ?? true;
        $paymongoEnabled = $settings['paymongoEnabled'] ?? false;
        $stripeEnabled = $settings['stripeEnabled'] ?? false;

        if ($region === ShippingRegion::REGION_PH) {
            $methods = [];

            if ($bnplEnabled) {
                $methods[] = self::BNPL_INSTALLMENT;
            }

            if ($paymongoEnabled && $paymongoGcashEnabled) {
                $methods[] = self::PAYMONGO_GCASH;
            }

            if ($paymongoEnabled && $paymongoMayaEnabled) {
                $methods[] = self::PAYMONGO_MAYA;
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

            return $methods !== [] ? $methods : [self::COD];
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
}
