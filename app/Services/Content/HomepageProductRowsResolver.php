<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Services\Bundles\BundlesService;
use App\Services\Collections\CollectionsService;
use App\Services\Products\ProductsService;
use App\Support\Content\HomepageUtils;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class HomepageProductRowsResolver
{
    public function __construct(
        private readonly ProductsService $products,
        private readonly CollectionsService $collections,
        private readonly BundlesService $bundles,
    ) {}

    /**
     * @param  array<string, mixed>  $content
     * @return list<array{row: array<string, mixed>, data: array<string, mixed>}>
     */
    public function resolve(array $content): array
    {
        $visibility = is_array($content['sectionVisibility'] ?? null) ? $content['sectionVisibility'] : [];
        $schedule = is_array($visibility['productRows'] ?? null) ? $visibility['productRows'] : ['enabled' => true];

        if (! HomepageUtils::isScheduleActive($schedule)) {
            return [];
        }

        $rows = is_array($content['productRows'] ?? null) ? $content['productRows'] : [];
        $resolved = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! $this->isProductRowActive($row)) {
                continue;
            }

            $data = $this->resolveRow($row);
            if ($data === null) {
                continue;
            }

            $resolved[] = ['row' => $row, 'data' => $data];
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isProductRowActive(array $row): bool
    {
        return HomepageUtils::isScheduleActive([
            'enabled' => ($row['enabled'] ?? true) !== false,
            'startsAt' => $row['startsAt'] ?? null,
            'endsAt' => $row['endsAt'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function resolveRow(array $row): ?array
    {
        $limit = min(12, max(1, (int) ($row['limit'] ?? 4)));
        $source = (string) ($row['source'] ?? 'on-sale');

        return match ($source) {
            'on-sale' => $this->resolveProducts(['deals' => 'on-sale', 'limit' => $limit]),
            'new-arrivals' => $this->resolveProducts(['deals' => 'new-arrivals', 'limit' => $limit]),
            'featured' => $this->resolveProducts(['featured' => true, 'limit' => $limit]),
            'best-sellers' => $this->resolveProducts(['deals' => 'best-sellers', 'limit' => $limit]),
            'category' => $this->resolveCategoryRow($row, $limit),
            'collection' => $this->resolveCollectionRow($row, $limit),
            'bundles' => $this->resolveBundlesRow($limit),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function resolveProducts(array $query): ?array
    {
        $result = $this->products->findAll($query);
        $products = $result['data'] ?? [];

        if ($products === []) {
            return null;
        }

        return ['type' => 'products', 'products' => $products];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveCategoryRow(array $row, int $limit): ?array
    {
        $slug = trim((string) ($row['sourceSlug'] ?? ''));
        if ($slug === '') {
            return null;
        }

        return $this->resolveProducts(['category' => $slug, 'limit' => $limit]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveCollectionRow(array $row, int $limit): ?array
    {
        $slug = trim((string) ($row['sourceSlug'] ?? ''));
        if ($slug === '') {
            return null;
        }

        try {
            $collection = $this->collections->findBySlugPublic($slug);
        } catch (NotFoundHttpException) {
            return null;
        }

        $products = array_slice($collection['products'] ?? [], 0, $limit);

        if ($products === []) {
            return null;
        }

        return ['type' => 'products', 'products' => $products];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveBundlesRow(int $limit): ?array
    {
        $bundles = array_slice($this->bundles->findAllPublic(), 0, $limit);

        if ($bundles === []) {
            return null;
        }

        return ['type' => 'bundles', 'bundles' => $bundles];
    }
}
