<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\ContentBlock;

final class ContentPagesRegistry
{
    public const INDEX_KEY = 'content-pages-index';

    /**
     * @return list<array{slug: string, label: string, description: string}>
     */
    public static function customPages(): array
    {
        $block = ContentBlock::query()->find(self::INDEX_KEY);
        if (! $block || ! is_array($block->value['pages'] ?? null)) {
            return [];
        }

        $pages = [];
        foreach ($block->value['pages'] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $slug = trim((string) ($entry['slug'] ?? ''));
            if ($slug === '' || ! self::isValidSlug($slug)) {
                continue;
            }
            if (self::isBuiltInSlug($slug)) {
                continue;
            }
            $pages[] = [
                'slug' => $slug,
                'label' => trim((string) ($entry['label'] ?? $slug)) ?: $slug,
                'description' => trim((string) ($entry['description'] ?? '')),
            ];
        }

        return $pages;
    }

    /**
     * @return list<string>
     */
    public static function allSlugs(): array
    {
        $builtIn = array_map(
            static fn (string $key) => str_replace('page-', '', $key),
            StorefrontDefaults::STATIC_PAGE_KEYS,
        );

        return array_values(array_unique([
            ...$builtIn,
            ...array_column(self::customPages(), 'slug'),
        ]));
    }

    public static function normalizeSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        if (str_starts_with($slug, '/pages/')) {
            $slug = substr($slug, 7);
        } elseif (str_starts_with($slug, 'pages/')) {
            $slug = substr($slug, 6);
        }

        return trim($slug, '/');
    }

    public static function pagePath(string $slug): string
    {
        return '/pages/'.self::normalizeSlug($slug);
    }

    public static function isValidSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }

    public static function isBuiltInSlug(string $slug): bool
    {
        return in_array('page-'.$slug, StorefrontDefaults::STATIC_PAGE_KEYS, true);
    }

    public static function pageKey(string $slug): string
    {
        return 'page-'.$slug;
    }

    /**
     * @param  array{slug: string, label: string, description?: string}  $entry
     */
    public static function addPage(array $entry): void
    {
        $slug = self::normalizeSlug(trim($entry['slug']));
        if (! self::isValidSlug($slug)) {
            throw new \InvalidArgumentException('Invalid page slug');
        }
        if (self::isBuiltInSlug($slug)) {
            throw new \InvalidArgumentException('Slug is reserved for a built-in page');
        }

        $pages = self::customPages();
        foreach ($pages as $page) {
            if ($page['slug'] === $slug) {
                throw new \InvalidArgumentException('A page with this slug already exists');
            }
        }

        $pages[] = [
            'slug' => $slug,
            'label' => trim($entry['label']) ?: $slug,
            'description' => trim($entry['description'] ?? ''),
        ];

        ContentBlock::query()->updateOrCreate(
            ['key' => self::INDEX_KEY],
            ['value' => ['pages' => $pages]],
        );
    }

    public static function removePage(string $slug): void
    {
        if (self::isBuiltInSlug($slug)) {
            throw new \InvalidArgumentException('Cannot delete a built-in page');
        }

        $pages = array_values(array_filter(
            self::customPages(),
            static fn (array $p) => $p['slug'] !== $slug,
        ));

        ContentBlock::query()->updateOrCreate(
            ['key' => self::INDEX_KEY],
            ['value' => ['pages' => $pages]],
        );

        ContentBlock::query()->where('key', self::pageKey($slug))->delete();
    }
};
