<?php

declare(strict_types=1);

namespace App\Services\Bundles;

use App\Models\BundleItem;
use App\Models\ProductBundle;
use App\Services\Supabase\SupabaseService;
use App\Support\Cache\ApiCache;
use App\Support\Utils\Slug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BundlesService
{
  public function __construct(
    private readonly SupabaseService $supabase,
  ) {}

  public function findAllPublic(): array
  {
    return ApiCache::remember(ApiCache::DOMAIN_CATALOG, 'bundles:public', function () {
      $rows = ProductBundle::query()
        ->where('isActive', true)
        ->with([
          'items.product:id,name,slug,images,priceInPHP',
          'items.variant:id,name,priceInPHP',
        ])
        ->orderBy('name')
        ->get();

      return $rows->map(fn (ProductBundle $bundle) => $this->serializeBundle($bundle))->all();
    });
  }

  public function findBySlug(string $slug): array
  {
    return ApiCache::remember(ApiCache::DOMAIN_CATALOG, 'bundle:slug:'.rawurlencode($slug), function () use ($slug) {
      $bundle = ProductBundle::query()
        ->where('slug', $slug)
        ->where('isActive', true)
        ->with(['items.product', 'items.variant'])
        ->first();

      if (! $bundle) {
        throw new NotFoundHttpException('Bundle not found');
      }

      return $this->serializeBundle($bundle);
    });
  }

  public function findAllAdmin(): array
  {
    return ProductBundle::query()
      ->with(['items.product:id,name'])
      ->orderByDesc('updatedAt')
      ->get()
      ->all();
  }

  /**
   * @param  array<string, mixed>  $data
   */
  public function create(array $data): ProductBundle
  {
    $slug = trim((string) ($data['slug'] ?? '')) ?: Slug::slugify((string) $data['name']);

    $bundle = DB::transaction(function () use ($data, $slug) {
      $bundle = ProductBundle::query()->create([
        'name' => $data['name'],
        'slug' => $slug,
        'description' => $data['description'] ?? null,
        'discountPercent' => $data['discountPercent'] ?? 10,
        'imageUrl' => $data['imageUrl'] ?? null,
      ]);

      foreach ($data['items'] ?? [] as $item) {
        BundleItem::query()->create([
          'bundleId' => $bundle->id,
          'productId' => $item['productId'],
          'variantId' => $item['variantId'] ?? null,
          'quantity' => $item['quantity'] ?? 1,
        ]);
      }

      return $bundle->fresh()->load('items');
    });

    ApiCache::bump(ApiCache::DOMAIN_CATALOG);

    return $bundle;
  }

  /**
   * @param  array<string, mixed>  $data
   */
  public function update(string $id, array $data): ProductBundle
  {
    $this->ensure($id);
    $items = $data['items'] ?? null;
    unset($data['items']);

    $bundle = DB::transaction(function () use ($id, $data, $items) {
      if ($items !== null) {
        BundleItem::query()->where('bundleId', $id)->delete();
        foreach ($items as $item) {
          BundleItem::query()->create([
            'bundleId' => $id,
            'productId' => $item['productId'],
            'variantId' => $item['variantId'] ?? null,
            'quantity' => $item['quantity'] ?? 1,
          ]);
        }
      }

      $bundle = ProductBundle::query()->findOrFail($id);
      $bundle->update(array_intersect_key($data, array_flip([
        'name', 'description', 'discountPercent', 'imageUrl', 'isActive',
      ])));

      return $bundle->fresh()->load(['items.product:id,name']);
    });

    ApiCache::bump(ApiCache::DOMAIN_CATALOG);

    return $bundle;
  }

  public function remove(string $id): array
  {
    $this->ensure($id);
    ProductBundle::query()->where('id', $id)->delete();

    ApiCache::bump(ApiCache::DOMAIN_CATALOG);

    return ['deleted' => true];
  }

  public function uploadCoverImage(string $id, ?UploadedFile $file): ProductBundle
  {
    if (! $file) {
      throw new BadRequestHttpException('A cover image file is required');
    }

    $this->ensure($id);
    $imageUrl = $this->supabase->uploadBundleCoverImage($id, $file);

    $bundle = ProductBundle::query()->findOrFail($id);
    $bundle->update(['imageUrl' => $imageUrl]);

    ApiCache::bump(ApiCache::DOMAIN_CATALOG);

    return $bundle->fresh()->load(['items.product:id,name']);
  }

  /**
   * @return array<string, mixed>
   */
  private function serializeBundle(ProductBundle $bundle): array
  {
    $items = $bundle->relationLoaded('items') ? $bundle->items : $bundle->items()->with(['product', 'variant'])->get();

    $subtotal = 0.0;
    foreach ($items as $item) {
      $price = $item->variant
        ? (float) $item->variant->priceInPHP
        : (float) $item->product->priceInPHP;
      $subtotal += $price * $item->quantity;
    }

    $discountPercent = (float) $bundle->discountPercent;
    $discount = $subtotal * ($discountPercent / 100);

    return [
      'id' => $bundle->id,
      'name' => $bundle->name,
      'slug' => $bundle->slug,
      'description' => $bundle->description,
      'discountPercent' => $discountPercent,
      'imageUrl' => $bundle->imageUrl,
      'subtotalInPHP' => $subtotal,
      'bundlePriceInPHP' => $subtotal - $discount,
      'savingsInPHP' => $discount,
      'items' => $items->map(function (BundleItem $item) {
        $images = $item->product->images ?? [];

        return [
          'productId' => $item->product->id,
          'productName' => $item->product->name,
          'productSlug' => $item->product->slug,
          'productImage' => $images[0] ?? null,
          'variantId' => $item->variant?->id,
          'variantName' => $item->variant?->name,
          'quantity' => $item->quantity,
          'unitPriceInPHP' => $item->variant
            ? (float) $item->variant->priceInPHP
            : (float) $item->product->priceInPHP,
        ];
      })->values()->all(),
    ];
  }

  private function ensure(string $id): ProductBundle
  {
    $row = ProductBundle::query()->find($id);
    if (! $row) {
      throw new NotFoundHttpException('Bundle not found');
    }

    return $row;
  }
}
