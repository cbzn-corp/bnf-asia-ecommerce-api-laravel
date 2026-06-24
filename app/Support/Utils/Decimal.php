<?php

declare(strict_types=1);

namespace App\Support\Utils;

final class Decimal
{
    private const SCALE = 2;

    public static function of(string|float|int $value): string
    {
        return bcadd((string) $value, '0', self::SCALE);
    }

    public static function add(string|float|int $a, string|float|int $b): string
    {
        return bcadd((string) $a, (string) $b, self::SCALE);
    }

    public static function sub(string|float|int $a, string|float|int $b): string
    {
        return bcsub((string) $a, (string) $b, self::SCALE);
    }

    public static function mul(string|float|int $a, string|float|int $b): string
    {
        return bcmul((string) $a, (string) $b, self::SCALE);
    }

    public static function div(string|float|int $a, string|float|int $b): string
    {
        return bcdiv((string) $a, (string) $b, self::SCALE);
    }

    public static function gte(string|float|int $a, string|float|int $b): bool
    {
        return bccomp((string) $a, (string) $b, self::SCALE) >= 0;
    }

    public static function gt(string|float|int $a, string|float|int $b): bool
    {
        return bccomp((string) $a, (string) $b, self::SCALE) > 0;
    }

    public static function lt(string|float|int $a, string|float|int $b): bool
    {
        return bccomp((string) $a, (string) $b, self::SCALE) < 0;
    }

    public static function min(string|float|int $a, string|float|int $b): string
    {
        return self::lt($a, $b) ? self::of($a) : self::of($b);
    }

    public static function toFloat(string|float|int $value): float
    {
        return (float) self::of($value);
    }
}
