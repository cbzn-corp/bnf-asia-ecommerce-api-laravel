<?php

declare(strict_types=1);

namespace App\Services\Wishlist;

use App\Models\Product;
use App\Models\WishlistItem;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WishlistService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findByUser(string $userId): array
    {
        $rows = WishlistItem::query()
            ->where('userId', $userId)
            ->with(['product.variants' => fn ($q) => $q->select('id', 'productId')])
            ->orderByDesc('createdAt')
            ->get();

        return $rows->map(function (WishlistItem $row) {
            $product = $row->product;
            $images = $product->images ?? [];

            return [
                'productId' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'priceInPHP' => (float) $product->priceInPHP,
                'compareAtPrice' => $product->compareAtPrice !== null ? (float) $product->compareAtPrice : null,
                'image' => $images[0] ?? '',
                'hasVariants' => $product->variants->isNotEmpty(),
                'isOnSale' => $product->isOnSale,
                'isBestSeller' => $product->isBestSeller,
                'isNew' => $product->isNew,
                'isFeatured' => $product->isFeatured,
            ];
        })->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function add(string $userId, string $productId): array
    {
        $product = Product::query()->find($productId);
        if (! $product) {
            throw new NotFoundHttpException('Product not found.');
        }

        WishlistItem::query()->updateOrCreate(
            ['userId' => $userId, 'productId' => $productId],
            [],
        );

        return $this->findByUser($userId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function remove(string $userId, string $productId): array
    {
        WishlistItem::query()->where('userId', $userId)->where('productId', $productId)->delete();

        return $this->findByUser($userId);
    }

    /**
     * @param  list<string>  $productIds
     * @return list<array<string, mixed>>
     */
    public function sync(string $userId, array $productIds): array
    {
        $unique = array_values(array_unique(array_filter($productIds)));
        if ($unique === []) {
            return $this->findByUser($userId);
        }

        $validIds = Product::query()
            ->whereIn('id', $unique)
            ->where('isPublished', true)
            ->pluck('id')
            ->all();

        WishlistItem::query()
            ->where('userId', $userId)
            ->whereNotIn('productId', $validIds)
            ->delete();

        foreach ($validIds as $productId) {
            WishlistItem::query()->updateOrCreate(
                ['userId' => $userId, 'productId' => $productId],
                [],
            );
        }

        return $this->findByUser($userId);
    }
}
