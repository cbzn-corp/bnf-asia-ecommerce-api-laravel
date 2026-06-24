<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bundles\CreateBundleRequest;
use App\Http\Requests\Bundles\UpdateBundleRequest;
use App\Services\Bundles\BundlesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BundlesController extends Controller
{
    public function __construct(
        private readonly BundlesService $bundlesService,
    ) {}

    public function findAll(): JsonResponse
    {
        return response()->json($this->bundlesService->findAllPublic());
    }

    public function findAllAdmin(): JsonResponse
    {
        return response()->json($this->bundlesService->findAllAdmin());
    }

    public function create(CreateBundleRequest $request): JsonResponse
    {
        return response()->json($this->bundlesService->create($request->validated()));
    }

    public function uploadCoverImage(Request $request, string $id): JsonResponse
    {
        return response()->json(
            $this->bundlesService->uploadCoverImage($id, $request->file('image')),
        );
    }

    public function findBySlug(string $slug): JsonResponse
    {
        return response()->json($this->bundlesService->findBySlug($slug));
    }

    public function update(UpdateBundleRequest $request, string $id): JsonResponse
    {
        return response()->json($this->bundlesService->update($id, $request->validated()));
    }

    public function remove(string $id): JsonResponse
    {
        return response()->json($this->bundlesService->remove($id));
    }
}
