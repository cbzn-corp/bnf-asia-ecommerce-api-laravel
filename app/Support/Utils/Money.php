<?php

declare(strict_types=1);

namespace App\Support\Utils;

final class Money
{
    public static function toUsdFromPhp(string|float|int $totalPhp, string|float|int $phpPerUsd): string
    {
        $quotient = (float) $totalPhp / (float) $phpPerUsd;

        return sprintf('%.2f', round($quotient, 2));
    }
}
