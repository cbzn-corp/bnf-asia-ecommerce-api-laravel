<?php

use App\Http\Controllers\Api\ContentController;
use Illuminate\Support\Facades\Route;

Route::prefix('content')->group(function () {
    Route::get('storefront-shell', [ContentController::class, 'getStorefrontShell']);
    Route::get('homepage', [ContentController::class, 'getHomepage']);
    Route::get('homepage-rendered', [ContentController::class, 'getHomepageRendered']);
    Route::get('storefront-settings', [ContentController::class, 'getStorefrontSettings']);
    Route::get('page-paths', [ContentController::class, 'getContentPagePaths']);
    Route::get('pages/{slug}', [ContentController::class, 'getStaticPage']);
    Route::post('maintenance-bypass', [ContentController::class, 'validateMaintenanceBypass']);

    Route::middleware(['bnf.authenticate', 'staff', 'permissions:homepage.manage'])->group(function () {
        Route::patch('homepage', [ContentController::class, 'updateHomepage']);
        Route::post('homepage/upload/hero', [ContentController::class, 'uploadHomepageHeroImage']);
        Route::delete('homepage/upload/hero', [ContentController::class, 'removeHomepageHeroImage']);
        Route::post('homepage/upload/collection/{index}', [ContentController::class, 'uploadHomepageCollectionImage']);
        Route::delete('homepage/upload/collection/{index}', [ContentController::class, 'removeHomepageCollectionImage']);
        Route::post('homepage/upload/promo-banner/{index}', [ContentController::class, 'uploadHomepagePromoBannerImage']);
        Route::delete('homepage/upload/promo-banner/{index}', [ContentController::class, 'removeHomepagePromoBannerImage']);
        Route::post('homepage/upload/sale-countdown', [ContentController::class, 'uploadHomepageSaleCountdownImage']);
        Route::delete('homepage/upload/sale-countdown', [ContentController::class, 'removeHomepageSaleCountdownImage']);
        Route::patch('storefront-settings', [ContentController::class, 'updateStorefrontSettings']);
        Route::post('storefront-settings/upload/{asset}', [ContentController::class, 'uploadStorefrontAsset']);
        Route::delete('storefront-settings/upload/{asset}', [ContentController::class, 'removeStorefrontAsset']);
        Route::get('pages', [ContentController::class, 'getAllStaticPages']);
        Route::post('pages', [ContentController::class, 'createStaticPage']);
        Route::patch('pages/{slug}', [ContentController::class, 'updateStaticPage']);
        Route::delete('pages/{slug}', [ContentController::class, 'deleteStaticPage']);
        Route::post('pages/upload-image', [ContentController::class, 'uploadContentPageImage']);
        Route::post('pages/upload-video', [ContentController::class, 'uploadContentPageVideo']);
        Route::post('revalidate-storefront', [ContentController::class, 'revalidateStorefront']);
    });
});
