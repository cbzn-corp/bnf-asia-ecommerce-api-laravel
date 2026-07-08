<?php

declare(strict_types=1);

namespace App\Support\Content;

final class HomepageDefaults
{
    public const PROMO_BANNER_BG_COLORS = ['#3d2b1f', '#2d4a3e', '#1a2332'];

    public const DEFAULT_OVERLAY_COLOR = '#000000';

    public const DEFAULT_HERO_OVERLAY_OPACITY = 70;

    public const DEFAULT_COLLECTION_OVERLAY_OPACITY = 75;

    public const DEFAULT_SALE_COUNTDOWN_OVERLAY_COLOR = '#8b0000';

    public const DEFAULT_SALE_COUNTDOWN_OVERLAY_OPACITY = 80;

    public const DEFAULT_HERO_TOP_CAROUSEL_AUTOPLAY_MS = 5000;

    /**
     * @return array<string, mixed>
     */
    public static function heroTopCarousel(): array
    {
        return [
            'autoplayMs' => self::DEFAULT_HERO_TOP_CAROUSEL_AUTOPLAY_MS,
            'slides' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sectionVisibility(): array
    {
        $schedule = ['enabled' => true, 'startsAt' => null, 'endsAt' => null];

        return [
            'hero' => $schedule,
            'heroTopCarousel' => $schedule,
            'collectionBlocks' => $schedule,
            'serviceHighlights' => $schedule,
            'categoryIconScroll' => $schedule,
            'productRows' => $schedule,
            'promoBanners' => $schedule,
            'saleCountdown' => $schedule,
            'shopByCategory' => $schedule,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function categoryIconScroll(): array
    {
        return [
            'mode' => 'auto',
            'categorySlugs' => [],
            'limit' => 8,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function shopBySection(): array
    {
        return [
            'mode' => 'auto',
            'title' => 'Shop by Category',
            'viewAllLabel' => 'See all categories →',
            'viewAllHref' => '/products',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function productRows(): array
    {
        return [
            [
                'id' => 'deals',
                'enabled' => true,
                'title' => "Today's Best Deals",
                'subtitle' => 'Best sellers & limited-time offers',
                'source' => 'on-sale',
                'sourceSlug' => '',
                'limit' => 4,
                'viewAllHref' => '/products?deals=on-sale',
                'startsAt' => null,
                'endsAt' => null,
            ],
            [
                'id' => 'new-arrivals',
                'enabled' => true,
                'title' => 'New Arrivals',
                'subtitle' => '',
                'source' => 'new-arrivals',
                'sourceSlug' => '',
                'limit' => 4,
                'viewAllHref' => '/products?deals=new-arrivals',
                'startsAt' => null,
                'endsAt' => null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function content(): array
    {
        return [
            'promoBar' => 'FREE DELIVERY NATIONWIDE | 5-YEAR WARRANTY | EASY 0% INSTALLMENT',
            'phone' => '+63 2 8888 0000',
            'hero' => [
                'eyebrow' => 'Mid-Year Sale — Up to 30% Off',
                'title' => 'Premium Metal Furniture for Every Room',
                'subtitle' => 'Shop wardrobes, sofas, dining sets, beds, and more — built to last.',
                'ctaLabel' => 'Shop the Sale',
                'ctaHref' => '/products?deals=on-sale',
                'imageUrl' => null,
                'overlayColor' => self::DEFAULT_OVERLAY_COLOR,
                'overlayOpacity' => self::DEFAULT_HERO_OVERLAY_OPACITY,
                'chips' => [
                    ['label' => 'Beds & Frames', 'href' => '/products?search=Beds%20%26%20Frames'],
                    ['label' => 'TV Cabinets', 'href' => '/products?search=TV%20Cabinets'],
                    ['label' => 'Tables', 'href' => '/products?search=Tables'],
                    ['label' => 'Sale', 'href' => '/products?deals=on-sale'],
                ],
            ],
            'heroTopCarousel' => self::heroTopCarousel(),
            'collections' => [
                [
                    'tag' => 'New Collection',
                    'title' => 'Metal Wardrobes',
                    'description' => 'Spacious storage with heavy-gauge steel frames.',
                    'ctaLabel' => 'Shop Now',
                    'ctaHref' => '/products?category=wardrobes',
                    'collectionSlug' => null,
                    'imageUrl' => null,
                    'overlayColor' => self::DEFAULT_OVERLAY_COLOR,
                    'overlayOpacity' => self::DEFAULT_COLLECTION_OVERLAY_OPACITY,
                ],
                [
                    'tag' => 'Outdoor Living',
                    'title' => 'Garden & Patio Sets',
                    'description' => 'Weather-resistant sets for balconies and gardens.',
                    'ctaLabel' => 'Shop Now',
                    'ctaHref' => '/products?category=outdoor-furniture',
                    'collectionSlug' => null,
                    'imageUrl' => null,
                    'overlayColor' => self::DEFAULT_OVERLAY_COLOR,
                    'overlayOpacity' => self::DEFAULT_COLLECTION_OVERLAY_OPACITY,
                ],
            ],
            'promoBanners' => [
                [
                    'title' => 'Bedroom Sale',
                    'subtitle' => 'Wardrobes, beds & cabinets up to 25% off',
                    'ctaLabel' => 'Shop Now',
                    'ctaHref' => '/products?category=bedroom&deals=on-sale',
                    'imageUrl' => null,
                    'bgColor' => self::PROMO_BANNER_BG_COLORS[0],
                ],
                [
                    'title' => 'Sofa & Recliner',
                    'subtitle' => 'New arrivals — electric recliners from ₱18,900',
                    'ctaLabel' => 'Shop Now',
                    'ctaHref' => '/products?category=sofas',
                    'imageUrl' => null,
                    'bgColor' => self::PROMO_BANNER_BG_COLORS[1],
                ],
                [
                    'title' => 'Dining Packages',
                    'subtitle' => 'Complete sets from ₱12,500 — 4 & 6-seater',
                    'ctaLabel' => 'Shop Now',
                    'ctaHref' => '/products?category=dining-sets',
                    'imageUrl' => null,
                    'bgColor' => self::PROMO_BANNER_BG_COLORS[2],
                ],
            ],
            'saleCountdown' => [
                'title' => 'Mid-Year Clearance',
                'headline' => 'Up to 30% OFF',
                'subtitle' => 'Selected items — limited stocks only',
                'ctaLabel' => 'Shop the Sale',
                'ctaHref' => '/products?deals=on-sale',
                'endsAt' => now()->addDays(14)->toIso8601String(),
                'imageUrl' => null,
                'overlayColor' => self::DEFAULT_SALE_COUNTDOWN_OVERLAY_COLOR,
                'overlayOpacity' => self::DEFAULT_SALE_COUNTDOWN_OVERLAY_OPACITY,
            ],
            'serviceHighlights' => [
                ['title' => 'Free Delivery', 'description' => 'On orders over ₱50,000'],
                ['title' => '5-Year Warranty', 'description' => 'All metal frame products'],
                ['title' => 'Easy Returns', 'description' => '30-day return policy'],
                ['title' => '0% Installment', 'description' => 'Up to 24 months'],
            ],
            'shopBySection' => self::shopBySection(),
            'shopCategories' => [],
            'categoryIconScroll' => self::categoryIconScroll(),
            'sectionVisibility' => self::sectionVisibility(),
            'productRows' => self::productRows(),
        ];
    }
}
