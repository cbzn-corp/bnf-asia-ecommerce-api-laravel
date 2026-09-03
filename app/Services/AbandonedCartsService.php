<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AbandonedCart;
use App\Models\User;
use App\Services\Email\EmailService;
use App\Services\Settings\PlatformSettingsService;
use App\Support\Config\AppUrls;

class AbandonedCartsService
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
        private readonly EmailService $emailService,
    ) {}

    /**
     * @param  array{email?: string|null, userId?: string|null, items: array<int, mixed>}  $params
     */
    public function upsert(array $params): ?AbandonedCart
    {
        if (empty($params['email']) && empty($params['userId'])) {
            return null;
        }

        $settings = $this->platformSettings->getRaw();
        if (! $settings->abandonedCartEnabled) {
            return null;
        }

        $existing = null;
        if (! empty($params['email'])) {
            $existing = AbandonedCart::query()
                ->where('email', strtolower($params['email']))
                ->whereNull('recoveredAt')
                ->orderByDesc('lastActivityAt')
                ->first();
        }

        if ($existing) {
            $existing->update([
                'items' => $params['items'],
                'lastActivityAt' => now(),
                'userId' => $params['userId'] ?? $existing->userId,
            ]);

            return $existing->fresh();
        }

        return AbandonedCart::query()->create([
            'email' => isset($params['email']) ? strtolower($params['email']) : null,
            'userId' => $params['userId'] ?? null,
            'items' => $params['items'],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(): array
    {
        $carts = AbandonedCart::query()
            ->whereNull('recoveredAt')
            ->orderByDesc('lastActivityAt')
            ->limit(100)
            ->get();

        $userIds = $carts->pluck('userId')->filter()->unique()->values()->all();
        $users = $userIds === []
            ? collect()
            : User::query()->whereIn('id', $userIds)->get(['id', 'email'])->keyBy('id');

        return $carts->map(function (AbandonedCart $cart) use ($users) {
            $user = $cart->userId ? $users->get($cart->userId) : null;
            $items = is_array($cart->items) ? $cart->items : [];
            $lineItems = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $qty = (int) ($item['qty'] ?? $item['quantity'] ?? 1);
                $price = (float) ($item['priceInPHP'] ?? 0);
                $lineItems[] = [
                    'productId' => $item['productId'] ?? null,
                    'variantId' => $item['variantId'] ?? null,
                    'name' => $item['name'] ?? 'Item',
                    'variantName' => $item['variantName'] ?? null,
                    'slug' => $item['slug'] ?? null,
                    'qty' => $qty,
                    'priceInPHP' => $price,
                    'lineTotalInPHP' => $price * $qty,
                    'image' => $item['image'] ?? null,
                ];
            }

            return [
                'id' => $cart->id,
                'email' => $cart->email ?? $user?->email,
                'userId' => $cart->userId,
                'customerEmail' => $user?->email ?? $cart->email,
                'items' => $cart->items,
                'lineItems' => $lineItems,
                'itemCount' => array_sum(array_column($lineItems, 'qty')),
                'lastActivityAt' => $cart->lastActivityAt,
                'recoveryEmailSentAt' => $cart->recoveryEmailSentAt,
                'recoveryToken' => $cart->recoveryToken,
            ];
        })->all();
    }

    /**
     * @return array{sent: int}
     */
    public function sendRecoveryEmails(): array
    {
        $settings = $this->platformSettings->getRaw();
        if (! $settings->abandonedCartEnabled) {
            return ['sent' => 0];
        }

        $cutoff = now()->subHours($settings->abandonedCartHours);
        $carts = AbandonedCart::query()
            ->whereNull('recoveredAt')
            ->whereNotNull('email')
            ->whereNull('recoveryEmailSentAt')
            ->where('lastActivityAt', '<=', $cutoff)
            ->limit(50)
            ->get();

        $baseUrl = AppUrls::getStorefrontUrl();
        $sent = 0;

        foreach ($carts as $cart) {
            if (! $cart->email) {
                continue;
            }

            $promo = trim((string) ($settings->abandonedCartDiscountCode ?? ''));
            $recoveryUrl = $promo !== ''
                ? "{$baseUrl}/cart?recover={$cart->recoveryToken}&promo=".rawurlencode($promo)
                : "{$baseUrl}/cart?recover={$cart->recoveryToken}";

            $this->emailService->sendTemplateEmail('abandoned_cart', $cart->email, [
                'recoveryUrl' => $recoveryUrl,
                'email' => $cart->email,
                'discountCode' => $promo,
            ]);

            $cart->update(['recoveryEmailSentAt' => now()]);
            $sent++;
        }

        return ['sent' => $sent];
    }

    public function recoverByToken(string $token): ?AbandonedCart
    {
        $cart = AbandonedCart::query()->where('recoveryToken', $token)->first();
        if (! $cart || $cart->recoveredAt) {
            return null;
        }

        return $cart;
    }

    public function markRecovered(string $email): int
    {
        return AbandonedCart::query()
            ->where('email', strtolower($email))
            ->whereNull('recoveredAt')
            ->update(['recoveredAt' => now()]);
    }

    public function findLatestByUser(string $userId): ?AbandonedCart
    {
        return AbandonedCart::query()
            ->where('userId', $userId)
            ->whereNull('recoveredAt')
            ->orderByDesc('lastActivityAt')
            ->first();
    }
}
