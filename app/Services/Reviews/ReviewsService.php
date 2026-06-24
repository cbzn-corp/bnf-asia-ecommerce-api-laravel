<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\PaymentStatus;
use App\Enums\ShippingStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReviewsService
{
  /**
   * @return \Illuminate\Database\Eloquent\Collection<int, ProductReview>
   */
    public function findByProduct(string $productId, bool $approvedOnly = true)
    {
        return ProductReview::query()
            ->where('productId', $productId)
            ->when($approvedOnly, fn ($q) => $q->where('isApproved', true))
            ->orderByDesc('createdAt')
            ->with(['user:id,email'])
            ->get([
                'id', 'productId', 'authorName', 'rating', 'comment',
                'isVerifiedPurchase', 'photos', 'createdAt', 'userId',
            ]);
    }

  /**
   * @return \Illuminate\Database\Eloquent\Collection<int, ProductReview>
   */
    public function findPending()
    {
        return ProductReview::query()
            ->where('isApproved', false)
            ->orderByDesc('createdAt')
            ->with(['product:id,name,slug'])
            ->get();
    }

    public function countPending(): int
    {
        return ProductReview::query()
            ->where('isApproved', false)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $dto
     */
    public function create(array $dto, ?string $userId = null): ProductReview
    {
        $product = Product::query()->find($dto['productId']);
        if (! $product) {
            throw new NotFoundHttpException('Product not found.');
        }

        $isVerifiedPurchase = false;
        $orderId = null;

        if ($userId) {
            $purchase = OrderItem::query()
                ->where('OrderItem.productId', $dto['productId'])
                ->join('Order', 'Order.id', '=', 'OrderItem.orderId')
                ->where('Order.userId', $userId)
                ->whereIn('Order.paymentStatus', [PaymentStatus::Paid->value, PaymentStatus::Unpaid->value])
                ->whereIn('Order.shippingStatus', [
                    ShippingStatus::Delivered->value,
                    ShippingStatus::Shipped->value,
                    ShippingStatus::Processing->value,
                    ShippingStatus::Pending->value,
                ])
                ->orderByDesc('Order.createdAt')
                ->select('OrderItem.*', 'Order.id as order_id')
                ->first();

            if ($purchase) {
                $isVerifiedPurchase = true;
                $orderId = $purchase->order_id;
            }
        }

        $photos = $dto['photos'] ?? [];

        return ProductReview::query()->create([
            'productId' => $dto['productId'],
            'userId' => $userId,
            'orderId' => $orderId,
            'authorName' => $dto['authorName'],
            'rating' => $dto['rating'],
            'comment' => $dto['comment'],
            'isApproved' => false,
            'isVerifiedPurchase' => $isVerifiedPurchase,
            'photos' => array_slice(is_array($photos) ? $photos : [], 0, 3),
        ]);
    }

    /**
     * @return array{approved: bool}
     */
    public function approve(string $id): array
    {
        $review = ProductReview::query()->find($id);
        if (! $review) {
            throw new NotFoundHttpException('Review not found.');
        }

        DB::transaction(function () use ($review, $id) {
            ProductReview::query()->where('id', $id)->update(['isApproved' => true]);
            $this->recalculateProductRating($review->productId);
        });

        return ['approved' => true];
    }

    /**
     * @return array{deleted: bool}
     */
    public function remove(string $id): array
    {
        $review = ProductReview::query()->find($id);
        if (! $review) {
            throw new NotFoundHttpException('Review not found.');
        }

        $productId = $review->productId;

        DB::transaction(function () use ($id, $productId) {
            ProductReview::query()->where('id', $id)->delete();
            $this->recalculateProductRating($productId);
        });

        return ['deleted' => true];
    }

    private function recalculateProductRating(string $productId): void
    {
        $agg = ProductReview::query()
            ->where('productId', $productId)
            ->where('isApproved', true)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as review_count')
            ->first();

        Product::query()->where('id', $productId)->update([
            'rating' => $agg?->avg_rating ?? 0,
            'reviewCount' => (int) ($agg?->review_count ?? 0),
        ]);
    }
}
