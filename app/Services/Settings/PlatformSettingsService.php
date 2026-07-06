<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\PlatformSetting;
use App\Support\Cache\ApiCache;
use App\Support\Config\AppSecrets;
use App\Support\Utils\PaymentMethods;

class PlatformSettingsService
{
    public function getRaw(): PlatformSetting
    {
        return PlatformSetting::query()->firstOrCreate(
            ['id' => 'default'],
            [
                'phpPerUsd' => 56.25,
                'freeShippingEnabled' => true,
                'freeShippingMinPHP' => 50000,
                'vatRatePercent' => 12,
                'vatEnabled' => true,
                'paymongoEnabled' => false,
                'stripeEnabled' => false,
                'lowStockThreshold' => 5,
                'bnplEnabled' => false,
                'abandonedCartEnabled' => true,
                'abandonedCartHours' => 24,
                'supportAssistedCheckoutEnabled' => false,
                'customerChatEnabled' => true,
                'quoteStaleAlertDays' => 7,
                'checkoutOrderNotesEnabled' => true,
                'guestCheckoutEnabled' => true,
                'compareEnabled' => true,
                'codEnabled' => true,
                'bankTransferEnabled' => true,
                'paymongoGcashEnabled' => true,
                'paymongoMayaEnabled' => true,
                'pricesIncludeVat' => false,
                'deliveryFeeAtCheckoutEnabled' => true,
            ],
        );
    }

    public function getPhpPerUsd(): string
    {
        return (string) $this->getRaw()->phpPerUsd;
    }

    public function getFreeShippingMinPHP(): float
    {
        return (float) $this->getRaw()->freeShippingMinPHP;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPublicSummary(): array
    {
        return ApiCache::remember(ApiCache::DOMAIN_SETTINGS, 'platform:public', function () {
            $row = $this->getRaw();
            $paymongoSecretConfigured = AppSecrets::isPaymongoSecretConfigured();
            $stripeSecretConfigured = AppSecrets::isStripeSecretConfigured();
            $paymentSecrets = AppSecrets::getPaymentSecretKeys();

            return [
                'baseCurrency' => 'PHP',
                'phpPerUsd' => (float) $row->phpPerUsd,
                'freeShippingEnabled' => $row->freeShippingEnabled,
                'freeShippingMinPHP' => (float) $row->freeShippingMinPHP,
                'vatEnabled' => $row->vatEnabled,
                'vatRatePercent' => (float) $row->vatRatePercent,
                'paymentProviders' => [
                    'paymongo' => $row->paymongoEnabled && $paymongoSecretConfigured,
                    'stripe' => $row->stripeEnabled && $stripeSecretConfigured,
                    'cod' => true,
                ],
                'stripePublishableKey' => $row->stripeEnabled ? $row->stripePublishableKey : null,
                'paymongoPublicKey' => $row->paymongoEnabled ? $row->paymongoPublicKey : null,
                'bnplEnabled' => $row->bnplEnabled,
                'supportAssistedCheckoutEnabled' => $row->supportAssistedCheckoutEnabled,
                'customerChatEnabled' => $row->customerChatEnabled,
                'checkoutOrderNotesEnabled' => $row->checkoutOrderNotesEnabled,
                'guestCheckoutEnabled' => $row->guestCheckoutEnabled,
                'compareEnabled' => $row->compareEnabled,
                'pricesIncludeVat' => $row->pricesIncludeVat,
                'codEnabled' => $row->codEnabled,
                'bankTransferEnabled' => $row->bankTransferEnabled,
                'paymongoGcashEnabled' => $row->paymongoGcashEnabled,
                'paymongoMayaEnabled' => $row->paymongoMayaEnabled,
                'deliveryFeeAtCheckoutEnabled' => $row->deliveryFeeAtCheckoutEnabled !== false,
                'paymentTestMode' => PaymentMethods::isPaymentGatewayTestMode([
                    ...AppSecrets::getPaymentSecretKeys(),
                    'paymongoEnabled' => $row->paymongoEnabled,
                    'stripeEnabled' => $row->stripeEnabled,
                ]),
                'maintenanceModeEnabled' => (bool) $row->maintenanceModeEnabled,
                'maintenanceMessage' => $row->maintenanceMessage,
                'maintenanceWhitelistIps' => $this->parseMaintenanceWhitelistIps($row->maintenanceWhitelistIps),
            ];
        });
    }

    /**
     * @return list<string>
     */
    public function parseMaintenanceWhitelistIps(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $ips = [];
        foreach (preg_split('/[\r\n,]+/', $raw) as $line) {
            $ip = trim((string) $line);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $ips[] = $ip;
            }
        }

        return array_values(array_unique($ips));
    }

