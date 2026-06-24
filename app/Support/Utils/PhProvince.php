<?php

declare(strict_types=1);

namespace App\Support\Utils;

final class PhProvince
{
    /** @var list<string> */
    public const PH_PROVINCE_CANONICAL = [
        'Metro Manila',
        'Abra',
        'Albay',
        'Apayao',
        'Aurora',
        'Bataan',
        'Batangas',
        'Benguet',
        'Bulacan',
        'Cagayan',
        'Camarines Norte',
        'Camarines Sur',
        'Cavite',
        'Catanduanes',
        'Ifugao',
        'Ilocos Norte',
        'Ilocos Sur',
        'Isabela',
        'Kalinga',
        'La Union',
        'Laguna',
        'Marinduque',
        'Mountain Province',
        'Nueva Ecija',
        'Nueva Vizcaya',
        'Occidental Mindoro',
        'Oriental Mindoro',
        'Pampanga',
        'Pangasinan',
        'Quezon',
        'Quirino',
        'Rizal',
        'Romblon',
        'Sorsogon',
        'Tarlac',
        'Zambales',
        'Aklan',
        'Antique',
        'Biliran',
        'Bohol',
        'Capiz',
        'Cebu',
        'Eastern Samar',
        'Guimaras',
        'Iloilo',
        'Leyte',
        'Negros Occidental',
        'Negros Oriental',
        'Northern Samar',
        'Samar',
        'Siquijor',
        'Southern Leyte',
        'Agusan del Norte',
        'Agusan del Sur',
        'Basilan',
        'Bukidnon',
        'Camiguin',
        'Cotabato',
        'Davao de Oro',
        'Davao del Norte',
        'Davao del Sur',
        'Davao Occidental',
        'Davao Oriental',
        'Dinagat Islands',
        'Lanao del Norte',
        'Lanao del Sur',
        'Maguindanao',
        'Misamis Occidental',
        'Misamis Oriental',
        'North Cotabato',
        'Sarangani',
        'South Cotabato',
        'Sultan Kudarat',
        'Surigao del Norte',
        'Surigao del Sur',
        'Zamboanga del Norte',
        'Zamboanga del Sur',
        'Zamboanga Sibugay',
        'Batanes',
        'Palawan',
        'Sulu',
        'Tawi-Tawi',
    ];

    /** @var array<string, string> */
    private const PH_PROVINCE_ALIASES = [
        'METROMANILA' => 'Metro Manila',
        'METRO MANILA' => 'Metro Manila',
        'NCR' => 'Metro Manila',
        'NATIONAL CAPITAL REGION' => 'Metro Manila',
        'MANILA METRO' => 'Metro Manila',
    ];

    public static function normalizePhProvince(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        foreach (self::PH_PROVINCE_CANONICAL as $province) {
            if (strtolower($province) === strtolower($trimmed)) {
                return $province;
            }
        }

        $aliasKey = strtoupper(preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed);

        if (isset(self::PH_PROVINCE_ALIASES[$aliasKey])) {
            return self::PH_PROVINCE_ALIASES[$aliasKey];
        }

        $compact = str_replace(' ', '', $aliasKey);

        if (isset(self::PH_PROVINCE_ALIASES[$compact])) {
            return self::PH_PROVINCE_ALIASES[$compact];
        }

        return $trimmed;
    }
}
