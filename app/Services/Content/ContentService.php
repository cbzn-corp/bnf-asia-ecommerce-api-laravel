<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\ContentBlock;
use App\Services\Categories\CategoriesService;
use App\Services\Settings\PlatformSettingsService;
use App\Services\Supabase\SupabaseService;
use App\Support\Cache\ApiCache;
use App\Support\Config\AppUrls;
use App\Support\Content\ContentPagesRegistry;
use App\Support\Content\HomepageDefaults;
use App\Support\Content\HomepageUtils;
use App\Support\Content\StorefrontDefaults;
use App\Support\Content\StorefrontUtils;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContentService
{
    private const HOMEPAGE_KEY = 'homepage';

    private const STOREFRONT_SETTINGS_KEY = 'storefront-settings';

    public function __construct(
        private readonly SupabaseService $supabase,
        private readonly PlatformSettingsService $platformSettings,
        private readonly CategoriesService $categories,
        private readonly HomepageProductRowsResolver $homepageProductRows,
    ) {}

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array{settings: array<string, mixed>, platform: array<string, mixed>, categoryTree: list<array<string, mixed>>}
     */
    public function getStorefrontShell(): array
    {
        return [
            'settings' => $this->getStorefrontSettings(),
            'platform' => $this->platformSettings->getPublicSummary(),
            'categoryTree' => $this->categories->findTreePublic(),
        ];
    }

    /**
     * @return array{content: array<string, mixed>, productRows: list<array{row: array<string, mixed>, data: array<string, mixed>}>}
     */
    public function getHomepageRendered(): array
    {
        $content = $this->getHomepage();

        return [
            'content' => $content,
            'productRows' => $this->homepageProductRows->resolve($content),
        ];
    }

    public function getHomepage(): array
    {
        return ApiCache::remember(ApiCache::DOMAIN_CONTENT, 'homepage', function () {
            $block = ContentBlock::query()->find(self::HOMEPAGE_KEY);

            if (! $block) {
                return HomepageUtils::normalizeHomepageContent(HomepageDefaults::content());
            }

            return HomepageUtils::normalizeHomepageContent($block->value);
        });
    }

    /**
     * @param  array<string, mixed>  $dto
     * @return array<string, mixed>
     */
    public function updateHomepage(array $dto): array
    {
        $current = $this->getHomepage();
        $merged = HomepageUtils::normalizeHomepageContent([...$current, ...$dto]);
        $this->persistBlock(self::HOMEPAGE_KEY, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStorefrontSettings(): array
    {
        return ApiCache::remember(ApiCache::DOMAIN_CONTENT, 'storefront-settings', function () {
            $block = ContentBlock::query()->find(self::STOREFRONT_SETTINGS_KEY);

            if (! $block) {
                return StorefrontUtils::normalizeStorefrontSettings(null);
            }

            return StorefrontUtils::normalizeStorefrontSettings($block->value);
        });
    }

    /**
     * @param  array<string, mixed>  $dto
     * @return array<string, mixed>
     */
    public function updateStorefrontSettings(array $dto): array
    {
        $current = $this->getStorefrontSettings();

        // Clear optional PDP copy when the admin sends blank text.
        if (array_key_exists('pdpFreeShippingNote', $dto)) {
            $dto['pdpFreeShippingNote'] = trim((string) ($dto['pdpFreeShippingNote'] ?? '')) ?: null;
        }

        $merged = StorefrontUtils::normalizeStorefrontSettings([
            ...$current,
            ...$dto,
            'seo' => isset($dto['seo']) ? [...$current['seo'], ...$dto['seo']] : $current['seo'],
            'analytics' => isset($dto['analytics']) ? [...$current['analytics'], ...$dto['analytics']] : $current['analytics'],
            'listingPages' => isset($dto['listingPages']) ? [
                'products' => isset($dto['listingPages']['products'])
                    ? [...$current['listingPages']['products'], ...$dto['listingPages']['products']]
                    : $current['listingPages']['products'],
                'collections' => isset($dto['listingPages']['collections'])
                    ? [...$current['listingPages']['collections'], ...$dto['listingPages']['collections']]
                    : $current['listingPages']['collections'],
                'bundles' => isset($dto['listingPages']['bundles'])
                    ? [...$current['listingPages']['bundles'], ...$dto['listingPages']['bundles']]
                    : $current['listingPages']['bundles'],
                'sale' => isset($dto['listingPages']['sale'])
                    ? [...$current['listingPages']['sale'], ...$dto['listingPages']['sale']]
                    : $current['listingPages']['sale'],
            ] : $current['listingPages'],
            'pageCopy' => isset($dto['pageCopy']) ? [...$current['pageCopy'], ...$dto['pageCopy']] : $current['pageCopy'],
        ]);

        $this->persistBlock(self::STOREFRONT_SETTINGS_KEY, $merged);

        return $merged;
    }

    public function validateMaintenanceBypassKey(string $key): bool
    {
        return $this->platformSettings->validateMaintenanceBypassKey($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadStorefrontAsset(string $asset, UploadedFile $file): array
    {
        $url = $this->supabase->uploadStorefrontAsset($asset, $file);
        $current = $this->getStorefrontSettings();

        $merged = match ($asset) {
            'logo' => StorefrontUtils::normalizeStorefrontSettings([...$current, 'logoUrl' => $url]),
            'favicon' => StorefrontUtils::normalizeStorefrontSettings([...$current, 'faviconUrl' => $url]),
            'og-image' => StorefrontUtils::normalizeStorefrontSettings([
                ...$current,
                'seo' => [...$current['seo'], 'ogImageUrl' => $url],
            ]),
            default => throw new BadRequestHttpException('Asset must be logo, favicon, or og-image'),
        };

        $this->persistBlock(self::STOREFRONT_SETTINGS_KEY, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function removeStorefrontAsset(string $asset): array
    {
        $current = $this->getStorefrontSettings();

        $merged = match ($asset) {
            'logo' => StorefrontUtils::normalizeStorefrontSettings([...$current, 'logoUrl' => null]),
            'favicon' => StorefrontUtils::normalizeStorefrontSettings([...$current, 'faviconUrl' => null]),
            'og-image' => StorefrontUtils::normalizeStorefrontSettings([
                ...$current,
                'seo' => [...$current['seo'], 'ogImageUrl' => null],
            ]),
            default => throw new BadRequestHttpException('Asset must be logo, favicon, or og-image'),
        };

        $this->persistBlock(self::STOREFRONT_SETTINGS_KEY, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadHomepageHeroTopCarouselImage(int $index, UploadedFile $file): array
    {
        $current = $this->getHomepage();
        if ($index < 0 || $index >= count($current['heroTopCarousel']['slides'])) {
            throw new BadRequestHttpException('Invalid hero top carousel slide index');
        }

        $url = $this->supabase->uploadHomepageHeroTopCarouselImage($index, $file);
        $slides = $current['heroTopCarousel']['slides'];
        $slides[$index] = [...$slides[$index], 'imageUrl' => $url];
        $merged = HomepageUtils::normalizeHomepageContent([
            ...$current,
            'heroTopCarousel' => [...$current['heroTopCarousel'], 'slides' => $slides],
        ]);
        $this->persistBlock(self::HOMEPAGE_KEY, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function removeHomepageHeroTopCarouselImage(int $index): array
    {
        $current = $this->getHomepage();
        if ($index < 0 || $index >= count($current['heroTopCarousel']['slides'])) {
            throw new BadRequestHttpException('Invalid hero top carousel slide index');
        }

        $slides = $current['heroTopCarousel']['slides'];
        $slides[$index] = [...$slides[$index], 'imageUrl' => ''];
        $merged = HomepageUtils::normalizeHomepageContent([
            ...$current,
            'heroTopCarousel' => [...$current['heroTopCarousel'], 'slides' => $slides],
        ]);
        $this->persistBlock(self::HOMEPAGE_KEY, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadHomepageHeroImage(UploadedFile $file): array
    {
        $url = $this->supabase->uploadHomepageHeroImage($file);
        $current = $this->getHomepage();
        $merged = HomepageUtils::normalizeHomepageContent([
            ...$current,
            'hero' => [...$current['hero'], 'imageUrl' => $url],
        ]);
        $this->persistBlock(self::HOMEPAGE_KEY, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function removeHomepageHeroImage(): array
    {
        $current = $this->getHomepage();
        $merged = HomepageUtils::normalizeHomepageContent([
            ...$current,
            'hero' => [...$current['hero'], 'imageUrl' => null],
        ]);
        $this->persistBlock(self::HOMEPAGE_KEY, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadHomepageCollectionImage(int $index, UploadedFile $file): array
    {
        $current = $this->getHomepage();
        if ($index < 0 || $index >= count($current['collections'])) {
            throw new BadRequestHttpException('Invalid collection block index');
        }

        $url = $this->supabase->uploadHomepageCollectionImage($index, $file);
        $collections = $current['collections'];
        $collections[$index] = [...$collections[$index], 'imageUrl' => $url];
        $merged = HomepageUtils::normalizeHomepageContent([...$current, 'collections' => $collections]);
        $this->persistBlock(self::HOMEPAGE_KEY, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function removeHomepageCollectionImage(int $index): array
    {
        $current = $this->getHomepage();
        if ($index < 0 || $index >= count($current['collections'])) {
            throw new BadRequestHttpException('Invalid collection block index');
        }

        $collections = $current['collections'];
        $collections[$index] = [...$collections[$index], 'imageUrl' => null];
        $merged = HomepageUtils::normalizeHomepageContent([...$current, 'collections' => $collections]);
        $this->persistBlock(self::HOMEPAGE_KEY, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadHomepagePromoBannerImage(int $index, UploadedFile $file): array
    {
        $current = $this->getHomepage();
        if ($index < 0 || $index >= count($current['promoBanners'])) {
            throw new BadRequestHttpException('Invalid promo banner index');
        }

        $url = $this->supabase->uploadHomepagePromoBannerImage($index, $file);
        $promoBanners = $current['promoBanners'];
        $promoBanners[$index] = [...$promoBanners[$index], 'imageUrl' => $url];
        $merged = HomepageUtils::normalizeHomepageContent([...$current, 'promoBanners' => $promoBanners]);
        $this->persistBlock(self::HOMEPAGE_KEY, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function removeHomepagePromoBannerImage(int $index): array
    {
        $current = $this->getHomepage();
        if ($index < 0 || $index >= count($current['promoBanners'])) {
            throw new BadRequestHttpException('Invalid promo banner index');
        }

        $promoBanners = $current['promoBanners'];
        $promoBanners[$index] = [...$promoBanners[$index], 'imageUrl' => null];
        $merged = HomepageUtils::normalizeHomepageContent([...$current, 'promoBanners' => $promoBanners]);
        $this->persistBlock(self::HOMEPAGE_KEY, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadHomepageSaleCountdownImage(UploadedFile $file): array
    {
        $url = $this->supabase->uploadHomepageSaleCountdownImage($file);
        $current = $this->getHomepage();
        $merged = HomepageUtils::normalizeHomepageContent([
            ...$current,
            'saleCountdown' => [...$current['saleCountdown'], 'imageUrl' => $url],
        ]);
        $this->persistBlock(self::HOMEPAGE_KEY, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function removeHomepageSaleCountdownImage(): array
    {
        $current = $this->getHomepage();
        $merged = HomepageUtils::normalizeHomepageContent([
            ...$current,
            'saleCountdown' => [...$current['saleCountdown'], 'imageUrl' => null],
        ]);
        $this->persistBlock(self::HOMEPAGE_KEY, $merged);

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStaticPage(string $slug): array
    {
        return ApiCache::remember(ApiCache::DOMAIN_CONTENT, 'page:'.rawurlencode($slug), function () use ($slug) {
            $key = StorefrontUtils::staticPageKeyFromSlug($slug);
            if (! $key) {
                throw new NotFoundHttpException("Page not found: {$slug}");
            }

            $block = ContentBlock::query()->find($key);
            $defaults = StorefrontDefaults::staticPages()[$key] ?? [
                'title' => str_replace('-', ' ', ucwords(str_replace('-', ' ', $slug))),
                'breadcrumb' => str_replace('-', ' ', ucwords(str_replace('-', ' ', $slug))),
                'sections' => [['body' => '']],
            ];

            if (! $block) {
                return StorefrontUtils::normalizeStaticPage($defaults, $key);
            }

            return StorefrontUtils::normalizeStaticPage($block->value, $key);
        });
    }

    public function getAllStaticPages(): array
    {
        $builtIn = array_map(static function (string $key) {
            $slug = str_replace('page-', '', $key);
            $defaults = StorefrontDefaults::staticPages()[$key];
            $block = ContentBlock::query()->find($key);

            return [
                'key' => $key,
                'slug' => $slug,
                'path' => ContentPagesRegistry::pagePath($slug),
                'label' => $defaults['title'] ?? $slug,
                'description' => '',
                'isBuiltIn' => true,
                'content' => StorefrontUtils::normalizeStaticPage(
                    $block?->value ?? $defaults,
                    $key,
                ),
            ];
        }, StorefrontDefaults::STATIC_PAGE_KEYS);

        $custom = array_map(static function (array $meta) {
            $key = ContentPagesRegistry::pageKey($meta['slug']);
            $block = ContentBlock::query()->find($key);
            $defaults = [
                'title' => $meta['label'],
                'breadcrumb' => $meta['label'],
                'sections' => [['body' => '']],
            ];

            return [
                'key' => $key,
                'slug' => $meta['slug'],
                'path' => ContentPagesRegistry::pagePath($meta['slug']),
                'label' => $meta['label'],
                'description' => $meta['description'],
                'isBuiltIn' => false,
                'content' => StorefrontUtils::normalizeStaticPage(
                    $block?->value ?? $defaults,
                    $key,
                ),
            ];
        }, ContentPagesRegistry::customPages());

        return [...$builtIn, ...$custom];
    }

    /**
     * @return list<array{slug: string, path: string}>
     */
    public function getContentPagePaths(): array
    {
        return array_map(
            static fn (string $slug) => [
                'slug' => $slug,
                'path' => ContentPagesRegistry::pagePath($slug),
            ],
            ContentPagesRegistry::allSlugs(),
        );
    }

    /**
     * @param  array{slug: string, label: string, description?: string, title?: string, breadcrumb?: string}  $dto
     * @return array<string, mixed>
     */
    public function createStaticPage(array $dto): array
    {
        try {
            ContentPagesRegistry::addPage([
                'slug' => $dto['slug'],
                'label' => $dto['label'],
                'description' => $dto['description'] ?? '',
            ]);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        $key = ContentPagesRegistry::pageKey($dto['slug']);
        $content = StorefrontUtils::normalizeStaticPage([
            'title' => $dto['title'] ?? $dto['label'],
            'breadcrumb' => $dto['breadcrumb'] ?? $dto['label'],
            'sections' => [['body' => '']],
        ], $key);

        $this->persistBlock($key, $content);
        ApiCache::bump(ApiCache::DOMAIN_CONTENT);

        return [
            'key' => $key,
            'slug' => $dto['slug'],
            'path' => ContentPagesRegistry::pagePath($dto['slug']),
            'label' => $dto['label'],
            'description' => $dto['description'] ?? '',
            'isBuiltIn' => false,
            'content' => $content,
        ];
    }

    public function deleteStaticPage(string $slug): void
    {
        try {
            ContentPagesRegistry::removePage($slug);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
        ApiCache::bump(ApiCache::DOMAIN_CONTENT);
    }

    public function uploadContentPageImage(UploadedFile $file): array
    {
        $url = $this->supabase->uploadContentPageImage($file);

        return ['url' => $url];
    }

    public function uploadContentPageVideo(UploadedFile $file): array
    {
        $url = $this->supabase->uploadContentPageVideo($file);

        return ['url' => $url];
    }

    /**
     * @return array<string, mixed>
     */
    public function updateStaticPage(string $slug, array $dto): array
    {
        $key = StorefrontUtils::staticPageKeyFromSlug($slug);
        if (! $key) {
            throw new NotFoundHttpException("Page not found: {$slug}");
        }

        $current = $this->getStaticPage($slug);
        $merged = StorefrontUtils::normalizeStaticPage([...$current, ...$dto], $key);
        $this->persistBlock($key, $merged);

        return $merged;
    }

    /**
     * @return array{revalidated: bool, at: string}
     */
    public function revalidateStorefront(): array
    {
        $secret = env('STOREFRONT_REVALIDATE_SECRET');
        if (! $secret) {
            throw new BadRequestHttpException(
                'Storefront revalidation is not configured. Set STOREFRONT_REVALIDATE_SECRET on the API and storefront.',
            );
        }

        $response = Http::withHeaders([
            'x-revalidate-secret' => $secret,
            'content-type' => 'application/json',
        ])->post(AppUrls::getStorefrontUrl().'/api/revalidate');

        if (! $response->successful()) {
            $detail = $response->body();
            throw new BadRequestHttpException(
                "Storefront revalidation failed ({$response->status()})".($detail !== '' ? ": {$detail}" : ''),
            );
        }

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function persistBlock(string $key, array $value): void
    {
        ContentBlock::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );

        ApiCache::bump(ApiCache::DOMAIN_CONTENT);
    }
}
