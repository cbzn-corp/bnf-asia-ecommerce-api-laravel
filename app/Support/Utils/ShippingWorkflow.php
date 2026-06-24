<?php

declare(strict_types=1);

namespace App\Support\Utils;

use RuntimeException;

final class ShippingWorkflow
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_PROCESSING = 'PROCESSING';

    public const STATUS_SHIPPED = 'SHIPPED';

    public const STATUS_DELIVERED = 'DELIVERED';

    public const STATUS_CANCELLED = 'CANCELLED';

    /** @var array<string, list<string>> */
    private const ALLOWED = [
        self::STATUS_PENDING => [self::STATUS_PROCESSING, self::STATUS_CANCELLED],
        self::STATUS_PROCESSING => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_DELIVERED],
        self::STATUS_DELIVERED => [],
        self::STATUS_CANCELLED => [],
    ];

    public static function assertShippingTransition(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        $allowed = self::ALLOWED[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw new RuntimeException("Invalid shipping transition: {$from} → {$to}");
        }
    }
}
