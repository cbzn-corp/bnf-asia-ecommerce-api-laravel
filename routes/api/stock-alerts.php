<?php

use App\Http\Controllers\Api\StockAlertsController;
use Illuminate\Support\Facades\Route;

Route::prefix('stock-alerts')->group(function () {
    Route::post('/', [StockAlertsController::class, 'subscribe'])->middleware('bnf.authenticate.optional');
});