    public function validateMaintenanceBypassKey(?string $key): bool
    {
        $secret = $this->getRaw()->maintenanceBypassSecret;
        if ($secret === null || $secret === '') {
            return false;
        }

        return hash_equals($secret, (string) $key);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdminConfig(): array
    {
        $row = $this->getRaw();
        $smtp = AppSecrets::getSmtpConfig();
        $paymongoSecretConfigured = AppSecrets::isPaymongoSecretConfigured();
        $stripeSecretConfigured = AppSecrets::isStripeSecretConfigured();
        $paymongoWebhookConfigured = AppSecrets::getPaymongoWebhookSecret() !== null;
        $stripeWebhookConfigured = AppSecrets::getStripeWebhookSecret() !== null;
        $paymentSecrets = AppSecrets::getPaymentSecretKeys();

        return [
            'phpPerUsd' => (float) $row->phpPerUsd,
            'freeShippingEnabled' => $row->freeShippingEnabled,
            'freeShippingMinPHP' => (float) $row->freeShippingMinPHP,
            'vatRatePercent' => (float) $row->vatRatePercent,
            'vatEnabled' => $row->vatEnabled,
            'email' => [
                'smtpHost' => $smtp['host'],
                'smtpPort' => $smtp['port'],
                'smtpUser' => $smtp['user'],
                'emailFrom' => $smtp['from'],
                'smtpPassConfigured' => $smtp['pass'] !== null,
                'configured' => AppSecrets::isSmtpConfigured(),
            ],
            'paymongo' => [
                'enabled' => $row->paymongoEnabled,
                'publicKey' => $row->paymongoPublicKey,
                'secretConfigured' => $paymongoSecretConfigured,
                'webhookConfigured' => $paymongoWebhookConfigured,
                'configured' => $row->paymongoEnabled && $paymongoSecretConfigured,
                'paymentTestMode' => PaymentMethods::isPaymongoTestMode([
                    ...$paymentSecrets,
                    'paymongoEnabled' => $row->paymongoEnabled,
                ]),
            ],
            'stripe' => [
                'enabled' => $row->stripeEnabled,
                'publishableKey' => $row->stripePublishableKey,
                'secretConfigured' => $stripeSecretConfigured,
                'webhookConfigured' => $stripeWebhookConfigured,
                'configured' => $row->stripeEnabled && $stripeSecretConfigured,
                'paymentTestMode' => PaymentMethods::isStripeTestMode([
                    ...$paymentSecrets,
                    'stripeEnabled' => $row->stripeEnabled,
                ]),
            ],
            'operations' => [
                'bnplEnabled' => $row->bnplEnabled,
                'abandonedCartEnabled' => $row->abandonedCartEnabled,
                'abandonedCartHours' => $row->abandonedCartHours,
                'lowStockThreshold' => $row->lowStockThreshold,
                'supportAssistedCheckoutEnabled' => $row->supportAssistedCheckoutEnabled,
                'customerChatEnabled' => $row->customerChatEnabled,
                'quoteStaleAlertDays' => $row->quoteStaleAlertDays,
                'compareEnabled' => $row->compareEnabled,
                'maintenanceModeEnabled' => (bool) $row->maintenanceModeEnabled,
                'maintenanceMessage' => $row->maintenanceMessage,
                'maintenanceWhitelistIps' => $row->maintenanceWhitelistIps ?? '',
                'maintenanceBypassSecret' => $row->maintenanceBypassSecret,
            ],
            'store' => [
                'name' => $row->storeName,
                'email' => $row->storeEmail,
                'phone' => $row->storePhone,
                'address' => $row->storeAddress,
            ],
            'checkout' => [
                'orderNotesEnabled' => $row->checkoutOrderNotesEnabled,
                'guestCheckoutEnabled' => $row->guestCheckoutEnabled,
                'codEnabled' => $row->codEnabled,
                'bankTransferEnabled' => $row->bankTransferEnabled,
                'paymongoGcashEnabled' => $row->paymongoGcashEnabled,
                'paymongoMayaEnabled' => $row->paymongoMayaEnabled,
                'pricesIncludeVat' => $row->pricesIncludeVat,
                'deliveryFeeAtCheckoutEnabled' => $row->deliveryFeeAtCheckoutEnabled !== false,
            ],
            'payments' => [
                'codEnabled' => $row->codEnabled,
                'bankTransferEnabled' => $row->bankTransferEnabled,
                'paymongoGcashEnabled' => $row->paymongoGcashEnabled,
                'paymongoMayaEnabled' => $row->paymongoMayaEnabled,
            ],
            'abandonedCartDiscountCode' => $row->abandonedCartDiscountCode,
            'paymentTestMode' => PaymentMethods::isPaymentGatewayTestMode([
                ...AppSecrets::getPaymentSecretKeys(),
                'paymongoEnabled' => $row->paymongoEnabled,
                'stripeEnabled' => $row->stripeEnabled,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $dto
     * @return array<string, mixed>
     */
    public function updateAdminConfig(array $dto): array
    {
        $row = $this->getRaw();
        $data = [];

        $fields = [
            'phpPerUsd', 'freeShippingEnabled', 'freeShippingMinPHP', 'vatRatePercent', 'vatEnabled',
            'paymongoEnabled', 'stripeEnabled', 'bnplEnabled', 'abandonedCartEnabled', 'abandonedCartHours',
            'lowStockThreshold', 'supportAssistedCheckoutEnabled', 'customerChatEnabled', 'quoteStaleAlertDays',
            'checkoutOrderNotesEnabled', 'guestCheckoutEnabled', 'compareEnabled',
            'codEnabled', 'bankTransferEnabled', 'paymongoGcashEnabled', 'paymongoMayaEnabled', 'pricesIncludeVat',
            'deliveryFeeAtCheckoutEnabled',
            'maintenanceModeEnabled', 'maintenanceMessage', 'maintenanceBypassSecret',
        ];

        foreach ($fields as $field) {
            if (array_key_exists($field, $dto)) {
                $data[$field] = $dto[$field];
            }
        }

        if (array_key_exists('paymongoPublicKey', $dto)) {
            $data['paymongoPublicKey'] = $dto['paymongoPublicKey'] ?: null;
        }
        if (array_key_exists('stripePublishableKey', $dto)) {
            $data['stripePublishableKey'] = $dto['stripePublishableKey'] ?: null;
        }
        foreach (['storeName', 'storeEmail', 'storePhone', 'storeAddress'] as $field) {
            if (array_key_exists($field, $dto)) {
                $data[$field] = $dto[$field] ?: null;
            }
        }
        if (array_key_exists('abandonedCartDiscountCode', $dto)) {
            $data['abandonedCartDiscountCode'] = trim((string) $dto['abandonedCartDiscountCode']) ?: null;
        }
        if (array_key_exists('maintenanceWhitelistIps', $dto)) {
            $parsed = $this->parseMaintenanceWhitelistIps((string) $dto['maintenanceWhitelistIps']);
            $data['maintenanceWhitelistIps'] = $parsed === [] ? null : implode("\n", $parsed);
        }
        if (array_key_exists('maintenanceBypassSecret', $dto)) {
            $data['maintenanceBypassSecret'] = trim((string) $dto['maintenanceBypassSecret']) ?: null;
        }

        if ($data !== []) {
            $row->update($data);
        }

        ApiCache::bump(ApiCache::DOMAIN_SETTINGS);
        ApiCache::bump(ApiCache::DOMAIN_CATALOG);

        return $this->getAdminConfig();
    }
}
