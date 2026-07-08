<?php

declare(strict_types=1);

namespace App\Support\Content;

final class HomepageUtils
{
    /**
     * @param  array{enabled: bool, startsAt?: string|null, endsAt?: string|null}  $schedule
     */
    public static function isScheduleActive(array $schedule, ?\DateTimeInterface $now = null): bool
    {
        $now = $now ?? now();

        if (! ($schedule['enabled'] ?? true)) {
            return false;
        }

        if (! empty($schedule['startsAt'])) {
            $start = new \DateTimeImmutable((string) $schedule['startsAt']);
            if ($now < $start) {
                return false;
            }
        }

        if (! empty($schedule['endsAt'])) {
            $end = new \DateTimeImmutable((string) $schedule['endsAt']);
            if ($now > $end) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{label: string, href: string}
     */
    private static function normalizeChip(mixed $chip): array
    {
        if (is_string($chip)) {
            $label = trim($chip);

            return [
                'label' => $label,
                'href' => $label !== '' ? '/products?search='.rawurlencode($label) : '/products',
            ];
        }

        if (is_array($chip)) {
            $label = trim((string) ($chip['label'] ?? ''));
            $href = trim((string) ($chip['href'] ?? ''));

            return [
                'label' => $label,
                'href' => $href !== '' ? $href : ($label !== '' ? '/products?search='.rawurlencode($label) : '/products'),
            ];
        }

        return ['label' => '', 'href' => '/products'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizePromoBanner(mixed $banner, int $index): array
    {
        $item = is_array($banner) ? $banner : [];
        $bgRaw = trim((string) ($item['bgColor'] ?? ''));
        $defaultBg = HomepageDefaults::PROMO_BANNER_BG_COLORS[$index % count(HomepageDefaults::PROMO_BANNER_BG_COLORS)];

        return [
            'title' => (string) ($item['title'] ?? ''),
            'subtitle' => (string) ($item['subtitle'] ?? ''),
            'ctaLabel' => (string) ($item['ctaLabel'] ?? 'Shop Now'),
            'ctaHref' => (string) ($item['ctaHref'] ?? '/products'),
            'imageUrl' => ! empty($item['imageUrl']) ? (string) $item['imageUrl'] : null,
            'bgColor' => preg_match('/^#[0-9a-fA-F]{6}$/', $bgRaw) ? $bgRaw : $defaultBg,
        ];
    }

    /**
     * @return array{overlayColor: string, overlayOpacity: int}
     */
    private static function normalizeOverlay(mixed $value, int $defaultOpacity): array
    {
        $raw = is_array($value) ? $value : [];
        $colorRaw = trim((string) ($raw['overlayColor'] ?? ''));
        $opacityRaw = $raw['overlayOpacity'] ?? $defaultOpacity;

        return [
            'overlayColor' => preg_match('/^#[0-9a-fA-F]{6}$/', $colorRaw)
                ? $colorRaw
                : HomepageDefaults::DEFAULT_OVERLAY_COLOR,
            'overlayOpacity' => min(100, max(0, (int) $opacityRaw)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeHeroTopCarouselSlide(mixed $slide, int $index): array
    {
        $value = is_array($slide) ? $slide : [];

        return [
            'id' => (string) ($value['id'] ?? 'hero-top-slide-'.($index + 1)),
            'imageUrl' => ! empty($value['imageUrl']) ? (string) $value['imageUrl'] : '',
            'href' => (string) ($value['href'] ?? '/products'),
            'alt' => (string) ($value['alt'] ?? ''),
            'sortOrder' => (int) ($value['sortOrder'] ?? $index),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeHeroTopCarousel(mixed $value): array
    {
        $raw = is_array($value) ? $value : [];
        $defaults = HomepageDefaults::heroTopCarousel();
        $slides = is_array($raw['slides'] ?? null)
            ? array_map(
                static fn ($slide, $index) => self::normalizeHeroTopCarouselSlide($slide, (int) $index),
                $raw['slides'],
                array_keys($raw['slides']),
            )
            : $defaults['slides'];

        usort($slides, static fn ($a, $b) => $a['sortOrder'] <=> $b['sortOrder']);

        return [
            'autoplayMs' => min(30000, max(2000, (int) ($raw['autoplayMs'] ?? $defaults['autoplayMs']))),
            'slides' => $slides,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeCollectionBlock(mixed $block): array
    {
        $value = is_array($block) ? $block : [];
        $overlay = self::normalizeOverlay($value, HomepageDefaults::DEFAULT_COLLECTION_OVERLAY_OPACITY);

        return [
            'tag' => (string) ($value['tag'] ?? ''),
            'title' => (string) ($value['title'] ?? ''),
            'description' => (string) ($value['description'] ?? ''),
            'ctaLabel' => (string) ($value['ctaLabel'] ?? 'Shop Now'),
            'ctaHref' => (string) ($value['ctaHref'] ?? '/products'),
            'collectionSlug' => ! empty($value['collectionSlug']) ? (string) $value['collectionSlug'] : null,
            'imageUrl' => ! empty($value['imageUrl']) ? (string) $value['imageUrl'] : null,
            'overlayColor' => $overlay['overlayColor'],
            'overlayOpacity' => $overlay['overlayOpacity'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeProductRow(mixed $row, int $index): array
    {
        $value = is_array($row) ? $row : [];
        $source = (string) ($value['source'] ?? 'on-sale');
        if ($source === 'room') {
            $source = 'category';
        }
        $allowed = ['on-sale', 'new-arrivals', 'featured', 'best-sellers', 'collection', 'category', 'bundles'];

        return [
            'id' => (string) ($value['id'] ?? 'row-'.($index + 1)),
            'enabled' => ($value['enabled'] ?? true) !== false,
            'title' => (string) ($value['title'] ?? 'Featured products'),
            'subtitle' => ! empty($value['subtitle']) ? (string) $value['subtitle'] : '',
            'source' => in_array($source, $allowed, true) ? $source : 'on-sale',
            'sourceSlug' => ! empty($value['sourceSlug']) ? (string) $value['sourceSlug'] : '',
            'limit' => min(12, max(1, (int) ($value['limit'] ?? 4))),
            'viewAllHref' => (string) ($value['viewAllHref'] ?? '/products'),
            'startsAt' => ! empty($value['startsAt']) ? (string) $value['startsAt'] : null,
            'endsAt' => ! empty($value['endsAt']) ? (string) $value['endsAt'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeShopCategory(mixed $item, int $index): array
    {
        $value = is_array($item) ? $item : [];
        $linkType = (string) ($value['linkType'] ?? 'category') === 'collection' ? 'collection' : 'category';

        return [
            'id' => (string) ($value['id'] ?? 'shop-card-'.($index + 1)),
            'enabled' => ($value['enabled'] ?? true) !== false,
            'linkType' => $linkType,
            'categorySlug' => ! empty($value['categorySlug']) ? (string) $value['categorySlug'] : '',
            'collectionSlug' => ! empty($value['collectionSlug']) ? (string) $value['collectionSlug'] : '',
            'title' => ! empty($value['title']) ? (string) $value['title'] : '',
            'imageUrl' => ! empty($value['imageUrl']) ? (string) $value['imageUrl'] : null,
            'sortOrder' => (int) ($value['sortOrder'] ?? $index),
            'startsAt' => ! empty($value['startsAt']) ? (string) $value['startsAt'] : null,
            'endsAt' => ! empty($value['endsAt']) ? (string) $value['endsAt'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeShopBySection(mixed $value): array
    {
        $raw = is_array($value) ? $value : [];
        $defaults = HomepageDefaults::shopBySection();
        $mode = (string) ($raw['mode'] ?? $defaults['mode']) === 'manual' ? 'manual' : 'auto';

        return [
            'mode' => $mode,
            'title' => (string) ($raw['title'] ?? $defaults['title']),
            'viewAllLabel' => (string) ($raw['viewAllLabel'] ?? $defaults['viewAllLabel']),
            'viewAllHref' => (string) ($raw['viewAllHref'] ?? $defaults['viewAllHref']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeCategoryIconScroll(mixed $value): array
    {
        $raw = is_array($value) ? $value : [];
        $defaults = HomepageDefaults::categoryIconScroll();
        $mode = (string) ($raw['mode'] ?? $defaults['mode']) === 'manual' ? 'manual' : 'auto';
        $categorySlugs = is_array($raw['categorySlugs'] ?? null)
            ? array_values(array_filter(array_map(static fn ($slug) => trim((string) $slug), $raw['categorySlugs'])))
            : $defaults['categorySlugs'];

        return [
            'mode' => $mode,
            'categorySlugs' => $categorySlugs,
            'limit' => min(12, max(1, (int) ($raw['limit'] ?? $defaults['limit']))),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function normalizeShopCategories(mixed $value): array
    {
        if (is_array($value) && $value !== []) {
            $categories = array_map(
                static fn ($item, $index) => self::normalizeShopCategory($item, (int) $index),
                $value,
                array_keys($value),
            );
            usort($categories, static fn ($a, $b) => $a['sortOrder'] <=> $b['sortOrder']);

            return $categories;
        }

        return [];
    }

    /**
     * @return array{enabled: bool, startsAt: string|null, endsAt: string|null}
     */
    private static function normalizeSectionSchedule(mixed $value, array $fallback): array
    {
        if (! is_array($value)) {
            return $fallback;
        }

        return [
            'enabled' => ($value['enabled'] ?? true) !== false,
            'startsAt' => ! empty($value['startsAt']) ? (string) $value['startsAt'] : null,
            'endsAt' => ! empty($value['endsAt']) ? (string) $value['endsAt'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeSaleCountdown(array $value, array $defaults): array
    {
        $rawSubtitle = (string) ($value['subtitle'] ?? $defaults['subtitle']);
        $rawHeadline = ! empty($value['headline']) ? (string) $value['headline'] : '';
        $splitIndex = strpos($rawSubtitle, ' — ');

        $headline = $rawHeadline;
        $subtitle = $rawSubtitle;

        if ($headline === '' && $splitIndex !== false && $splitIndex > 0) {
            $headline = substr($rawSubtitle, 0, $splitIndex);
            $subtitle = substr($rawSubtitle, $splitIndex + 3);
        }

        if ($headline === '') {
            $headline = $defaults['headline'];
        }

        $overlay = self::normalizeOverlay($value, HomepageDefaults::DEFAULT_SALE_COUNTDOWN_OVERLAY_OPACITY);
        $overlayColorRaw = trim((string) ($value['overlayColor'] ?? ''));
        $overlayColor = preg_match('/^#[0-9a-fA-F]{6}$/', $overlayColorRaw)
            ? $overlayColorRaw
            : HomepageDefaults::DEFAULT_SALE_COUNTDOWN_OVERLAY_COLOR;

        return [
            'title' => (string) ($value['title'] ?? $defaults['title']),
            'headline' => $headline,
            'subtitle' => $subtitle,
            'ctaLabel' => (string) ($value['ctaLabel'] ?? $defaults['ctaLabel']),
            'ctaHref' => (string) ($value['ctaHref'] ?? $defaults['ctaHref']),
            'endsAt' => (string) ($value['endsAt'] ?? $defaults['endsAt']),
            'imageUrl' => ! empty($value['imageUrl']) ? (string) $value['imageUrl'] : null,
            'overlayColor' => $overlayColor,
            'overlayOpacity' => $overlay['overlayOpacity'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function normalizeHomepageContent(mixed $raw): array
    {
        $value = is_array($raw) ? $raw : [];
        $defaults = HomepageDefaults::content();
        $heroRaw = is_array($value['hero'] ?? null) ? $value['hero'] : [];
        $countdownRaw = is_array($value['saleCountdown'] ?? null) ? $value['saleCountdown'] : [];
        $visibilityRaw = is_array($value['sectionVisibility'] ?? null) ? $value['sectionVisibility'] : [];

        $heroOverlay = self::normalizeOverlay($heroRaw, HomepageDefaults::DEFAULT_HERO_OVERLAY_OPACITY);

        $hero = [
            'eyebrow' => (string) ($heroRaw['eyebrow'] ?? $defaults['hero']['eyebrow']),
            'title' => (string) ($heroRaw['title'] ?? $defaults['hero']['title']),
            'subtitle' => (string) ($heroRaw['subtitle'] ?? $defaults['hero']['subtitle']),
            'ctaLabel' => (string) ($heroRaw['ctaLabel'] ?? $defaults['hero']['ctaLabel']),
            'ctaHref' => (string) ($heroRaw['ctaHref'] ?? $defaults['hero']['ctaHref']),
            'imageUrl' => ! empty($heroRaw['imageUrl']) ? (string) $heroRaw['imageUrl'] : null,
            'overlayColor' => $heroOverlay['overlayColor'],
            'overlayOpacity' => $heroOverlay['overlayOpacity'],
            'chips' => is_array($heroRaw['chips'] ?? null)
                ? array_values(array_filter(
                    array_map([self::class, 'normalizeChip'], $heroRaw['chips']),
                    static fn ($chip) => $chip['label'] !== '',
                ))
                : $defaults['hero']['chips'],
        ];

        $sectionVisibilityDefaults = HomepageDefaults::sectionVisibility();
        $sectionVisibility = [
            'hero' => self::normalizeSectionSchedule($visibilityRaw['hero'] ?? null, $sectionVisibilityDefaults['hero']),
            'heroTopCarousel' => self::normalizeSectionSchedule(
                $visibilityRaw['heroTopCarousel'] ?? null,
                $sectionVisibilityDefaults['heroTopCarousel'],
            ),
            'collectionBlocks' => self::normalizeSectionSchedule($visibilityRaw['collectionBlocks'] ?? null, $sectionVisibilityDefaults['collectionBlocks']),
            'serviceHighlights' => self::normalizeSectionSchedule($visibilityRaw['serviceHighlights'] ?? null, $sectionVisibilityDefaults['serviceHighlights']),
            'categoryIconScroll' => self::normalizeSectionSchedule($visibilityRaw['categoryIconScroll'] ?? null, $sectionVisibilityDefaults['categoryIconScroll']),
            'productRows' => self::normalizeSectionSchedule($visibilityRaw['productRows'] ?? null, $sectionVisibilityDefaults['productRows']),
            'promoBanners' => self::normalizeSectionSchedule($visibilityRaw['promoBanners'] ?? null, $sectionVisibilityDefaults['promoBanners']),
            'saleCountdown' => self::normalizeSectionSchedule($visibilityRaw['saleCountdown'] ?? null, $sectionVisibilityDefaults['saleCountdown']),
            'shopByCategory' => self::normalizeSectionSchedule(
                $visibilityRaw['shopByCategory'] ?? $visibilityRaw['shopByRoom'] ?? null,
                $sectionVisibilityDefaults['shopByCategory'],
            ),
        ];

        $productRows = is_array($value['productRows'] ?? null)
            ? array_map(static fn ($row, $index) => self::normalizeProductRow($row, (int) $index), $value['productRows'], array_keys($value['productRows']))
            : $defaults['productRows'];

        $shopCategories = self::normalizeShopCategories($value['shopCategories'] ?? null);
        $shopBySection = self::normalizeShopBySection($value['shopBySection'] ?? null);

        return [
            'promoBar' => (string) ($value['promoBar'] ?? $defaults['promoBar']),
            'phone' => (string) ($value['phone'] ?? $defaults['phone']),
            'hero' => $hero,
            'heroTopCarousel' => self::normalizeHeroTopCarousel($value['heroTopCarousel'] ?? null),
            'collections' => is_array($value['collections'] ?? null)
                ? array_map([self::class, 'normalizeCollectionBlock'], $value['collections'])
                : $defaults['collections'],
            'promoBanners' => is_array($value['promoBanners'] ?? null)
                ? array_map(static fn ($banner, $index) => self::normalizePromoBanner($banner, (int) $index), $value['promoBanners'], array_keys($value['promoBanners']))
                : $defaults['promoBanners'],
            'saleCountdown' => self::normalizeSaleCountdown($countdownRaw, $defaults['saleCountdown']),
            'serviceHighlights' => is_array($value['serviceHighlights'] ?? null)
                ? array_map(static function ($item) {
                    $highlight = is_array($item) ? $item : [];

                    return [
                        'title' => (string) ($highlight['title'] ?? ''),
                        'description' => (string) ($highlight['description'] ?? ''),
                    ];
                }, $value['serviceHighlights'])
                : $defaults['serviceHighlights'],
            'shopBySection' => $shopBySection,
            'shopCategories' => $shopCategories,
            'categoryIconScroll' => self::normalizeCategoryIconScroll($value['categoryIconScroll'] ?? null),
            'sectionVisibility' => $sectionVisibility,
            'productRows' => $productRows,
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function isSaleCountdownVisible(array $content, ?\DateTimeInterface $now = null): bool
    {
        $now = $now ?? now();

        if (! self::isScheduleActive($content['sectionVisibility']['saleCountdown'] ?? [], $now)) {
            return false;
        }

        $endsAt = new \DateTimeImmutable((string) ($content['saleCountdown']['endsAt'] ?? ''));

        if ($endsAt->getTimestamp() === (new \DateTimeImmutable('@0'))->getTimestamp()) {
            return true;
        }

        return $endsAt->getTimestamp() > $now->getTimestamp();
    }
}
