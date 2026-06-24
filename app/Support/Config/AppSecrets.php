<?php

declare(strict_types=1);

namespace App\Support\Config;

use RuntimeException;

final class AppSecrets
{
    private const JWT_PLACEHOLDER = 'change-this-to-a-long-random-string-in-production';

    private const JWT_DEV_FALLBACK = 'bnf-asia-dev-secret-change-in-production';

    public static function getJwtSecret(): string
    {
        $secret = trim((string) env('JWT_SECRET', ''));

        $invalid = $secret === ''
            || $secret === self::JWT_PLACEHOLDER
            || $secret === self::JWT_DEV_FALLBACK;

        if ($invalid) {
            if (app()->environment('production')) {
                throw new RuntimeException('JWT_SECRET must be set to a strong random value in production');
            }

            return $secret !== '' ? $secret : self::JWT_DEV_FALLBACK;
        }

        return $secret;
    }

    /**
     * @return array{
     *     host: string|null,
     *     port: int,
     *     user: string|null,
     *     pass: string|null,
     *     from: string|null,
     * }
     */
    public static function getSmtpConfig(): array
    {
        $port = (int) (self::env('SMTP_PORT') ?? '587');

        return [
            'host' => self::env('SMTP_HOST'),
            'port' => is_finite((float) $port) && $port > 0 ? $port : 587,
            'user' => self::env('SMTP_USER'),
            'pass' => self::env('SMTP_PASS'),
            'from' => self::env('EMAIL_FROM'),
        ];
    }

    public static function isSmtpConfigured(): bool
    {
        $smtp = self::getSmtpConfig();

        return $smtp['host'] !== null && $smtp['host'] !== ''
            && $smtp['from'] !== null && $smtp['from'] !== '';
    }

    public static function getPaymongoSecretKey(): ?string
    {
        return self::env('PAYMONGO_SECRET_KEY');
    }

    public static function getStripeSecretKey(): ?string
    {
        return self::env('STRIPE_SECRET_KEY');
    }

    public static function getPaymongoWebhookSecret(): ?string
    {
        return self::env('PAYMONGO_WEBHOOK_SECRET');
    }

    public static function getStripeWebhookSecret(): ?string
    {
        return self::env('STRIPE_WEBHOOK_SECRET');
    }

    public static function isPaymongoSecretConfigured(): bool
    {
        return self::getPaymongoSecretKey() !== null;
    }

    public static function isStripeSecretConfigured(): bool
    {
        return self::getStripeSecretKey() !== null;
    }

    /**
     * @return array{
     *     paymongoSecretKey: string|null,
     *     stripeSecretKey: string|null,
     * }
     */
    public static function getPaymentSecretKeys(): array
    {
        return [
            'paymongoSecretKey' => self::getPaymongoSecretKey(),
            'stripeSecretKey' => self::getStripeSecretKey(),
        ];
    }

    private static function env(string $name): ?string
    {
        $value = env($name);

        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
