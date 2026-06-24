<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Collections\CreateCollectionRequest;
use App\Http\Requests\Collections\UpdateCollectionRequest;
use App\Services\Collections\CollectionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollectionsController extends Controller
{
    public function __construct(
        private readonly CollectionsService $collectionsService,
    ) {}

    public function findPublic(): JsonResponse
    {
        return response()->json($this->collectionsService->findAllPublic());
    }

    public function findBySlug(string $slug): JsonResponse
    {
        return response()->json($this->collectionsService->findBySlugPublic($slug));
    }

    public function findAllAdmin(): JsonResponse
    {
        return response()->json($this->collectionsService->findAllAdmin());
    }

    public function create(CreateCollectionRequest $request): JsonResponse
    {
        return response()->json($this->collectionsService->create($request->validated()));
    }

    public function uploadCoverImage(Request $request, string $id): JsonResponse
    {
        return response()->json(
            $this->collectionsService->uploadCoverImage($id, $request->file('image')),
        );
    }

    public function update(UpdateCollectionRequest $request, string $id): JsonResponse
    {
        return response()->json($this->collectionsService->update($id, $request->validated()));
    }

    public function remove(string $id): JsonResponse
    {
        return response()->json($this->collectionsService->remove($id));
    }
}
