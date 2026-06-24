<?php

use App\Http\Controllers\PromotionsController;
use Illuminate\Support\Facades\Route;

Route::prefix('promotions')->group(function () {
    Route::post('validate', [PromotionsController::class, 'validateCode']);

    Route::middleware(['bnf.authenticate', 'require.permissions:promotions.manage'])->group(function () {
        Route::get('/', [PromotionsController::class, 'findAll']);
        Route::post('/', [PromotionsController::class, 'create']);
        Route::patch('{id}', [PromotionsController::class, 'update']);
        Route::delete('{id}', [PromotionsController::class, 'remove']);
    });
});
