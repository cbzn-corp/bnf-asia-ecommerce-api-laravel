<?php

use App\Http\Controllers\Api\ReviewsController;
use Illuminate\Support\Facades\Route;

Route::prefix('reviews')->group(function () {
    Route::get('product/{productId}', [ReviewsController::class, 'findByProduct']);
    Route::post('/', [ReviewsController::class, 'create'])->middleware('bnf.authenticate.optional');

    Route::middleware(['bnf.authenticate', 'staff', 'permissions:reviews.manage'])->group(function () {
        Route::get('pending', [ReviewsController::class, 'findPending']);
        Route::patch('{id}/approve', [ReviewsController::class, 'approve']);
        Route::delete('{id}', [ReviewsController::class, 'remove']);
    });
});
