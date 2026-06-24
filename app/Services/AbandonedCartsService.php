<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AbandonedCart;
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
   * @return \Illuminate\Database\Eloquent\Collection<int, AbandonedCart>
   */
    public function findAll()
    {
        return AbandonedCart::query()
            ->whereNull('recoveredAt')
            ->orderByDesc('lastActivityAt')
            ->limit(100)
            ->get();
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
