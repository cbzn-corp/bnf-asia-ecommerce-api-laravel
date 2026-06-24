<?php

use App\Http\Controllers\WebhooksController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks')->group(function () {
    Route::post('paymongo', [WebhooksController::class, 'handlePaymongo']);
    Route::post('stripe', [WebhooksController::class, 'handleStripe']);
});
