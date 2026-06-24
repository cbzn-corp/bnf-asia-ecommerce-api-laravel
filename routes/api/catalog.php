<?php

use App\Http\Controllers\Api\BundlesController;
use App\Http\Controllers\Api\CategoriesController;
use App\Http\Controllers\Api\CollectionsController;
use App\Http\Controllers\Api\ProductsController;
use Illuminate\Support\Facades\Route;

Route::prefix('categories')->group(function () {
    Route::get('public/tree', [CategoriesController::class, 'findTreePublic']);
    Route::get('public', [CategoriesController::class, 'findAllPublic']);

    Route::middleware(['bnf.authenticate', 'staff', 'permissions:categories.manage'])->group(function () {
        Route::get('tree', [CategoriesController::class, 'findTree']);
        Route::get('/', [CategoriesController::class, 'findAll']);
        Route::get('{id}', [CategoriesController::class, 'findOne']);
        Route::post('/', [CategoriesController::class, 'create']);
        Route::post('{id}/cover-image', [CategoriesController::class, 'uploadCoverImage']);
        Route::patch('{id}', [CategoriesController::class, 'update']);
        Route::delete('{id}', [CategoriesController::class, 'remove']);
    });
});

Route::prefix('products')->group(function () {
    Route::get('storage-status', [ProductsController::class, 'storageStatus']);
    Route::get('search/autocomplete', [ProductsController::class, 'autocomplete']);
    Route::get('/', [ProductsController::class, 'findAll']);
    Route::get('slug/{slug}', [ProductsController::class, 'findBySlug']);

    Route::middleware(['bnf.authenticate', 'staff', 'permissions:products.manage'])->group(function () {
        Route::get('admin/list', [ProductsController::class, 'findAllAdmin']);
        Route::post('admin/batch-update', [ProductsController::class, 'batchUpdate']);
        Route::post('admin/batch-create', [ProductsController::class, 'batchCreate']);
        Route::get('admin/variants/list', [ProductsController::class, 'findAllVariantsAdmin']);
        Route::post('admin/batch-update-variants', [ProductsController::class, 'batchUpdateVariants']);
        Route::post('admin/batch-create-variants', [ProductsController::class, 'batchCreateVariants']);
        Route::get('item/{id}', [ProductsController::class, 'findById']);
        Route::post('/', [ProductsController::class, 'create']);
        Route::get('{id}/variants', [ProductsController::class, 'listVariants']);
        Route::post('{id}/variants', [ProductsController::class, 'createVariant']);
        Route::patch('variants/{variantId}', [ProductsController::class, 'updateVariant']);
        Route::delete('variants/{variantId}', [ProductsController::class, 'removeVariant']);
        Route::post('variants/{variantId}/images', [ProductsController::class, 'uploadVariantImages']);
        Route::post('{id}/images', [ProductsController::class, 'uploadImages']);
        Route::post('{id}/description-images', [ProductsController::class, 'uploadDescriptionImage']);
        Route::patch('{id}', [ProductsController::class, 'update']);
        Route::delete('{id}', [ProductsController::class, 'remove']);
    });
});

Route::prefix('collections')->group(function () {
    Route::get('public', [CollectionsController::class, 'findPublic']);
    Route::get('public/{slug}', [CollectionsController::class, 'findBySlug']);

    Route::middleware(['bnf.authenticate', 'staff', 'permissions:collections.manage'])->group(function () {
        Route::get('admin/list', [CollectionsController::class, 'findAllAdmin']);
        Route::post('/', [CollectionsController::class, 'create']);
        Route::post('{id}/cover-image', [CollectionsController::class, 'uploadCoverImage']);
        Route::patch('{id}', [CollectionsController::class, 'update']);
        Route::delete('{id}', [CollectionsController::class, 'remove']);
    });
});

Route::prefix('bundles')->group(function () {
    Route::get('/', [BundlesController::class, 'findAll']);

    Route::middleware(['bnf.authenticate', 'staff', 'permissions:bundles.manage'])->group(function () {
        Route::get('admin/list', [BundlesController::class, 'findAllAdmin']);
        Route::post('/', [BundlesController::class, 'create']);
        Route::post('{id}/cover-image', [BundlesController::class, 'uploadCoverImage']);
        Route::patch('{id}', [BundlesController::class, 'update']);
        Route::delete('{id}', [BundlesController::class, 'remove']);
    });

    Route::get('{slug}', [BundlesController::class, 'findBySlug']);
});
