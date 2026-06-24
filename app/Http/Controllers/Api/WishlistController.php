<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesAuthUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wishlist\SyncWishlistRequest;
use App\Services\Wishlist\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    use ResolvesAuthUser;

    public function __construct(
        private readonly WishlistService $wishlistService,
    ) {}

    public function findMine(Request $request): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json($this->wishlistService->findByUser($user->id));
    }

    public function sync(SyncWishlistRequest $request): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json(
            $this->wishlistService->sync($user->id, $request->validated('productIds') ?? []),
        );
    }

    public function add(Request $request, string $productId): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json($this->wishlistService->add($user->id, $productId));
    }

    public function remove(Request $request, string $productId): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json($this->wishlistService->remove($user->id, $productId));
    }
}
