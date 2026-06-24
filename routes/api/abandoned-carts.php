<?php

use App\Http\Controllers\Api\AbandonedCartsController;
use Illuminate\Support\Facades\Route;

Route::prefix('abandoned-carts')->group(function () {
    Route::post('/', [AbandonedCartsController::class, 'upsert']);
    Route::get('recover/{token}', [AbandonedCartsController::class, 'recover']);

    Route::middleware(['bnf.authenticate', 'require.customer'])->group(function () {
        Route::get('mine', [AbandonedCartsController::class, 'findMine']);
    });

    Route::middleware(['bnf.authenticate', 'staff', 'permissions:abandoned_carts.manage'])->group(function () {
        Route::get('/', [AbandonedCartsController::class, 'findAll']);
        Route::post('send-recovery', [AbandonedCartsController::class, 'sendRecovery']);
    });
});
