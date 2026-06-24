<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class PostgresTextArray implements CastsAttributes
{
    /**
     * @return list<string>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '{}') {
            return [];
        }

        if (is_array($value)) {
            return array_values($value);
        }

        if (! is_string($value)) {
            return [];
        }

        $trimmed = trim($value, '{}');
        if ($trimmed === '') {
            return [];
        }

        return array_values(array_map(
            static fn (string $item): string => trim($item, '"'),
            explode(',', $trimmed),
        ));
    }

    /**
     * @param  list<string>|null  $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! is_array($value) || $value === []) {
            return '{}';
        }

        $escaped = array_map(
            static fn (string $item): string => '"'.str_replace('"', '\\"', $item).'"',
            $value,
        );

        return '{'.implode(',', $escaped).'}';
    }
}
