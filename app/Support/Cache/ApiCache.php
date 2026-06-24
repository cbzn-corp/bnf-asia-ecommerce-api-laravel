<?php

declare(strict_types=1);

namespace App\Support\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned API response cache. Uses Laravel's default CACHE_STORE driver:
 * - local: file (no Redis required)
 * - production: redis
 */
final class ApiCache
{
    public const DOMAIN_CATALOG = 'catalog';

    public const DOMAIN_CONTENT = 'content';

    public const DOMAIN_SETTINGS = 'settings';

    public static function enabled(): bool
    {
        return filter_var(env('API_CACHE_ENABLED', true), FILTER_VALIDATE_BOOL);
    }

    public static function ttl(): int
    {
        return max(1, (int) env('API_CACHE_TTL', 60));
    }

    public static function remember(string $domain, string $key, Closure $callback): mixed
    {
        if (! self::enabled()) {
            return $callback();
        }

        return Cache::remember(self::key($domain, $key), self::ttl(), $callback);
    }

    public static function bump(string $domain): void
    {
        $versionKey = "api-cache:version:{$domain}";

        if (! Cache::has($versionKey)) {
            Cache::forever($versionKey, 1);
        }

        Cache::increment($versionKey);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function queryKey(array $query): string
    {
        ksort($query);

        return hash('xxh128', json_encode($query, JSON_THROW_ON_ERROR));
    }

    private static function key(string $domain, string $key): string
    {
        $version = (int) Cache::get("api-cache:version:{$domain}", 1);

        return "api-cache:v{$version}:{$domain}:{$key}";
    }
}
