<?php

use App\Http\Controllers\Api\CartEventsController;
use Illuminate\Support\Facades\Route;

Route::prefix('cart-events')->group(function () {
    Route::middleware(['bnf.authenticate', 'require.customer'])->group(function () {
        Route::post('/', [CartEventsController::class, 'record']);
    });

    Route::middleware(['bnf.authenticate', 'staff', 'permissions:abandoned_carts.manage'])->group(function () {
        Route::get('report', [CartEventsController::class, 'report']);
    });
});
