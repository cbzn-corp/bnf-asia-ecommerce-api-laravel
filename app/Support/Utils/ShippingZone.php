<?php

declare(strict_types=1);

namespace App\Support\Utils;

final class ShippingZone
{
    public const ZONE_NCR = 'NCR';

    public const ZONE_LUZON = 'LUZON';

    public const ZONE_VISAYAS = 'VISAYAS';

    public const ZONE_MINDANAO = 'MINDANAO';

    public const ZONE_REMOTE = 'REMOTE';

    /** @var array<string, string> */
    public const SHIPPING_ZONE_LABELS = [
        self::ZONE_NCR => 'Metro Manila (NCR)',
        self::ZONE_LUZON => 'Luzon',
        self::ZONE_VISAYAS => 'Visayas',
        self::ZONE_MINDANAO => 'Mindanao',
        self::ZONE_REMOTE => 'Remote / island',
    ];

    /** @var array<string, true> */
    private const NCR_PROVINCE_ALIASES = [
        'METRO MANILA' => true,
        'NCR' => true,
        'NATIONAL CAPITAL REGION' => true,
        'MANILA METRO' => true,
    ];

    /** @var array<string, true> */
    private const NCR_CITY_ALIASES = [
        'MANILA' => true,
        'QUEZON CITY' => true,
        'MAKATI' => true,
        'MAKATI CITY' => true,
        'TAGUIG' => true,
        'TAGUIG CITY' => true,
        'PASIG' => true,
        'PASIG CITY' => true,
        'MANDALUYONG' => true,
        'MANDALUYONG CITY' => true,
        'SAN JUAN' => true,
        'SAN JUAN CITY' => true,
        'MARIKINA' => true,
        'MARIKINA CITY' => true,
        'CALOOCAN' => true,
        'CALOOCAN CITY' => true,
        'MALABON' => true,
        'MALABON CITY' => true,
        'NAVOTAS' => true,
        'NAVOTAS CITY' => true,
        'VALENZUELA' => true,
        'VALENZUELA CITY' => true,
        'PARANAQUE' => true,
        'PARAÑAQUE' => true,
        'PARAÑAQUE CITY' => true,
        'LAS PINAS' => true,
        'LAS PIÑAS' => true,
        'LAS PIÑAS CITY' => true,
        'MUNTINLUPA' => true,
        'MUNTINLUPA CITY' => true,
        'PASAY' => true,
        'PASAY CITY' => true,
        'PATEROS' => true,
        'PATEROS MUNICIPALITY' => true,
    ];

    /** @var array<string, true> */
    private const LUZON_PROVINCES = [
        'ABRA' => true,
        'APAYAO' => true,
        'AURORA' => true,
        'BATAAN' => true,
        'BATANGAS' => true,
        'BENGUET' => true,
        'BULACAN' => true,
        'CAGAYAN' => true,
        'CAMARINES NORTE' => true,
        'CAMARINES SUR' => true,
        'CAVITE' => true,
        'IFUGAO' => true,
        'ILOCOS NORTE' => true,
        'ILOCOS SUR' => true,
        'ISABELA' => true,
        'KALINGA' => true,
        'LA UNION' => true,
        'LAGUNA' => true,
        'MOUNTAIN PROVINCE' => true,
        'NUEVA ECIJA' => true,
        'NUEVA VIZCAYA' => true,
        'PAMPANGA' => true,
        'PANGASINAN' => true,
        'QUEZON' => true,
        'QUIRINO' => true,
        'RIZAL' => true,
        'ROMBLON' => true,
        'SORSOGON' => true,
        'TARLAC' => true,
        'ZAMBALES' => true,
        'ALBAY' => true,
        'CATANDUANES' => true,
        'MARINDUQUE' => true,
        'OCCIDENTAL MINDORO' => true,
        'ORIENTAL MINDORO' => true,
    ];

