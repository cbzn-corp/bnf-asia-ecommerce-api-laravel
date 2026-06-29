<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Products\BatchCreateProductsRequest;
use App\Http\Requests\Products\BatchCreateVariantsRequest;
use App\Http\Requests\Products\BatchUpdateProductsRequest;
use App\Http\Requests\Products\BatchUpdateVariantsRequest;
use App\Http\Requests\Products\CreateProductRequest;
use App\Http\Requests\Products\CreateVariantRequest;
use App\Http\Requests\Products\ProductQueryRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Requests\Products\UpdateVariantRequest;
use App\Services\Products\ProductsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    public function __construct(
        private readonly ProductsService $productsService,
    ) {}

    public function storageStatus(): JsonResponse
    {
        return response()->json($this->productsService->storageStatus());
    }

    public function autocomplete(Request $request): JsonResponse
    {
        return response()->json($this->productsService->autocomplete($request->query('q')));
    }

    public function findAll(ProductQueryRequest $request): JsonResponse
    {
        return response()->json($this->productsService->findAll($request->validated()));
    }

    public function findBySlug(string $slug): JsonResponse
    {
        return response()->json($this->productsService->findBySlug($slug));
    }

    public function findAllAdmin(Request $request): JsonResponse
    {
        return response()->json($this->productsService->findAllAdmin(
            $request->query('search'),
            $request->query('categoryId'),
        ));
    }

    public function batchUpdate(BatchUpdateProductsRequest $request): JsonResponse
    {
        return response()->json($this->productsService->batchUpdateProducts($request->validated('updates')));
    }

    public function batchCreate(BatchCreateProductsRequest $request): JsonResponse
    {
        return response()->json($this->productsService->batchCreateProducts($request->validated('items')));
    }

    public function findAllVariantsAdmin(Request $request): JsonResponse
    {
        return response()->json($this->productsService->findAllVariantsAdmin(
            $request->query('search'),
            $request->query('categoryId'),
        ));
    }

    public function batchUpdateVariants(BatchUpdateVariantsRequest $request): JsonResponse
    {
        return response()->json($this->productsService->batchUpdateVariants($request->validated('updates')));
    }

    public function batchCreateVariants(BatchCreateVariantsRequest $request): JsonResponse
    {
        return response()->json($this->productsService->batchCreateVariants($request->validated('items')));
    }

    public function findById(string $id): JsonResponse
    {
        return response()->json($this->productsService->findById($id));
    }

    public function create(CreateProductRequest $request): JsonResponse
    {
        return response()->json($this->productsService->create($request->validated()));
    }

    public function listVariants(string $id): JsonResponse
    {
        return response()->json($this->productsService->listVariants($id));
    }

    public function createVariant(CreateVariantRequest $request, string $id): JsonResponse
    {
        return response()->json($this->productsService->createVariant($id, $request->validated()));
    }

    public function updateVariant(UpdateVariantRequest $request, string $variantId): JsonResponse
    {
        return response()->json($this->productsService->updateVariant($variantId, $request->validated()));
    }

    public function removeVariant(string $variantId): JsonResponse
    {
        return response()->json($this->productsService->removeVariant($variantId));
    }

    public function uploadVariantImages(Request $request, string $variantId): JsonResponse
    {
        $files = $request->file('images', []);
        $files = is_array($files) ? $files : [$files];

        return response()->json(
            $this->productsService->uploadVariantImages($variantId, $files),
        );
    }

    public function uploadImages(Request $request, string $id): JsonResponse
    {
        $files = $request->file('images', []);
        $files = is_array($files) ? $files : [$files];

        return response()->json(
            $this->productsService->uploadImages($id, $files),
        );
    }

    public function uploadDescriptionImage(Request $request, string $id): JsonResponse
    {
        return response()->json(
            $this->productsService->uploadDescriptionImage($id, $request->file('image')),
        );
    }

    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        return response()->json($this->productsService->update($id, $request->validated()));
    }

    public function remove(string $id): JsonResponse
    {
        return response()->json($this->productsService->remove($id));
    }
}
