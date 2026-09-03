<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CartAddEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CartEventsService
{
    /**
     * @param  array{
     *   productId: string,
     *   variantId?: string|null,
     *   productName: string,
     *   variantName?: string|null,
     *   slug: string,
     *   qty: int,
     *   priceInPHP: float|int|string
     * }  $params
     */
    public function recordAdd(string $userId, array $params): CartAddEvent
    {
        return CartAddEvent::query()->create([
            'userId' => $userId,
            'productId' => $params['productId'],
            'variantId' => $params['variantId'] ?? null,
            'productName' => $params['productName'],
            'variantName' => $params['variantName'] ?? null,
            'slug' => $params['slug'],
            'qty' => max(1, (int) $params['qty']),
            'priceInPHP' => $params['priceInPHP'],
        ]);
    }

    /**
     * @return array{
     *   totals: array{addEvents: int, unitsAdded: int, uniqueCustomers: int, uniqueProducts: int},
     *   byProduct: list<array<string, mixed>>,
     *   byCustomer: list<array<string, mixed>>
     * }
     */
    public function report(?string $from, ?string $to): array
    {
        $start = $from
            ? Carbon::parse($from)->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
        $end = $to
            ? Carbon::parse($to)->endOfDay()
            : Carbon::now()->endOfDay();

        $base = CartAddEvent::query()
            ->whereBetween('createdAt', [$start, $end]);

        $totals = [
            'addEvents' => (clone $base)->count(),
            'unitsAdded' => (int) (clone $base)->sum('qty'),
            'uniqueCustomers' => (int) (clone $base)->selectRaw('COUNT(DISTINCT "userId") as aggregate')->value('aggregate'),
            'uniqueProducts' => (int) (clone $base)->selectRaw('COUNT(DISTINCT "productId") as aggregate')->value('aggregate'),
        ];

        $byProduct = CartAddEvent::query()
            ->select([
                'productId',
                DB::raw('MAX("productName") as "productName"'),
                DB::raw('MAX("slug") as "slug"'),
                DB::raw('SUM(qty) as "unitsAdded"'),
                DB::raw('COUNT(*) as "addEvents"'),
                DB::raw('COUNT(DISTINCT "userId") as "uniqueCustomers"'),
            ])
            ->whereBetween('createdAt', [$start, $end])
            ->groupBy('productId')
            ->orderByDesc(DB::raw('SUM(qty)'))
            ->limit(100)
            ->get()
            ->map(fn ($row) => [
                'productId' => $row->productId,
                'productName' => $row->productName,
                'slug' => $row->slug,
                'unitsAdded' => (int) $row->unitsAdded,
                'addEvents' => (int) $row->addEvents,
                'uniqueCustomers' => (int) $row->uniqueCustomers,
            ])
            ->all();

        $byCustomerRows = CartAddEvent::query()
            ->select([
                'userId',
                DB::raw('SUM(qty) as "unitsAdded"'),
                DB::raw('COUNT(*) as "addEvents"'),
                DB::raw('COUNT(DISTINCT "productId") as "uniqueProducts"'),
                DB::raw('MAX("createdAt") as "lastActivityAt"'),
            ])
            ->whereBetween('createdAt', [$start, $end])
            ->groupBy('userId')
            ->orderByDesc(DB::raw('SUM(qty)'))
            ->limit(100)
            ->get();

        $userIds = $byCustomerRows->pluck('userId')->filter()->all();
        $users = $userIds === []
            ? collect()
            : User::query()->whereIn('id', $userIds)->get(['id', 'email'])->keyBy('id');

        $byCustomer = $byCustomerRows->map(function ($row) use ($users) {
            $user = $users->get($row->userId);

            return [
                'userId' => $row->userId,
                'email' => $user?->email,
                'unitsAdded' => (int) $row->unitsAdded,
                'addEvents' => (int) $row->addEvents,
                'uniqueProducts' => (int) $row->uniqueProducts,
                'lastActivityAt' => $row->lastActivityAt,
            ];
        })->all();

        return [
            'totals' => $totals,
            'byProduct' => $byProduct,
            'byCustomer' => $byCustomer,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
        ];
    }
}