    /** @var array<string, true> */
    private const VISAYAS_PROVINCES = [
        'AKLAN' => true,
        'ANTIQUE' => true,
        'BOHOL' => true,
        'CAPIZ' => true,
        'CEBU' => true,
        'EASTERN SAMAR' => true,
        'GUIMARAS' => true,
        'ILOILO' => true,
        'LEYTE' => true,
        'NEGROS OCCIDENTAL' => true,
        'NEGROS ORIENTAL' => true,
        'NORTHERN SAMAR' => true,
        'SAMAR' => true,
        'SIQUIJOR' => true,
        'SOUTHERN LEYTE' => true,
        'BILIRAN' => true,
    ];

    /** @var array<string, true> */
    private const MINDANAO_PROVINCES = [
        'AGUSAN DEL NORTE' => true,
        'AGUSAN DEL SUR' => true,
        'BASILAN' => true,
        'BUKIDNON' => true,
        'CAMIGUIN' => true,
        'COTABATO' => true,
        'DAVAO DE ORO' => true,
        'DAVAO DEL NORTE' => true,
        'DAVAO DEL SUR' => true,
        'DAVAO OCCIDENTAL' => true,
        'DAVAO ORIENTAL' => true,
        'DINAGAT ISLANDS' => true,
        'LANAO DEL NORTE' => true,
        'LANAO DEL SUR' => true,
        'MAGUINDANAO' => true,
        'MISAMIS OCCIDENTAL' => true,
        'MISAMIS ORIENTAL' => true,
        'NORTH COTABATO' => true,
        'SARANGANI' => true,
        'SOUTH COTABATO' => true,
        'SULTAN KUDARAT' => true,
        'SULU' => true,
        'SURIGAO DEL NORTE' => true,
        'SURIGAO DEL SUR' => true,
        'TAWI-TAWI' => true,
        'ZAMBOANGA DEL NORTE' => true,
        'ZAMBOANGA DEL SIBUGAY' => true,
        'ZAMBOANGA SIBUGAY' => true,
        'ZAMBOANGA DEL SUR' => true,
        'COMPOSTELA VALLEY' => true,
    ];

    /** @var array<string, true> */
    private const REMOTE_PROVINCES = [
        'BATANES' => true,
        'PALAWAN' => true,
        'SULU' => true,
        'TAWI-TAWI' => true,
    ];

    private static function normalizeLocation(?string $value): string
    {
        $normalized = strtoupper(trim($value ?? ''));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';
        $normalized = str_replace('Ñ', 'N', $normalized);

        return $normalized;
    }

    /**
     * @param  array{
     *     country?: string,
     *     street1?: string|null,
     *     street2?: string|null,
     *     city?: string|null,
     *     province?: string|null,
     *     barangay?: string|null,
     *     region?: string|null,
     *     postalCode?: string|null,
     * }|null  $address
     */
    public static function resolvePhilippinesZone(?array $address): string
    {
        $province = self::normalizeLocation($address['province'] ?? null);
        $city = self::normalizeLocation($address['city'] ?? null);

        if (isset(self::NCR_PROVINCE_ALIASES[$province]) || isset(self::NCR_CITY_ALIASES[$city])) {
            return self::ZONE_NCR;
        }

        if (isset(self::REMOTE_PROVINCES[$province])) {
            return self::ZONE_REMOTE;
        }

        if (isset(self::VISAYAS_PROVINCES[$province])) {
            return self::ZONE_VISAYAS;
        }

        if (isset(self::MINDANAO_PROVINCES[$province])) {
            return self::ZONE_MINDANAO;
        }

        if (isset(self::LUZON_PROVINCES[$province])) {
            return self::ZONE_LUZON;
        }

        return self::ZONE_LUZON;
    }

    /**
     * @param  array{
     *     country?: string,
     *     street1?: string|null,
     *     street2?: string|null,
     *     city?: string|null,
     *     province?: string|null,
     *     barangay?: string|null,
     *     region?: string|null,
     *     postalCode?: string|null,
     * }|null  $address
     */
    public static function getShippingZoneForAddress(?array $address): ?string
    {
        if ($address === null || empty($address['country']) || ! ShippingRegion::isPhilippines($address['country'])) {
            return null;
        }

        return self::resolvePhilippinesZone($address);
    }

    public static function getShippingZoneLabel(string $code): string
    {
        return self::SHIPPING_ZONE_LABELS[$code];
    }
}
