<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Enums\PaymentStatus;
use App\Models\AbandonedCart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Settings\PlatformSettingsService;
use App\Support\Analytics\AnalyticsPeriod;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    /**
     * @param  array{days?: int|null, from?: string|null, to?: string|null}  $options
     * @return array<string, mixed>
     */
    public function getDashboardAnalytics(array $options = []): array
    {
        $period = AnalyticsPeriod::resolve($options);
        $since = $period['since']->format('Y-m-d H:i:s');
        $until = $period['until']->format('Y-m-d H:i:s');

        $orders = Order::query()
            ->whereBetween('createdAt', [$since, $until])
            ->where('paymentStatus', '!=', PaymentStatus::Failed->value)
            ->get(['totalAmountInPHP', 'createdAt', 'paymentStatus']);

        $paidOrders = Order::query()
            ->whereBetween('createdAt', [$since, $until])
            ->where('paymentStatus', PaymentStatus::Paid->value)
            ->selectRaw('SUM("totalAmountInPHP") as revenue, COUNT(*) as count, AVG("totalAmountInPHP") as average')
            ->first();

        $topProducts = OrderItem::query()
            ->select('productId', DB::raw('SUM(quantity) as quantity_sum'), DB::raw('SUM("totalPriceInPHP") as revenue_sum'))
            ->whereHas('order', function ($q) use ($since, $until) {
                $q->whereBetween('createdAt', [$since, $until])
                    ->where('paymentStatus', PaymentStatus::Paid->value);
            })
            ->groupBy('productId')
            ->orderByDesc('revenue_sum')
            ->limit(5)
            ->get();

        $lowStockThreshold = $this->platformSettings->getRaw()->lowStockThreshold;

        $revenueByDay = [];
        foreach ($orders->where('paymentStatus', PaymentStatus::Paid->value) as $order) {
            $key = $order->createdAt->format('Y-m-d');
            $revenueByDay[$key] = ($revenueByDay[$key] ?? 0) + (float) $order->totalAmountInPHP;
        }

        $productIds = $topProducts->pluck('productId')->all();
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        $lowStockProducts = Product::query()
            ->where(function ($q) use ($lowStockThreshold) {
                $q->where(function ($inner) use ($lowStockThreshold) {
                    $inner->where('stockQuantity', '<=', $lowStockThreshold)
                        ->where('stockQuantity', '>', 0);
                })->orWhereHas('variants', function ($v) use ($lowStockThreshold) {
                    $v->where('isActive', true)
                        ->where('stockQuantity', '<=', $lowStockThreshold)
                        ->where('stockQuantity', '>', 0);
                });
            })
            ->with(['variants' => fn ($q) => $q->where('isActive', true)->select('id', 'productId', 'name', 'stockQuantity', 'sku')])
            ->select('id', 'name', 'slug', 'stockQuantity', 'sku')
            ->limit(20)
            ->get();

        $abandonedCount = AbandonedCart::query()
            ->whereNull('recoveredAt')
            ->whereBetween('lastActivityAt', [$since, $until])
            ->count();

        return [
            'periodDays' => $period['periodDays'],
            'periodFrom' => $period['periodFrom'],
            'periodTo' => $period['periodTo'],
            'revenueInPHP' => (float) ($paidOrders->revenue ?? 0),
            'paidOrderCount' => (int) ($paidOrders->count ?? 0),
            'averageOrderValue' => (float) ($paidOrders->average ?? 0),
            'revenueByDay' => collect($revenueByDay)
                ->map(fn ($revenue, $date) => ['date' => $date, 'revenueInPHP' => $revenue])
                ->sortBy('date')
                ->values()
                ->all(),
            'topProducts' => $topProducts->map(function ($row) use ($products) {
                $product = $products->get($row->productId);

                return [
                    'productId' => $row->productId,
                    'name' => $product?->name ?? 'Product',
                    'slug' => $product?->slug,
                    'quantitySold' => (int) ($row->quantity_sum ?? 0),
                    'revenueInPHP' => (float) ($row->revenue_sum ?? 0),
                ];
            })->all(),
            'lowStockProducts' => $lowStockProducts->map(function (Product $p) use ($lowStockThreshold) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'sku' => $p->sku,
                    'stockQuantity' => $p->stockQuantity,
                    'variants' => $p->variants
                        ->filter(fn ($v) => $v->stockQuantity <= $lowStockThreshold)
                        ->values()
                        ->all(),
                ];
            })->all(),
            'abandonedCarts' => $abandonedCount,
        ];
    }
}
