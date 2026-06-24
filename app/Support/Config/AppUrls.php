<?php

declare(strict_types=1);

namespace App\Support\Config;

final class AppUrls
{
    private const LOCAL_STOREFRONT = 'http://localhost:3000';

    private const LOCAL_ADMIN = 'http://localhost:3001';

    public static function getStorefrontUrl(): string
    {
        $url = env('STOREFRONT_URL', self::LOCAL_STOREFRONT);

        return rtrim($url, '/');
    }

    public static function getAdminUrl(): string
    {
        $url = env('ADMIN_URL', self::LOCAL_ADMIN);

        return rtrim($url, '/');
    }

    /**
     * @return list<string>
     */
    public static function getCorsOrigins(): array
    {
        $productionOrigins = array_values(array_filter([
            env('STOREFRONT_URL'),
            env('ADMIN_URL'),
        ], static fn (?string $url): bool => $url !== null && $url !== ''));

        $productionOrigins = array_map(
            static fn (string $url): string => rtrim($url, '/'),
            $productionOrigins,
        );

        if (app()->environment('production')) {
            return $productionOrigins;
        }

        $origins = array_merge([self::LOCAL_STOREFRONT, self::LOCAL_ADMIN], $productionOrigins);

        return array_values(array_unique($origins));
    }
}
