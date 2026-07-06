<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesAuthUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\CreateReviewRequest;
use App\Services\Reviews\ReviewsService;
use App\Services\Settings\PlatformSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewsController extends Controller
{
    use ResolvesAuthUser;

    public function __construct(
        private readonly ReviewsService $reviewsService,
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    public function findByProduct(string $productId): JsonResponse
    {
        if (! $this->platformSettings->getRaw()->reviewsEnabled) {
            return response()->json([]);
        }

        return response()->json($this->reviewsService->findByProduct($productId));
    }

    public function create(CreateReviewRequest $request): JsonResponse
    {
        $settings = $this->platformSettings->getRaw();
        if (! $settings->reviewsEnabled || ! $settings->reviewsSubmissionEnabled) {
            return response()->json(['message' => 'Product reviews are not accepting submissions.'], 403);
        }

        $user = $this->authUser($request);

        return response()->json(
            $this->reviewsService->create($request->validated(), $user?->id),
        );
    }

    public function findPending(): JsonResponse
    {
        return response()->json($this->reviewsService->findPending());
    }

    public function approve(string $id): JsonResponse
    {
        return response()->json($this->reviewsService->approve($id));
    }

    public function remove(string $id): JsonResponse
    {
        return response()->json($this->reviewsService->remove($id));
    }
}
