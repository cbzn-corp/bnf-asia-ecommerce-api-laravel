<?php

namespace App\Services;

use App\Enums\PromotionType;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionProduct;
use App\Support\Utils\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PromotionsService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(?string $search = null): array
    {
        $query = Promotion::query()
            ->with(['eligibleProducts.product:id,name,slug,sku'])
            ->orderByDesc('createdAt');

        if ($search !== null && trim($search) !== '') {
            $query->where('code', 'ilike', '%'.trim($search).'%');
        }

        return $query->get()->map(fn (Promotion $row) => $this->serializePromotion($row))->all();
    }

    /**
     * @param  array<string, mixed>  $dto
     * @return array<string, mixed>
     */
    public function create(array $dto): array
    {
        $productIds = $this->normalizeProductIds($dto['productIds'] ?? []);
        $this->ensureProductsExist($productIds);

        $promo = DB::transaction(function () use ($dto, $productIds) {
            $promo = Promotion::query()->create([
                'code' => strtoupper(trim($dto['code'])),
                'description' => $dto['description'] ?? null,
                'type' => $dto['type'],
                'value' => $dto['value'],
                'minOrderPHP' => $dto['minOrderPHP'] ?? 0,
                'maxUses' => $dto['maxUses'] ?? null,
                'oneUsePerAccount' => $dto['oneUsePerAccount'] ?? false,
                'startsAt' => ! empty($dto['startsAt']) ? $dto['startsAt'] : null,
                'expiresAt' => ! empty($dto['expiresAt']) ? $dto['expiresAt'] : null,
                'isActive' => $dto['isActive'] ?? true,
            ]);

            if ($productIds !== []) {
                foreach ($productIds as $productId) {
                    PromotionProduct::query()->create([
                        'promotionId' => $promo->id,
                        'productId' => $productId,
                    ]);
                }
            }

            return $promo->load(['eligibleProducts.product:id,name,slug,sku']);
        });

        return $this->serializePromotion($promo);
    }

    /**
     * @param  array<string, mixed>  $dto
     * @return array<string, mixed>
     */
    public function update(string $id, array $dto): array
    {
        $this->ensureExists($id);
        $productIds = array_key_exists('productIds', $dto)
            ? $this->normalizeProductIds($dto['productIds'] ?? [])
            : null;

        if ($productIds !== null) {
            $this->ensureProductsExist($productIds);
        }

        $promo = DB::transaction(function () use ($id, $dto, $productIds) {
            if ($productIds !== null) {
                PromotionProduct::query()->where('promotionId', $id)->delete();
                foreach ($productIds as $productId) {
                    PromotionProduct::query()->create([
                        'promotionId' => $id,
                        'productId' => $productId,
                    ]);
                }
            }

            $updates = array_filter([
                'code' => isset($dto['code']) ? strtoupper(trim($dto['code'])) : null,
                'description' => $dto['description'] ?? null,
                'type' => $dto['type'] ?? null,
                'value' => $dto['value'] ?? null,
                'minOrderPHP' => $dto['minOrderPHP'] ?? null,
                'maxUses' => $dto['maxUses'] ?? null,
                'oneUsePerAccount' => $dto['oneUsePerAccount'] ?? null,
                'isActive' => $dto['isActive'] ?? null,
                'startsAt' => array_key_exists('startsAt', $dto)
                    ? (! empty($dto['startsAt']) ? $dto['startsAt'] : null)
                    : null,
                'expiresAt' => array_key_exists('expiresAt', $dto)
                    ? (! empty($dto['expiresAt']) ? $dto['expiresAt'] : null)
                    : null,
            ], fn ($v) => $v !== null);

            Promotion::query()->where('id', $id)->update($updates);

            return Promotion::query()
                ->with(['eligibleProducts.product:id,name,slug,sku'])
                ->findOrFail($id);
        });

        return $this->serializePromotion($promo);
    }

    public function remove(string $id): array
    {
        $this->ensureExists($id);
        Promotion::query()->where('id', $id)->delete();

        return ['deleted' => true];
    }

    /**
     * @param  list<array{productId: string, lineTotalInPHP: float|string}>  $pricedItems
     * @return array{discountInPHP: string, promotionCode: string|null, promotion: Promotion|null}
     */
    public function validateAndCalculate(?string $code, array $pricedItems, ?string $userId = null): array
    {
        if ($code === null || trim($code) === '') {
            return ['discountInPHP' => '0.00', 'promotionCode' => null, 'promotion' => null];
        }

        $promo = Promotion::query()
            ->with('eligibleProducts')
            ->where('code', strtoupper(trim($code)))
            ->first();

        if (! $promo || ! $promo->isActive) {
            throw new BadRequestException('Invalid or inactive voucher code.');
        }

        $now = now();
        if ($promo->startsAt && $promo->startsAt->gt($now)) {
            throw new BadRequestException('This voucher is not active yet.');
        }
        if ($promo->expiresAt && $promo->expiresAt->lt($now)) {
            throw new BadRequestException('This voucher has expired.');
        }
        if ($promo->maxUses !== null && $promo->usedCount >= $promo->maxUses) {
            throw new BadRequestException('This voucher has reached its usage limit.');
        }

        if ($promo->oneUsePerAccount) {
            if (! $userId) {
                throw new BadRequestException('Sign in to use this voucher.');
            }
            $priorUse = Order::query()
                ->where('userId', $userId)
                ->where('promotionCode', $promo->code)
                ->count();
            if ($priorUse > 0) {
                throw new BadRequestException('You have already used this voucher.');
            }
        }

        $eligibleIds = $promo->eligibleProducts->pluck('productId')->flip()->all();
        $cartSubtotal = '0.00';
        foreach ($pricedItems as $item) {
            $cartSubtotal = Decimal::add($cartSubtotal, $item['lineTotalInPHP']);
        }

        $discountBase = $cartSubtotal;
        if ($promo->eligibleProducts->isNotEmpty()) {
            $matching = array_filter($pricedItems, fn ($item) => isset($eligibleIds[$item['productId']]));
            if ($matching === []) {
                throw new BadRequestException('This voucher does not apply to items in your cart.');
            }
            $discountBase = '0.00';
            foreach ($matching as $item) {
                $discountBase = Decimal::add($discountBase, $item['lineTotalInPHP']);
            }
        }

        if (Decimal::lt($discountBase, $promo->minOrderPHP)) {
            $min = number_format((float) $promo->minOrderPHP, 0, '.', ',');
            throw new BadRequestException("Minimum order of ₱{$min} required for this voucher.");
        }

        if ($promo->type === PromotionType::Percent) {
            $discountInPHP = Decimal::div(Decimal::mul($discountBase, $promo->value), 100);
        } else {
            $discountInPHP = Decimal::of($promo->value);
        }

        if (Decimal::gt($discountInPHP, $cartSubtotal)) {
            $discountInPHP = $cartSubtotal;
        }

        return [
            'discountInPHP' => $discountInPHP,
            'promotionCode' => $promo->code,
            'promotion' => $promo,
        ];
    }

    public function incrementUsage(string $code): void
    {
        Promotion::query()->where('code', $code)->increment('usedCount');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePromotion(Promotion $row): array
    {
        $data = $row->toArray();
        $data['eligibleProductIds'] = $row->eligibleProducts->pluck('productId')->values()->all();
        $data['eligibleProducts'] = $row->eligibleProducts->map(fn ($link) => $link->product)->filter()->values()->all();

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
        if ($productIds === []) {
            return;
        }
        $count = Product::query()->whereIn('id', $productIds)->count();
        if ($count !== count($productIds)) {
            throw new BadRequestException('One or more eligible products are invalid.');
        }
    }

    private function ensureExists(string $id): Promotion
    {
        $row = Promotion::query()->find($id);
        if (! $row) {
            throw new NotFoundHttpException("Voucher not found: {$id}");
        }

        return $row;
    }
}
