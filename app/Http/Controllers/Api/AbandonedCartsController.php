<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesAuthUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\AbandonedCarts\UpsertAbandonedCartRequest;
use App\Services\AbandonedCartsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbandonedCartsController extends Controller
{
    use ResolvesAuthUser;

    public function __construct(
        private readonly AbandonedCartsService $abandonedCartsService,
    ) {}

    public function upsert(UpsertAbandonedCartRequest $request): JsonResponse
    {
        return response()->json($this->abandonedCartsService->upsert($request->validated()));
    }

    public function findMine(Request $request): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json($this->abandonedCartsService->findLatestByUser($user->id));
    }

    public function recover(string $token): JsonResponse
    {
        return response()->json($this->abandonedCartsService->recoverByToken($token));
    }

    public function findAll(): JsonResponse
    {
        return response()->json($this->abandonedCartsService->findAll());
    }

    public function sendRecovery(): JsonResponse
    {
        return response()->json($this->abandonedCartsService->sendRecoveryEmails());
    }
}
