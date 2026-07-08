<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ReferralCommissionStatus;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReferralClick;
use App\Models\ReferralCommission;
use App\Models\ReferralPartner;
use App\Models\ReferralPartnerProduct;
use App\Support\Utils\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReferralsService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findAllPartners(?string $search = null): array
    {
        $query = ReferralPartner::query()
            ->with(['products.product:id,name,slug,sku'])
            ->orderByDesc('createdAt');

        if ($search !== null && trim($search) !== '') {
            $needle = '%'.trim($search).'%';
            $query->where(function (Builder $q) use ($needle) {
                $q->where('name', 'ilike', $needle)
                    ->orWhere('code', 'ilike', $needle)
                    ->orWhere('email', 'ilike', $needle);
            });
        }

        return $query->get()->map(fn (ReferralPartner $row) => $this->serializePartner($row))->all();
    }

    /**
     * @param  array<string, mixed>  $dto
     * @return array<string, mixed>
     */
    public function createPartner(array $dto): array
    {
        $productIds = $this->normalizeProductIds($dto['productIds'] ?? []);
        if ($productIds === []) {
            throw new BadRequestException('At least one product is required.');
        }
        $this->ensureProductsExist($productIds);

        $partner = DB::transaction(function () use ($dto, $productIds) {
            $partner = ReferralPartner::query()->create([
                'name' => trim($dto['name']),
                'code' => $this->normalizeCode($dto['code']),
                'email' => ! empty($dto['email']) ? strtolower(trim($dto['email'])) : null,
                'commissionRate' => $dto['commissionRate'] ?? 5,
                'isActive' => $dto['isActive'] ?? true,
                'notes' => $dto['notes'] ?? null,
            ]);

            foreach ($productIds as $productId) {
                ReferralPartnerProduct::query()->create([
                    'partnerId' => $partner->id,
                    'productId' => $productId,
                ]);
            }

            return $partner->load(['products.product:id,name,slug,sku']);
        });

        return $this->serializePartner($partner);
    }

    /**
     * @param  array<string, mixed>  $dto
     * @return array<string, mixed>
     */
    public function updatePartner(string $id, array $dto): array
    {
        $this->ensurePartnerExists($id);
        $productIds = array_key_exists('productIds', $dto)
            ? $this->normalizeProductIds($dto['productIds'] ?? [])
            : null;

        if ($productIds !== null) {
            if ($productIds === []) {
                throw new BadRequestException('At least one product is required.');
            }
            $this->ensureProductsExist($productIds);
        }

        $partner = DB::transaction(function () use ($id, $dto, $productIds) {
            if ($productIds !== null) {
                ReferralPartnerProduct::query()->where('partnerId', $id)->delete();
                foreach ($productIds as $productId) {
                    ReferralPartnerProduct::query()->create([
                        'partnerId' => $id,
                        'productId' => $productId,
                    ]);
                }
            }

            $updates = array_filter([
                'name' => isset($dto['name']) ? trim($dto['name']) : null,
                'code' => isset($dto['code']) ? $this->normalizeCode($dto['code']) : null,
                'email' => array_key_exists('email', $dto)
                    ? (! empty($dto['email']) ? strtolower(trim($dto['email'])) : null)
                    : null,
                'commissionRate' => $dto['commissionRate'] ?? null,
                'isActive' => $dto['isActive'] ?? null,
                'notes' => array_key_exists('notes', $dto) ? $dto['notes'] : null,
            ], fn ($v) => $v !== null);

            ReferralPartner::query()->where('id', $id)->update($updates);

            return ReferralPartner::query()
                ->with(['products.product:id,name,slug,sku'])
                ->findOrFail($id);
        });

        return $this->serializePartner($partner);
    }

    public function removePartner(string $id): array
    {
        $this->ensurePartnerExists($id);
        ReferralPartner::query()->where('id', $id)->delete();

        return ['deleted' => true];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPartnerStats(string $id): array
    {
        $partner = ReferralPartner::query()
            ->with(['products.product:id,name,slug,sku'])
            ->find($id);

        if (! $partner) {
            throw new NotFoundHttpException("Referral partner not found: {$id}");
        }

        $clickCount = ReferralClick::query()->where('partnerId', $id)->count();
        $orderCount = Order::query()
            ->where('referralPartnerId', $id)
            ->where('paymentStatus', PaymentStatus::Paid)
            ->count();
        $commissionTotal = ReferralCommission::query()
            ->where('partnerId', $id)
            ->where('status', ReferralCommissionStatus::Recorded)
            ->sum('commissionAmountInPHP');

        return [
            ...$this->serializePartner($partner),
            'clickCount' => $clickCount,
            'orderCount' => $orderCount,
            'commissionTotalInPHP' => (float) $commissionTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $dto
     */
    public function recordClick(array $dto): array
    {
        $code = $this->normalizeCode($dto['code']);
        $partner = ReferralPartner::query()
            ->where('code', $code)
            ->where('isActive', true)
            ->first();

        if (! $partner) {
            return ['recorded' => false];
        }

        $sessionId = isset($dto['sessionId']) ? trim($dto['sessionId']) : null;
        if ($sessionId) {
            $recent = ReferralClick::query()
                ->where('partnerId', $partner->id)
                ->where('sessionId', $sessionId)
                ->where('createdAt', '>=', now()->subMinutes(30))
                ->exists();
            if ($recent) {
                return ['recorded' => false, 'deduped' => true];
            }
        }

        $productId = $dto['productId'] ?? null;
        if ($productId && ! Product::query()->where('id', $productId)->exists()) {
            $productId = null;
        }

        ReferralClick::query()->create([
            'partnerId' => $partner->id,
            'productId' => $productId,
            'landingPath' => isset($dto['landingPath']) ? trim($dto['landingPath']) : null,
            'sessionId' => $sessionId,
        ]);

        return ['recorded' => true];
    }

    /**
     * @return array{partnerId: string, referralCode: string}|null
     */
    public function resolvePartnerForCheckout(?string $code): ?array
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $partner = ReferralPartner::query()
            ->where('code', $this->normalizeCode($code))
            ->where('isActive', true)
            ->first();

        if (! $partner) {
            return null;
        }

        return [
            'partnerId' => $partner->id,
            'referralCode' => $partner->code,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllCommissions(?string $partnerId = null): array
    {
        $query = ReferralCommission::query()
            ->with([
                'partner:id,name,code',
                'order:id,orderNumber,guestEmail,totalAmountInPHP,paymentStatus,createdAt',
            ])
            ->orderByDesc('createdAt');

        if ($partnerId) {
            $query->where('partnerId', $partnerId);
        }

        return $query->get()->map(fn (ReferralCommission $row) => $this->serializeCommission($row))->all();
    }

    public function onOrderPaid(Order $order): void
    {
        if (! $order->referralPartnerId) {
            return;
        }

        if ($order->paymentStatus !== PaymentStatus::Paid) {
            return;
        }

        if (ReferralCommission::query()->where('orderId', $order->id)->exists()) {
            return;
        }

        $order->loadMissing(['orderItems', 'referralPartner.products']);

        $partner = $order->referralPartner;
        if (! $partner) {
            return;
        }

        $eligibleProductIds = $partner->products->pluck('productId')->flip()->all();
        if ($eligibleProductIds === []) {
            return;
        }

        $eligibleItems = $order->orderItems->filter(
            fn ($item) => isset($eligibleProductIds[$item->productId]),
        );

        if ($eligibleItems->isEmpty()) {
            return;
        }

        $eligibleSubtotal = '0.00';
        $lineSnapshots = [];
        foreach ($eligibleItems as $item) {
            $lineTotal = Decimal::of($item->totalPriceInPHP);
            $eligibleSubtotal = Decimal::add($eligibleSubtotal, $lineTotal);
            $lineSnapshots[] = [
                'productId' => $item->productId,
                'quantity' => $item->quantity,
                'lineTotalInPHP' => (float) $lineTotal,
            ];
        }

        $orderSubtotal = Decimal::of($order->subtotalInPHP);
        $discountAmount = Decimal::of($order->discountAmountInPHP);
        $commissionBase = $eligibleSubtotal;
        if (Decimal::gt($discountAmount, '0') && Decimal::gt($orderSubtotal, '0')) {
            $discountShare = Decimal::div(Decimal::mul($discountAmount, $eligibleSubtotal), $orderSubtotal);
            $commissionBase = Decimal::sub($eligibleSubtotal, $discountShare);
        }

        if (! Decimal::gt($commissionBase, '0')) {
            return;
        }

        $rate = Decimal::of($partner->commissionRate);
        $commissionAmount = Decimal::div(Decimal::mul($commissionBase, $rate), 100);

        ReferralCommission::query()->create([
            'partnerId' => $partner->id,
            'orderId' => $order->id,
            'eligibleSubtotalInPHP' => $commissionBase,
            'commissionRate' => $partner->commissionRate,
            'commissionAmountInPHP' => $commissionAmount,
            'lineItems' => $lineSnapshots,
            'status' => ReferralCommissionStatus::Recorded,
        ]);
    }

    public function onOrderRefundStatusChanged(Order $order): void
    {
        if (! $order->referralPartnerId) {
            return;
        }

        if ($order->refundStatus === RefundStatus::Processed || $order->paymentStatus === PaymentStatus::Refunded) {
            ReferralCommission::query()
                ->where('orderId', $order->id)
                ->where('status', ReferralCommissionStatus::Recorded)
                ->update(['status' => ReferralCommissionStatus::Cancelled]);
        }
    }

    /**
     * Create missing commission rows for already-paid referred orders.
     *
     * @return array{processed: int, created: int}
     */
    public function backfillCommissions(): array
    {
        $processed = 0;
        $createdBefore = ReferralCommission::query()->count();

        Order::query()
            ->whereNotNull('referralPartnerId')
            ->where('paymentStatus', PaymentStatus::Paid)
            ->orderBy('createdAt')
            ->chunkById(50, function ($orders) use (&$processed) {
                foreach ($orders as $order) {
                    $processed++;
                    $this->onOrderPaid($order);
                }
            });

        return [
            'processed' => $processed,
            'created' => ReferralCommission::query()->count() - $createdBefore,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePartner(ReferralPartner $row): array
    {
        $data = $row->toArray();
        $data['productIds'] = $row->products->pluck('productId')->values()->all();
        $data['products'] = $row->products->map(fn ($link) => $link->product)->filter()->values()->all();
        $data['clickCount'] = $row->relationLoaded('clicks')
            ? $row->clicks->count()
            : ReferralClick::query()->where('partnerId', $row->id)->count();
        $data['orderCount'] = Order::query()
            ->where('referralPartnerId', $row->id)
            ->where('paymentStatus', PaymentStatus::Paid)
            ->count();
        $data['commissionTotalInPHP'] = (float) ReferralCommission::query()
            ->where('partnerId', $row->id)
            ->where('status', ReferralCommissionStatus::Recorded)
            ->sum('commissionAmountInPHP');

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCommission(ReferralCommission $row): array
    {
        $data = $row->toArray();
        $data['partner'] = $row->partner ? [
            'id' => $row->partner->id,
            'name' => $row->partner->name,
            'code' => $row->partner->code,
        ] : null;
        $data['order'] = $row->order ? [
            'id' => $row->order->id,
            'orderNumber' => $row->order->orderNumber,
            'guestEmail' => $row->order->guestEmail,
            'totalAmountInPHP' => (float) $row->order->totalAmountInPHP,
            'paymentStatus' => $row->order->paymentStatus->value,
            'createdAt' => $row->order->createdAt?->toISOString(),
        ] : null;

        return $data;
    }

    /**
     * @param  list<string>|null  $productIds
     * @return list<string>
     */
    private function normalizeProductIds(?array $productIds): array
    {
        if ($productIds === null || $productIds === []) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (string $id) => trim($id),
            $productIds,
        ))));
    }

    /**
     * @param  list<string>  $productIds
     */
    private function ensureProductsExist(array $productIds): void
    {
        $count = Product::query()->whereIn('id', $productIds)->count();
        if ($count !== count($productIds)) {
            throw new BadRequestException('One or more products are invalid.');
        }
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($code)) ?? trim($code));
    }

    private function ensurePartnerExists(string $id): ReferralPartner
    {
        $row = ReferralPartner::query()->find($id);
        if (! $row) {
            throw new NotFoundHttpException("Referral partner not found: {$id}");
        }

        return $row;
    }
}
