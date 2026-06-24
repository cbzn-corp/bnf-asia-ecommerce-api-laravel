<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesAuthUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockAlerts\SubscribeStockAlertRequest;
use App\Services\StockAlerts\StockAlertsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAlertsController extends Controller
{
    use ResolvesAuthUser;

    public function __construct(
        private readonly StockAlertsService $stockAlertsService,
    ) {}

    public function subscribe(SubscribeStockAlertRequest $request): JsonResponse
    {
        $user = $this->authUser($request);
        $validated = $request->validated();

        return response()->json($this->stockAlertsService->subscribe([
            'email' => $validated['email'],
            'productId' => $validated['productId'],
            'variantId' => $validated['variantId'] ?? null,
            'userId' => $user?->id,
        ]));
    }
}
