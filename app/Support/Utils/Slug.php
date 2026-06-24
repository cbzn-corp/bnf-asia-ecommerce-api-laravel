<?php

declare(strict_types=1);

namespace App\Support\Utils;

final class Slug
{
    public static function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = preg_replace('/^-+|-+$/', '', $slug) ?? '';

        return $slug;
    }
}
