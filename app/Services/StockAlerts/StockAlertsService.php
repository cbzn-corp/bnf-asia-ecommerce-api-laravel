<?php

declare(strict_types=1);

namespace App\Services\StockAlerts;

use App\Models\Product;
use App\Models\StockAlert;
use App\Services\Email\EmailService;
use App\Support\Config\AppUrls;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StockAlertsService
{
  public function __construct(
    private readonly EmailService $emailService,
  ) {}

  /**
   * @return array{sent: int}
   */
  public function notifyForProduct(string $productId, ?string $variantId = null): array
  {
    $product = Product::query()->find($productId);
    if (! $product) {
      return ['sent' => 0];
    }

    $productUrl = AppUrls::getStorefrontUrl().'/products/'.$product->slug;

    $alerts = StockAlert::query()
      ->where('productId', $productId)
      ->where('variantId', $variantId)
      ->whereNull('notifiedAt')
      ->limit(100)
      ->get();

    $sent = 0;
    foreach ($alerts as $alert) {
      $this->emailService->sendStockAlertEmail([
        'to' => $alert->email,
        'productName' => $product->name,
        'productUrl' => $productUrl,
      ]);

      $alert->update(['notifiedAt' => now()]);
      $sent++;
    }

    return ['sent' => $sent];
  }

  /**
   * @return array{sent: int}
   */
  public function checkAndNotifyAfterStockIncrease(
    string $productId,
    int $previousStock,
    int $newStock,
    ?string $variantId = null,
  ): array {
    if ($previousStock <= 0 && $newStock > 0) {
      return $this->notifyForProduct($productId, $variantId);
    }

    return ['sent' => 0];
  }

  public function subscribe(array $params): StockAlert
  {
    $email = strtolower(trim($params['email'] ?? ''));
    if (! str_contains($email, '@')) {
      throw new BadRequestHttpException('Valid email is required.');
    }

    $product = Product::query()
      ->with(['variants' => fn ($query) => $query->where('isActive', true)])
      ->find($params['productId'] ?? '');

    if (! $product?->isPublished) {
      throw new NotFoundHttpException('Product not found.');
    }

    $variantId = $params['variantId'] ?? null;

    if ($variantId) {
      $variant = $product->variants->firstWhere('id', $variantId);
      if (! $variant) {
        throw new BadRequestHttpException('Variant not found.');
      }
      if ($variant->stockQuantity > 0) {
        throw new BadRequestHttpException('This item is already in stock.');
      }
    } elseif ($product->variants->isNotEmpty()) {
      throw new BadRequestHttpException('Select a variant to get notified.');
    } elseif ($product->stockQuantity > 0) {
      throw new BadRequestHttpException('This item is already in stock.');
    }

    $existing = StockAlert::query()
      ->where('email', $email)
      ->where('productId', $product->id)
      ->where('variantId', $variantId)
      ->first();

    if ($existing) {
      if (! empty($params['userId'])) {
        $existing->update(['userId' => $params['userId']]);
      }

      return $existing->fresh();
    }

    return StockAlert::query()->create([
      'email' => $email,
      'productId' => $product->id,
      'variantId' => $variantId,
      'userId' => $params['userId'] ?? null,
    ]);
  }
}
