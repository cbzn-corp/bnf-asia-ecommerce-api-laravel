<?php

use App\Http\Controllers\Api\WishlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('wishlist')->middleware(['bnf.authenticate', 'require.customer'])->group(function () {
    Route::get('/', [WishlistController::class, 'findMine']);
    Route::post('sync', [WishlistController::class, 'sync']);
    Route::post('{productId}', [WishlistController::class, 'add']);
    Route::delete('{productId}', [WishlistController::class, 'remove']);
});
