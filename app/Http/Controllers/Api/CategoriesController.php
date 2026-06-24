<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Categories\CreateCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Services\Categories\CategoriesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    public function __construct(
        private readonly CategoriesService $categoriesService,
    ) {}

    public function findTreePublic(): JsonResponse
    {
        return response()->json($this->categoriesService->findTreePublic());
    }

    public function findAllPublic(): JsonResponse
    {
        return response()->json($this->categoriesService->findAllPublic());
    }

    public function findTree(Request $request): JsonResponse
    {
        return response()->json($this->categoriesService->findTree($request->query('search')));
    }

    public function findAll(Request $request): JsonResponse
    {
        return response()->json($this->categoriesService->findAll($request->query('search')));
    }

    public function findOne(string $id): JsonResponse
    {
        return response()->json($this->categoriesService->findOne($id));
    }

    public function create(CreateCategoryRequest $request): JsonResponse
    {
        return response()->json($this->categoriesService->create($request->validated()));
    }

    public function uploadCoverImage(Request $request, string $id): JsonResponse
    {
        return response()->json(
            $this->categoriesService->uploadCoverImage($id, $request->file('image')),
        );
    }

    public function update(UpdateCategoryRequest $request, string $id): JsonResponse
    {
        return response()->json($this->categoriesService->update($id, $request->validated()));
    }

    public function remove(string $id): JsonResponse
    {
        return response()->json($this->categoriesService->remove($id));
    }
}
