<?php

declare(strict_types=1);

namespace App\Support\Utils;

use Symfony\Component\HttpFoundation\Exception\BadRequestException;

final class ShippingRegion
{
    public const REGION_PH = 'PH';

    public const REGION_INTL = 'INTL';

    /** @var array<string, true> */
    private const PH_ALIASES = [
        'PH' => true,
        'PHILIPPINES' => true,
        'PHL' => true,
    ];

    public static function normalizeCountryCode(string $country): string
    {
        $value = strtoupper(trim($country));

        if (isset(self::PH_ALIASES[$value])) {
            return 'PH';
        }

        return strlen($value) === 2 ? $value : substr($value, 0, 2);
    }

    public static function isPhilippines(string $country): bool
    {
        return isset(self::PH_ALIASES[strtoupper(trim($country))]);
    }

    public static function getShippingRegion(string $country): string
    {
        return self::isPhilippines($country) ? self::REGION_PH : self::REGION_INTL;
    }

    /**
     * @param  array{
     *     country: string,
     *     street1?: string|null,
     *     street2?: string|null,
     *     city?: string|null,
     *     province?: string|null,
     *     barangay?: string|null,
     *     region?: string|null,
     *     postalCode?: string|null,
     * }  $address
     */
    public static function validateShippingAddress(array $address, bool $forSubmit = false): void
    {
        if (! trim($address['country'] ?? '')) {
            throw new BadRequestException('Country is required.');
        }

        if (! $forSubmit) {
            return;
        }

        if (! trim($address['street1'] ?? '')) {
            throw new BadRequestException('Street address is required.');
        }

        if (self::isPhilippines($address['country'])) {
            if (! trim($address['city'] ?? '') || ! trim($address['province'] ?? '')) {
                throw new BadRequestException('City and province are required for Philippines delivery.');
            }

            return;
        }

        if (! trim($address['city'] ?? '') || ! trim($address['postalCode'] ?? '')) {
            throw new BadRequestException('City and postal code are required for international delivery.');
        }
    }

    public static function getDeliveryNote(
        string $region,
        float $feeInPHP,
        float $subtotalInPHP,
        float $freeShippingMinPHP,
        bool $freeShippingEnabled = true,
    ): string {
        if ($region === self::REGION_PH && $freeShippingEnabled && $feeInPHP === 0.0) {
            return 'Free nationwide delivery on orders over ₱'.number_format($freeShippingMinPHP, 0, '.', ',').'.';
        }

        if ($region === self::REGION_PH && $freeShippingEnabled && $subtotalInPHP < $freeShippingMinPHP) {
            $remaining = (int) ceil($freeShippingMinPHP - $subtotalInPHP);

            return 'Add ₱'.number_format($remaining, 0, '.', ',').' more for free shipping.';
        }

        if ($region === self::REGION_PH) {
            return 'Standard delivery across the Philippines — typically 5–7 business days.';
        }

        return 'International delivery — customs duties may apply. Delivery times vary by destination.';
    }
}
