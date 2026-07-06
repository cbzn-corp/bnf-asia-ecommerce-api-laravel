<?php

declare(strict_types=1);

namespace App\Services\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Categories\CategoriesService;
use App\Services\Settings\PlatformSettingsService;
use App\Services\StockAlerts\StockAlertsService;
use App\Services\Supabase\SupabaseService;
use App\Support\Cache\ApiCache;
use App\Support\Utils\Slug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductsService
{
  private const DEFAULT_LOW_STOCK_THRESHOLD = 5;

  public function __construct(
    private readonly SupabaseService $supabase,
    private readonly PlatformSettingsService $platformSettings,
    private readonly StockAlertsService $stockAlerts,
    private readonly CategoriesService $categories,
  ) {}

  private function getLowStockThreshold(): int
  {
    $settings = $this->platformSettings->getRaw();

    return $settings->lowStockThreshold ?? self::DEFAULT_LOW_STOCK_THRESHOLD;
  }

  /**
   * @return array{availableQuantity: int, inStock: bool, lowStock: bool, stockLabel: string}
   */
  private function stockMeta(int $quantity, int $lowStockThreshold): array
  {
    $inStock = $quantity > 0;
    $lowStock = $inStock && $quantity <= $lowStockThreshold;

    return [
      'availableQuantity' => $quantity,
      'inStock' => $inStock,
      'lowStock' => $lowStock,
      'stockLabel' => ! $inStock ? 'Out of Stock' : ($lowStock ? 'Low Stock' : 'In Stock'),
    ];
  }

  /**
   * @param  array<string, mixed>  $query
   * @param  list<string>|null  $categoryIds
   */
  private function buildWhere(Builder $builder, array $query, ?array $categoryIds = null, bool $publicOnly = true): Builder
  {
    if ($publicOnly) {
      $builder->where('isPublished', true)
        ->where(function (Builder $q) {
          $q->where('hideWhenOutOfStock', false)
            ->orWhere(function (Builder $q2) {
              $q2->where('hideWhenOutOfStock', true)->where('stockQuantity', '>', 0);
            })
            ->orWhere(function (Builder $q2) {
              $q2->where('hideWhenOutOfStock', true)
                ->whereHas('variants', fn (Builder $v) => $v->where('isActive', true)->where('stockQuantity', '>', 0));
            });
        });
    }

    if (! empty($query['search'])) {
      $search = (string) $query['search'];
      $builder->where(function (Builder $q) use ($search) {
        $q->where('name', 'ilike', "%{$search}%")
          ->orWhere('slug', 'ilike', "%{$search}%")
          ->orWhere('sku', 'ilike', "%{$search}%");
      });
    }

    if ($categoryIds) {
      $builder->whereIn('categoryId', $categoryIds);
    }

    if (! empty($query['featured'])) {
      $builder->where('isFeatured', true);
    }

    if (! empty($query['onSale']) || ($query['deals'] ?? null) === 'on-sale') {
      $builder->where('isOnSale', true);
    }

    if (! empty($query['isNew']) || ($query['deals'] ?? null) === 'new-arrivals') {
      $builder->where('isNew', true);
    }

    if (! empty($query['bestSeller']) || ($query['deals'] ?? null) === 'best-sellers') {
      $builder->where('isBestSeller', true);
    }

    if (isset($query['minPrice']) || isset($query['maxPrice'])) {
      if (isset($query['minPrice'])) {
        $builder->where('priceInPHP', '>=', $query['minPrice']);
      }
      if (isset($query['maxPrice'])) {
        $builder->where('priceInPHP', '<=', $query['maxPrice']);
      }
    }

    return $builder;
  }

  private function applyOrderBy(Builder $builder, ?string $sort): Builder
  {
    return match ($sort) {
      'price-asc' => $builder->orderBy('priceInPHP'),
      'price-desc' => $builder->orderByDesc('priceInPHP'),
      'popular' => $builder->orderByDesc('reviewCount'),
      default => $builder->orderByDesc('createdAt'),
    };
  }

  /**
   * @param  array<string, mixed>  $query
   */
  public function findAll(array $query = []): array
  {
    $cacheKey = 'products:list:'.ApiCache::queryKey($query);

    return ApiCache::remember(ApiCache::DOMAIN_CATALOG, $cacheKey, function () use ($query) {
      return $this->loadProductList($query);
    });
  }

  /**
   * @param  array<string, mixed>  $query
   */
  private function loadProductList(array $query): array
  {
    $lowStockThreshold = $this->getLowStockThreshold();
    $page = max(1, (int) ($query['page'] ?? 1));
    $limit = max(1, (int) ($query['limit'] ?? 100));

    $categoryIds = ! empty($query['category'])
      ? $this->categories->resolveCategoryIdsBySlug((string) $query['category'])
      : null;

    $baseQuery = Product::query()
      ->with(['category:id,name,slug'])
      ->withCount('variants');

    $this->buildWhere(
      $baseQuery,
      $query,
      $categoryIds ? (count($categoryIds) ? $categoryIds : ['__none__']) : null,
    );

    if ($categoryIds !== null && count($categoryIds) === 0) {
      return [
        'data' => [],
        'meta' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'totalPages' => 1],
      ];
    }

    $total = (clone $baseQuery)->count();
    $rows = $this->applyOrderBy(clone $baseQuery, $query['sort'] ?? null)
      ->skip(($page - 1) * $limit)
      ->take($limit)
      ->get();

    return [
      'data' => $this->serializeMany($rows, $lowStockThreshold),
      'meta' => [
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'totalPages' => max(1, (int) ceil($total / $limit)),
      ],
    ];
  }

  public function findAllAdmin(?string $search = null, ?string $categoryId = null): array
  {
    $query = Product::query()
      ->with(['category:id,name,slug'])
      ->withCount('variants');

    if ($search) {
      $query->where(function (Builder $q) use ($search) {
        $q->where('name', 'ilike', "%{$search}%")
          ->orWhere('slug', 'ilike', "%{$search}%")
          ->orWhere('sku', 'ilike', "%{$search}%");
      });
    }

    if ($categoryId) {
      $ids = $this->categories->collectDescendantIds($categoryId);
      $query->whereIn('categoryId', $ids);
    }

    $rows = $query->orderByDesc('createdAt')->get();

    $variantProductIds = $rows
      ->filter(fn (Product $row) => ($row->variants_count ?? 0) > 0)
      ->pluck('id')
      ->all();

    $variantStockByProductId = [];
    if ($variantProductIds) {
      $grouped = ProductVariant::query()
        ->selectRaw('"productId", SUM("stockQuantity") as total')
        ->whereIn('productId', $variantProductIds)
        ->groupBy('productId')
        ->get();

      foreach ($grouped as $entry) {
        $variantStockByProductId[$entry->productId] = (int) $entry->total;
      }
    }

    return $this->serializeAdminMany($rows, $variantStockByProductId);
  }

  public function autocomplete(?string $q): array
  {
    if (! $q || trim($q) === '') {
      return [];
    }

    $term = trim($q);
    $rows = Product::query()
      ->where('isPublished', true)
      ->where(function (Builder $builder) {
        $builder->where('hideWhenOutOfStock', false)
          ->orWhere(function (Builder $q2) {
            $q2->where('hideWhenOutOfStock', true)->where('stockQuantity', '>', 0);
          })
          ->orWhere(function (Builder $q2) {
            $q2->where('hideWhenOutOfStock', true)
              ->whereHas('variants', fn (Builder $v) => $v->where('isActive', true)->where('stockQuantity', '>', 0));
          });
      })
      ->where(function (Builder $builder) use ($term) {
        $builder->where('name', 'ilike', "%{$term}%")
          ->orWhere('sku', 'ilike', "%{$term}%");
      })
      ->select(['id', 'name', 'slug', 'sku', 'priceInPHP', 'images'])
      ->orderByDesc('reviewCount')
      ->limit(8)
      ->get();

    return $rows->map(function (Product $p) {
      $images = $p->images ?? [];

      return [
        'id' => $p->id,
        'name' => $p->name,
        'slug' => $p->slug,
        'sku' => $p->sku,
        'priceInPHP' => (float) $p->priceInPHP,
        'image' => $images[0] ?? null,
      ];
    })->all();
  }

  public function findBySlug(string $slug): array
  {
    return ApiCache::remember(ApiCache::DOMAIN_CATALOG, 'product:slug:'.rawurlencode($slug), function () use ($slug) {
      return $this->loadProductBySlug($slug);
    });
  }

  private function loadProductBySlug(string $slug): array
  {
    $lowStockThreshold = $this->getLowStockThreshold();
    $product = Product::query()
      ->with([
        'category:id,name,slug',
        'variants' => fn ($q) => $q->where('isActive', true)->orderBy('sortOrder'),
      ])
      ->where('slug', $slug)
      ->first();

    if (! $product || ! $product->isPublished) {
      throw new NotFoundHttpException("Product not found: {$slug}");
    }

    $related = [];
    if ($product->categoryId) {
      $related = Product::query()
        ->with(['category:id,name,slug'])
        ->where('categoryId', $product->categoryId)
        ->where('id', '!=', $product->id)
        ->where('isPublished', true)
        ->orderByDesc('reviewCount')
        ->limit(4)
        ->get();
    }

    $serialized = $this->serializeProduct($product, $lowStockThreshold);
    $variants = $product->variants->map(function (ProductVariant $v) use ($lowStockThreshold) {
      return [
        'id' => $v->id,
        'sku' => $v->sku,
        'name' => $v->name,
        'options' => $v->options,
        'priceInPHP' => (float) $v->priceInPHP,
        'compareAtPrice' => $v->compareAtPrice !== null ? (float) $v->compareAtPrice : null,
        'images' => $v->images,
        ...$this->stockMeta($v->stockQuantity, $lowStockThreshold),
      ];
    })->all();

    return [
      ...$serialized,
      'category' => $product->category,
      'variants' => $variants,
      'hasVariants' => count($variants) > 0,
      'relatedProducts' => $this->serializeMany($related, $lowStockThreshold),
    ];
  }

  public function findById(string $id): array
  {
    $product = Product::query()
      ->with(['category:id,name,slug'])
      ->find($id);

    if (! $product) {
      throw new NotFoundHttpException("Product not found: {$id}");
    }

    return $this->serializeProduct($product);
  }

  /**
   * @param  array<string, mixed>  $dto
   * @return array{installationAvailable: bool, installationFeeInPHP: float|null}
   */
  private function installationFromDto(array $dto): array
  {
    $fee = isset($dto['installationFeeInPHP']) && $dto['installationFeeInPHP'] > 0
      ? (float) $dto['installationFeeInPHP']
      : null;

    return [
      'installationAvailable' => $fee !== null,
      'installationFeeInPHP' => $fee,
    ];
  }

  /**
   * @param  array<string, mixed>  $dto
   * @return array<string, mixed>
   */
  private function productData(array $dto): array
  {
    $installation = (array_key_exists('installationFeeInPHP', $dto) || array_key_exists('installationAvailable', $dto))
      ? $this->installationFromDto($dto)
      : [];

    return array_filter([
      'name' => $dto['name'] ?? null,
      'slug' => $dto['slug'] ?? null,
      'sku' => $dto['sku'] ?? null,
      'shortDescription' => $dto['shortDescription'] ?? null,
      'description' => $dto['description'] ?? null,
      'priceInPHP' => $dto['priceInPHP'] ?? null,
      'compareAtPrice' => $dto['compareAtPrice'] ?? null,
      'weightInGrams' => $dto['weightInGrams'] ?? null,
      'stockQuantity' => $dto['stockQuantity'] ?? null,
      'images' => $dto['images'] ?? null,
      'features' => $dto['features'] ?? null,
      'isFeatured' => $dto['isFeatured'] ?? null,
      'isNew' => $dto['isNew'] ?? null,
      'isBestSeller' => $dto['isBestSeller'] ?? null,
      'isOnSale' => $dto['isOnSale'] ?? null,
      'isPublished' => $dto['isPublished'] ?? null,
      'hideWhenOutOfStock' => $dto['hideWhenOutOfStock'] ?? null,
      'rating' => $dto['rating'] ?? null,
      'reviewCount' => $dto['reviewCount'] ?? null,
      'categoryId' => $dto['categoryId'] ?? null,
      ...$installation,
    ], fn ($value) => $value !== null);
  }

  /**
   * @param  array<string, mixed>  $dto
   */
  public function create(array $dto): array
  {
    $slug = trim((string) ($dto['slug'] ?? '')) ?: Slug::slugify((string) $dto['name']);

    if (Product::query()->where('slug', $slug)->exists()) {
      throw new BadRequestHttpException("Slug already exists: {$slug}");
    }

    $installation = $this->installationFromDto($dto);

    $product = Product::query()->create([
      'name' => $dto['name'],
      'slug' => $slug,
      'sku' => $dto['sku'] ?? null,
      'shortDescription' => $dto['shortDescription'] ?? null,
      'description' => $dto['description'] ?? '',
      'priceInPHP' => $dto['priceInPHP'],
      'compareAtPrice' => $dto['compareAtPrice'] ?? null,
      'weightInGrams' => $dto['weightInGrams'],
      'stockQuantity' => $dto['stockQuantity'],
      'images' => $dto['images'] ?? [],
      'features' => $dto['features'] ?? [],
      'isFeatured' => $dto['isFeatured'] ?? false,
      'isNew' => $dto['isNew'] ?? false,
      'isBestSeller' => $dto['isBestSeller'] ?? false,
      'isOnSale' => $dto['isOnSale'] ?? false,
      'isPublished' => $dto['isPublished'] ?? true,
      'hideWhenOutOfStock' => $dto['hideWhenOutOfStock'] ?? false,
      'installationAvailable' => $installation['installationAvailable'],
      'installationFeeInPHP' => $installation['installationFeeInPHP'],
      'rating' => $dto['rating'] ?? 0,
      'reviewCount' => $dto['reviewCount'] ?? 0,
      'categoryId' => $dto['categoryId'] ?? null,
    ]);

    $this->invalidateCatalogCache();

    return $this->serializeProduct($product);
  }

  /**
   * @param  array<string, mixed>  $dto
   */
  public function update(string $id, array $dto): array
  {
    $existing = Product::query()->find($id);
    if (! $existing) {
      throw new NotFoundHttpException("Product not found: {$id}");
    }

    if (! empty($dto['slug'])) {
      $conflict = Product::query()
        ->where('slug', $dto['slug'])
        ->where('id', '!=', $id)
        ->exists();

      if ($conflict) {
        throw new BadRequestHttpException("Slug already exists: {$dto['slug']}");
      }
    }

    $variantCount = ProductVariant::query()->where('productId', $id)->count();
    if ($variantCount > 0 && array_key_exists('stockQuantity', $dto)) {
      unset($dto['stockQuantity']);
    }

    $data = $this->productData($dto);
    $previousStock = (int) $existing->stockQuantity;
    $existing->update($data);
    $product = $existing->fresh();

    if (array_key_exists('stockQuantity', $dto) && $variantCount === 0) {
      $this->stockAlerts->checkAndNotifyAfterStockIncrease(
        $id,
        $previousStock,
        (int) $product->stockQuantity,
      );
    }

    $this->invalidateCatalogCache();

    return $this->serializeProduct($product);
  }

  public function remove(string $id): array
  {
    $this->findById($id);
    Product::query()->where('id', $id)->delete();

    $this->invalidateCatalogCache();

    return ['deleted' => true];
  }

  /**
   * @param  list<UploadedFile>  $files
   */
  public function uploadImages(string $id, array $files): array
  {
    if (! $files) {
      throw new BadRequestHttpException('At least one image file is required');
    }

    $product = Product::query()->find($id);
    if (! $product) {
      throw new NotFoundHttpException("Product not found: {$id}");
    }

    $newUrls = $this->supabase->uploadProductImages($id, $files);
    $product->update(['images' => array_merge($product->images ?? [], $newUrls)]);

    $this->invalidateCatalogCache();

    return $this->serializeProduct($product->fresh());
  }

  public function uploadDescriptionImage(string $id, ?UploadedFile $file): array
  {
    if (! $file) {
      throw new BadRequestHttpException('An image file is required');
    }

    $this->findById($id);
    $url = $this->supabase->uploadDescriptionImage($id, $file);

    return ['url' => $url];
  }

  /**
   * @return array{configured: bool, bucket: string}
   */
  public function storageStatus(): array
  {
    return [
      'configured' => $this->supabase->isConfigured(),
      'bucket' => $this->supabase->getBucket(),
    ];
  }

  public function serializeProduct(Product $product, ?int $lowStockThreshold = null): array
  {
    $lowStockThreshold ??= self::DEFAULT_LOW_STOCK_THRESHOLD;
    $variantCount = $product->variants_count ?? $product->variants()->count();
    $compareAt = $product->compareAtPrice !== null ? (float) $product->compareAtPrice : null;
    $price = (float) $product->priceInPHP;
    $discountPercent = $compareAt && $compareAt > $price
      ? (int) round((($compareAt - $price) / $compareAt) * 100)
      : null;
    $stock = $this->stockMeta((int) $product->stockQuantity, $lowStockThreshold);
    $installationFee = $product->installationAvailable && $product->installationFeeInPHP !== null
      ? (float) $product->installationFeeInPHP
      : null;

    return [
      'id' => $product->id,
      'sku' => $product->sku,
      'name' => $product->name,
      'slug' => $product->slug,
      'shortDescription' => $product->shortDescription,
      'description' => $product->description,
      'priceInPHP' => $price,
      'compareAtPrice' => $compareAt,
      'discountPercent' => $discountPercent,
      'weightInGrams' => $product->weightInGrams,
      'stockQuantity' => (int) $product->stockQuantity,
      'images' => $product->images ?? [],
      'features' => $product->features ?? [],
      'isFeatured' => $product->isFeatured,
      'isNew' => $product->isNew,
      'isBestSeller' => $product->isBestSeller,
      'isOnSale' => $product->isOnSale,
      'isPublished' => $product->isPublished,
      'hideWhenOutOfStock' => $product->hideWhenOutOfStock,
      'installationAvailable' => (bool) $product->installationAvailable,
      'installationFeeInPHP' => $installationFee,
      'rating' => $product->rating,
      'reviewCount' => $product->reviewCount,
      'sortOrder' => $product->sortOrder,
      'categoryId' => $product->categoryId,
      'createdAt' => $product->createdAt,
      'updatedAt' => $product->updatedAt,
      'category' => $product->relationLoaded('category') ? $product->category : null,
      'hasVariants' => $variantCount > 0,
      ...$stock,
    ];
  }

  /**
   * @param  iterable<Product>  $products
   */
  public function serializeMany(iterable $products, ?int $lowStockThreshold = null): array
  {
    $lowStockThreshold ??= self::DEFAULT_LOW_STOCK_THRESHOLD;
    $result = [];
    foreach ($products as $product) {
      $result[] = $this->serializeProduct($product, $lowStockThreshold);
    }

    return $result;
  }

  public function serializeAdminProduct(Product $product, ?int $variantStockTotal = null): array
  {
    $base = $this->serializeProduct($product);
    $variantCount = $product->variants_count ?? 0;
    $hasVariants = $variantCount > 0;
    $effectiveStock = $hasVariants ? ($variantStockTotal ?? (int) $product->stockQuantity) : (int) $product->stockQuantity;

    return [
      ...$base,
      'stockQuantity' => $effectiveStock,
      'inStock' => $effectiveStock > 0,
      'stockLabel' => $effectiveStock <= 0 ? 'Out of Stock' : ($effectiveStock <= 5 ? 'Low Stock' : 'In Stock'),
      'variantCount' => $variantCount,
      'hasVariants' => $hasVariants,
      'variantStockTotal' => $hasVariants ? $effectiveStock : null,
    ];
  }

  /**
   * @param  iterable<Product>  $products
   * @param  array<string, int>  $variantStockByProductId
   */
  public function serializeAdminMany(iterable $products, array $variantStockByProductId = []): array
  {
    $result = [];
    foreach ($products as $product) {
      $result[] = $this->serializeAdminProduct($product, $variantStockByProductId[$product->id] ?? null);
    }

    return $result;
  }

  private function syncProductStockFromVariants(string $productId): int
  {
    $total = (int) ProductVariant::query()->where('productId', $productId)->sum('stockQuantity');
    Product::query()->where('id', $productId)->update(['stockQuantity' => $total]);

    return $total;
  }

  /**
   * @param  list<array<string, mixed>>  $updates
   */
  public function batchUpdateProducts(array $updates): array
  {
    $failed = [];
    $updated = 0;

    foreach ($updates as $item) {
      $itemId = $item['id'];
      try {
        $id = $itemId;
        $categoryName = $item['categoryName'] ?? null;
        unset($item['id'], $item['categoryName']);

        if ($categoryName && trim($categoryName) !== '') {
          $category = Category::query()
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($categoryName))])
            ->first();

          if (! $category) {
            throw new BadRequestHttpException("Category not found: {$categoryName}");
          }
          $item['categoryId'] = $category->id;
        }

        $payload = array_filter($item, fn ($value) => $value !== null && $value !== '');

        if (! $payload) {
          throw new BadRequestHttpException('No fields to update');
        }

        $this->update($id, $payload);
        $updated++;
      } catch (\Throwable $error) {
        $failed[] = [
          'id' => $itemId,
          'error' => $error->getMessage(),
        ];
      }
    }

    return ['updated' => $updated, 'failed' => $failed, 'total' => count($updates)];
  }

  /**
   * @param  list<array<string, mixed>>  $items
   */
  public function batchCreateProducts(array $items): array
  {
    $failed = [];
    $skipped = [];
    $created = [];
    $count = 0;

    $categories = Category::query()->select(['id', 'name'])->get();
    $categoryIdByName = [];
    foreach ($categories as $category) {
      $categoryIdByName[strtolower($category->name)] = $category->id;
    }

    $existingProducts = Product::query()->select(['id', 'name', 'sku', 'slug'])->get();
    $productBySku = [];
    $productBySlug = [];
    foreach ($existingProducts as $product) {
      if ($product->sku) {
        $productBySku[$product->sku] = $product;
      }
      $productBySlug[$product->slug] = $product;
    }
    $reservedSkus = array_keys($productBySku);
    $reservedSlugs = array_keys($productBySlug);

    foreach ($items as $item) {
      $key = trim((string) ($item['sku'] ?? '')) ?: (trim((string) ($item['slug'] ?? '')) ?: $item['name']);
      try {
        $categoryName = $item['categoryName'] ?? null;
        unset($item['categoryName']);

        $description = $item['description'] ?? $item['shortDescription'] ?? '';
        $slug = trim((string) ($item['slug'] ?? '')) ?: Slug::slugify((string) $item['name']);
        $sku = trim((string) ($item['sku'] ?? '')) ?: null;
        $categoryId = null;

        if ($categoryName && trim($categoryName) !== '') {
          $resolvedCategoryId = $categoryIdByName[strtolower(trim($categoryName))] ?? null;
          if (! $resolvedCategoryId) {
            throw new BadRequestHttpException("Category not found: {$categoryName}");
          }
          $categoryId = $resolvedCategoryId;
        }

        if ($sku && in_array($sku, $reservedSkus, true)) {
          $existing = $productBySku[$sku];
          $skipped[] = [
            'key' => $sku,
            'name' => $existing->name ?? $item['name'],
            'reason' => 'SKU already exists',
          ];
          continue;
        }

        if (in_array($slug, $reservedSlugs, true)) {
          $existing = $productBySlug[$slug];
          $skipped[] = [
            'key' => $slug,
            'name' => $existing->name ?? $item['name'],
            'reason' => 'Slug already exists',
          ];
          continue;
        }

        if ($sku) {
          $reservedSkus[] = $sku;
        }
        $reservedSlugs[] = $slug;

        $installation = $this->installationFromDto($item);

        $product = Product::query()->create([
          'name' => $item['name'],
          'slug' => $slug,
          'sku' => $sku,
          'shortDescription' => $item['shortDescription'] ?? null,
          'description' => $description,
          'priceInPHP' => $item['priceInPHP'],
          'compareAtPrice' => $item['compareAtPrice'] ?? null,
          'weightInGrams' => $item['weightInGrams'],
          'stockQuantity' => $item['stockQuantity'],
          'images' => [],
          'features' => [],
          'isFeatured' => $item['isFeatured'] ?? false,
          'isNew' => $item['isNew'] ?? false,
          'isBestSeller' => $item['isBestSeller'] ?? false,
          'isOnSale' => $item['isOnSale'] ?? false,
          'isPublished' => $item['isPublished'] ?? true,
          'hideWhenOutOfStock' => false,
          'installationAvailable' => $installation['installationAvailable'],
          'installationFeeInPHP' => $installation['installationFeeInPHP'],
          'rating' => 0,
          'reviewCount' => 0,
          'categoryId' => $categoryId,
        ]);

        $created[] = ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku];
        $count++;
      } catch (\Throwable $error) {
        $failed[] = [
          'key' => $key,
          'error' => $error->getMessage(),
        ];
      }
    }

    if ($count > 0) {
      $this->invalidateCatalogCache();
    }

    return [
      'created' => $count,
      'skipped' => $skipped,
      'failed' => $failed,
      'total' => count($items),
      'products' => $created,
    ];
  }

  /**
   * @param  list<array<string, mixed>>  $items
   */
  public function batchCreateVariants(array $items): array
  {
    $failed = [];
    $skipped = [];
    $affectedProductIds = [];
    $count = 0;

    $productSkus = array_values(array_unique(array_filter(array_map(
      fn (array $item) => trim((string) ($item['productSku'] ?? '')),
      $items,
    ))));

    $products = Product::query()
      ->whereIn('sku', $productSkus)
      ->select(['id', 'sku', 'name'])
      ->get();

    $productIdBySku = [];
    foreach ($products as $product) {
      if ($product->sku) {
        $productIdBySku[$product->sku] = $product;
      }
    }

    $existingVariants = $productSkus
      ? ProductVariant::query()
        ->whereHas('product', fn (Builder $q) => $q->whereIn('sku', $productSkus))
        ->with(['product:id,sku,name'])
        ->get()
      : collect();

    $variantBySku = [];
    $variantKeyByProduct = [];
    foreach ($existingVariants as $variant) {
      if ($variant->sku) {
        $variantBySku[$variant->sku] = $variant;
      }
      $productSku = $variant->product->sku ?? '';
      $variantKeyByProduct[strtolower("{$productSku}:{$variant->name}")] = $variant;
    }
    $reservedVariantSkus = array_keys($variantBySku);

    foreach ($items as $item) {
      $key = trim((string) ($item['sku'] ?? '')) ?: "{$item['productSku']}:{$item['name']}";
      try {
        $product = $productIdBySku[trim((string) $item['productSku'])] ?? null;
        if (! $product) {
          throw new NotFoundHttpException("Product not found for SKU: {$item['productSku']}");
        }

        $variantSku = trim((string) ($item['sku'] ?? '')) ?: null;
        $variantNameKey = strtolower(trim((string) $item['productSku']).':'.strtolower((string) $item['name']));

        if ($variantSku && in_array($variantSku, $reservedVariantSkus, true)) {
          $existing = $variantBySku[$variantSku];
          $skipped[] = [
            'key' => $variantSku,
            'name' => $existing->name ?? $item['name'],
            'reason' => 'Variant SKU already exists',
          ];
          continue;
        }

        if (isset($variantKeyByProduct[$variantNameKey])) {
          $existing = $variantKeyByProduct[$variantNameKey];
          $skipped[] = [
            'key' => $key,
            'name' => $existing->name ?? $item['name'],
            'reason' => "Variant already exists on {$product->name}",
          ];
          continue;
        }

        $options = ! empty($item['optionColor']) ? ['Color' => $item['optionColor']] : [];

        $variant = ProductVariant::query()->create([
          'productId' => $product->id,
          'name' => $item['name'],
          'sku' => $variantSku,
          'options' => $options,
          'priceInPHP' => $item['priceInPHP'],
          'compareAtPrice' => $item['compareAtPrice'] ?? null,
          'stockQuantity' => $item['stockQuantity'],
          'images' => [],
          'isActive' => $item['isActive'] ?? true,
        ]);

        if ($variantSku) {
          $reservedVariantSkus[] = $variantSku;
        }
        $variantKeyByProduct[$variantNameKey] = $variant;
        if ($variant->sku) {
          $variantBySku[$variant->sku] = $variant;
        }

        $affectedProductIds[$product->id] = true;
        $count++;
      } catch (\Throwable $error) {
        $failed[] = [
          'key' => $key,
          'error' => $error->getMessage(),
        ];
      }
    }

    foreach (array_keys($affectedProductIds) as $productId) {
      $this->syncProductStockFromVariants($productId);
    }

    if ($count > 0) {
      $this->invalidateCatalogCache();
    }

    return ['created' => $count, 'skipped' => $skipped, 'failed' => $failed, 'total' => count($items)];
  }

  public function findAllVariantsAdmin(?string $search = null, ?string $categoryId = null): array
  {
    $query = ProductVariant::query()
      ->with(['product:id,name,slug,sku,categoryId']);

    if ($search || $categoryId) {
      $query->whereHas('product', function (Builder $productQuery) use ($search, $categoryId) {
        if ($search) {
          $productQuery->where(function (Builder $q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
              ->orWhere('slug', 'ilike', "%{$search}%")
              ->orWhere('sku', 'ilike', "%{$search}%");
          });
        }
        if ($categoryId) {
          $productQuery->where('categoryId', $categoryId);
        }
      });
    }

    $rows = $query->get()->sortBy([
      fn (ProductVariant $a, ProductVariant $b) => strcmp($a->product->name ?? '', $b->product->name ?? ''),
      fn (ProductVariant $a, ProductVariant $b) => $a->sortOrder <=> $b->sortOrder,
    ])->values();

    return $rows->map(function (ProductVariant $row) {
      $options = $row->options ?? [];
      $optionColor = is_array($options)
        ? ($options['Color'] ?? $options['color'] ?? null)
        : null;

      return [
        ...$this->serializeVariant($row),
        'productId' => $row->productId,
        'productName' => $row->product->name,
        'productSlug' => $row->product->slug,
        'productSku' => $row->product->sku,
        'optionColor' => is_string($optionColor) ? $optionColor : null,
      ];
    })->all();
  }

  /**
   * @param  list<array<string, mixed>>  $updates
   */
  public function batchUpdateVariants(array $updates): array
  {
    $failed = [];
    $affectedProductIds = [];
    $updated = 0;

    foreach ($updates as $item) {
      try {
        $variant = ProductVariant::query()->find($item['id']);
        if (! $variant) {
          throw new NotFoundHttpException('Variant not found');
        }

        $id = $item['id'];
        unset($item['id']);
        $payload = array_filter($item, fn ($value) => $value !== null && $value !== '');

        if (! $payload) {
          throw new BadRequestHttpException('No fields to update');
        }

        $variant->update($payload);
        $affectedProductIds[$variant->productId] = true;
        $updated++;
      } catch (\Throwable $error) {
        $failed[] = [
          'id' => $item['id'] ?? 'unknown',
          'error' => $error->getMessage(),
        ];
      }
    }

    foreach (array_keys($affectedProductIds) as $productId) {
      $this->syncProductStockFromVariants($productId);
    }

    if ($updated > 0) {
      $this->invalidateCatalogCache();
    }

    return ['updated' => $updated, 'failed' => $failed, 'total' => count($updates)];
  }

  public function listVariants(string $productId): array
  {
    return ProductVariant::query()
      ->where('productId', $productId)
      ->orderBy('sortOrder')
      ->get()
      ->map(fn (ProductVariant $v) => $this->serializeVariant($v))
      ->all();
  }

  /**
   * @return array<string, mixed>
   */
  private function serializeVariant(ProductVariant $variant): array
  {
    return [
      'id' => $variant->id,
      'productId' => $variant->productId,
      'sku' => $variant->sku,
      'name' => $variant->name,
      'options' => $variant->options,
      'priceInPHP' => (float) $variant->priceInPHP,
      'compareAtPrice' => $variant->compareAtPrice !== null ? (float) $variant->compareAtPrice : null,
      'stockQuantity' => $variant->stockQuantity,
      'weightInGrams' => $variant->weightInGrams,
      'images' => $variant->images ?? [],
      'isActive' => $variant->isActive,
      'sortOrder' => $variant->sortOrder,
      'createdAt' => $variant->createdAt,
      'updatedAt' => $variant->updatedAt,
    ];
  }

  /**
   * @param  array<string, mixed>  $dto
   */
  public function createVariant(string $productId, array $dto): array
  {
    $this->findById($productId);

    $variant = ProductVariant::query()->create([
      'productId' => $productId,
      'name' => $dto['name'],
      'sku' => $dto['sku'] ?? null,
      'options' => $dto['options'] ?? [],
      'priceInPHP' => $dto['priceInPHP'],
      'compareAtPrice' => $dto['compareAtPrice'] ?? null,
      'stockQuantity' => $dto['stockQuantity'],
      'weightInGrams' => $dto['weightInGrams'] ?? null,
      'images' => $dto['images'] ?? [],
      'isActive' => $dto['isActive'] ?? true,
    ]);

    $this->syncProductStockFromVariants($productId);

    $this->invalidateCatalogCache();

    return $this->serializeVariant($variant);
  }

  /**
   * @param  array<string, mixed>  $dto
   */
  public function updateVariant(string $variantId, array $dto): array
  {
    $variant = ProductVariant::query()->find($variantId);
    if (! $variant) {
      throw new NotFoundHttpException('Variant not found');
    }

    $variant->update(array_intersect_key($dto, array_flip([
      'name', 'sku', 'options', 'priceInPHP', 'compareAtPrice',
      'stockQuantity', 'weightInGrams', 'images', 'isActive', 'sortOrder',
    ])));

    $this->syncProductStockFromVariants($variant->productId);

    $this->invalidateCatalogCache();

    return $this->serializeVariant($variant->fresh());
  }

  /**
   * @param  list<UploadedFile>  $files
   */
  public function uploadVariantImages(string $variantId, array $files): array
  {
    if (! $files) {
      throw new BadRequestHttpException('At least one image file is required');
    }

    $variant = ProductVariant::query()->find($variantId);
    if (! $variant) {
      throw new NotFoundHttpException('Variant not found');
    }

    $newUrls = $this->supabase->uploadVariantImages($variant->productId, $variantId, $files);
    $variant->update(['images' => array_merge($variant->images ?? [], $newUrls)]);

    $this->invalidateCatalogCache();

    return $this->serializeVariant($variant->fresh());
  }

  public function removeVariant(string $variantId): array
  {
    $variant = ProductVariant::query()->find($variantId);
    if (! $variant) {
      throw new NotFoundHttpException('Variant not found');
    }

    $productId = $variant->productId;
    $variant->delete();
    $this->syncProductStockFromVariants($productId);

    $this->invalidateCatalogCache();

    return ['deleted' => true];
  }

  private function invalidateCatalogCache(): void
  {
    ApiCache::bump(ApiCache::DOMAIN_CATALOG);
  }
}
