<?php

declare(strict_types=1);

namespace App\Services\Collections;

use App\Enums\CollectionType;
use App\Models\Collection;
use App\Models\CollectionProduct;
use App\Models\Product;
use App\Services\Categories\CategoriesService;
use App\Services\Supabase\SupabaseService;
use App\Support\Cache\ApiCache;
use App\Support\Utils\Slug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CollectionsService
{
  public function __construct(
    private readonly SupabaseService $supabase,
    private readonly CategoriesService $categories,
  ) {}

  public function findAllPublic(): array
  {
    return ApiCache::remember(ApiCache::DOMAIN_CATALOG, 'collections:public', function () {
      return Collection::query()
        ->where('isActive', true)
        ->orderBy('sortOrder')
        ->orderBy('name')
        ->get(['id', 'name', 'slug', 'description', 'imageUrl', 'type'])
        ->all();
    });
  }

  public function findBySlugPublic(string $slug): array
  {
    return ApiCache::remember(ApiCache::DOMAIN_CATALOG, 'collection:slug:'.rawurlencode($slug), function () use ($slug) {
      return $this->loadCollectionBySlugPublic($slug);
    });
  }

  private function loadCollectionBySlugPublic(string $slug): array
  {
    $collection = Collection::query()
      ->where('slug', $slug)
      ->where('isActive', true)
      ->with([
        'products' => fn ($q) => $q->orderBy('sortOrder'),
        'products.product.category:id,name,slug',
        'products.product.variants' => fn ($q) => $q->where('isActive', true)->orderBy('sortOrder'),
      ])
      ->first();

    if (! $collection) {
      throw new NotFoundHttpException('Collection not found');
    }

    $products = $collection->products->map(fn (CollectionProduct $cp) => $cp->product)->filter();

    if ($collection->type === CollectionType::Automated && $collection->rules) {
      $products = collect($this->resolveAutomatedProducts($collection->rules));
    }

    return [
      'id' => $collection->id,
      'name' => $collection->name,
      'slug' => $collection->slug,
      'description' => $collection->description,
      'imageUrl' => $collection->imageUrl,
      'type' => $collection->type->value,
      'products' => $products->map(fn (Product $p) => $this->serializeProduct($p))->values()->all(),
    ];
  }

  public function findAllAdmin(): array
  {
    return Collection::query()
      ->withCount('products')
      ->orderBy('sortOrder')
      ->orderBy('name')
      ->get()
      ->map(fn (Collection $collection) => $this->formatCollectionAdmin($collection))
      ->all();
  }

  /**
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  public function create(array $data): array
  {
    $slug = trim((string) ($data['slug'] ?? '')) ?: Slug::slugify((string) $data['name']);
    $type = isset($data['type']) ? CollectionType::from((string) $data['type']) : CollectionType::Manual;

    $collection = DB::transaction(function () use ($data, $slug, $type) {
      $collection = Collection::query()->create([
        'name' => $data['name'],
        'slug' => $slug,
        'description' => $data['description'] ?? null,
        'type' => $type,
        'rules' => $data['rules'] ?? null,
        'imageUrl' => $data['imageUrl'] ?? null,
      ]);

      if (! empty($data['productIds'])) {
        foreach ($data['productIds'] as $index => $productId) {
          CollectionProduct::query()->create([
            'collectionId' => $collection->id,
            'productId' => $productId,
            'sortOrder' => $index,
          ]);
        }
      }

      return $collection;
    });

    ApiCache::bump(ApiCache::DOMAIN_CATALOG);

    return $this->formatCollectionAdmin($collection->loadCount('products'));
  }

  /**
   * @param  array<string, mixed>  $data
   * @return array<string, mixed>
   */
  public function update(string $id, array $data): array
  {
    $this->ensure($id);
    $productIds = $data['productIds'] ?? null;
    unset($data['productIds']);

    $collection = DB::transaction(function () use ($id, $data, $productIds) {
      if ($productIds !== null) {
        CollectionProduct::query()->where('collectionId', $id)->delete();
        foreach ($productIds as $index => $productId) {
          CollectionProduct::query()->create([
            'collectionId' => $id,
            'productId' => $productId,
            'sortOrder' => $index,
          ]);
        }
      }

      if (isset($data['type'])) {
        $data['type'] = CollectionType::from((string) $data['type']);
      }

      $collection = Collection::query()->findOrFail($id);
      $collection->update(array_intersect_key($data, array_flip([
        'name', 'slug', 'description', 'type', 'rules', 'imageUrl', 'isActive', 'sortOrder',
      ])));

      return $collection->fresh();
    });

    ApiCache::bump(ApiCache::DOMAIN_CATALOG);

    return $this->formatCollectionAdmin($collection->loadCount('products'));
  }

  public function remove(string $id): array
  {
    $this->ensure($id);
    Collection::query()->where('id', $id)->delete();

    ApiCache::bump(ApiCache::DOMAIN_CATALOG);

    return ['deleted' => true];
  }

  public function uploadCoverImage(string $id, ?UploadedFile $file): array
  {
    if (! $file) {
      throw new BadRequestHttpException('A cover image file is required');
    }

    $this->ensure($id);
    $imageUrl = $this->supabase->uploadCollectionCoverImage($id, $file);

    $collection = Collection::query()->findOrFail($id);
    $collection->update(['imageUrl' => $imageUrl]);

    ApiCache::bump(ApiCache::DOMAIN_CATALOG);

    return $this->formatCollectionAdmin($collection->fresh()->loadCount('products'));
  }

  /**
   * @param  array<string, mixed>  $rules
   * @return list<Product>
   */
  private function resolveAutomatedProducts(array $rules): array
  {
    $query = Product::query()
      ->with([
        'category:id,name,slug',
        'variants' => fn ($q) => $q->where('isActive', true),
      ]);

    if (! empty($rules['isOnSale'])) {
      $query->where('isOnSale', true);
    }
    if (! empty($rules['isNew'])) {
      $query->where('isNew', true);
    }
    if (! empty($rules['isFeatured'])) {
      $query->where('isFeatured', true);
    }
    if (! empty($rules['categorySlug'])) {
      $categoryIds = $this->categories->resolveCategoryIdsBySlug((string) $rules['categorySlug']);
      if (! $categoryIds) {
        return [];
      }
      $query->whereIn('categoryId', $categoryIds);
    }

    return $query
      ->orderBy('sortOrder')
      ->limit((int) ($rules['limit'] ?? 24))
      ->get()
      ->all();
  }

  private function serializeProduct(Product $p): array
  {
    $variants = $p->relationLoaded('variants') ? $p->variants : collect();
    $variantPrices = $variants->map(fn ($v) => (float) $v->priceInPHP)->all();
    $fromPrice = $variantPrices ? min($variantPrices) : (float) $p->priceInPHP;

    return [
      'id' => $p->id,
      'sku' => $p->sku,
      'name' => $p->name,
      'slug' => $p->slug,
      'priceInPHP' => $fromPrice,
      'compareAtPrice' => $p->compareAtPrice !== null ? (float) $p->compareAtPrice : null,
      'images' => $p->images ?? [],
      'isOnSale' => $p->isOnSale,
      'rating' => $p->rating,
      'reviewCount' => $p->reviewCount,
      'stockQuantity' => $variants->isNotEmpty()
        ? $variants->sum('stockQuantity')
        : (int) $p->stockQuantity,
      'hasVariants' => $variants->isNotEmpty(),
      'variants' => $variants->map(fn ($v) => [
        'id' => $v->id,
        'name' => $v->name,
        'priceInPHP' => (float) $v->priceInPHP,
        'stockQuantity' => $v->stockQuantity,
      ])->values()->all(),
    ];
  }

  private function ensure(string $id): Collection
  {
    $row = Collection::query()->find($id);
    if (! $row) {
      throw new NotFoundHttpException('Collection not found');
    }

    return $row;
  }

  /**
   * @return array<string, mixed>
   */
  private function formatCollectionAdmin(Collection $collection): array
  {
    return [
      'id' => $collection->id,
      'name' => $collection->name,
      'slug' => $collection->slug,
      'description' => $collection->description,
      'type' => $collection->type->value,
      'rules' => $collection->rules,
      'imageUrl' => $collection->imageUrl,
      'sortOrder' => $collection->sortOrder,
      'isActive' => $collection->isActive,
      'createdAt' => $collection->createdAt,
      'updatedAt' => $collection->updatedAt,
      '_count' => ['products' => (int) ($collection->products_count ?? 0)],
    ];
  }
}
