<?php

use App\Http\Controllers\OrdersController;
use Illuminate\Support\Facades\Route;

Route::prefix('orders')->group(function () {
    Route::post('preview', [OrdersController::class, 'preview']);
    Route::get('track', [OrdersController::class, 'track']);

    Route::middleware('bnf.authenticate.optional')->post('checkout', [OrdersController::class, 'checkout']);

    Route::middleware(['bnf.authenticate', 'require.customer'])->group(function () {
        Route::get('my-orders', [OrdersController::class, 'myOrders']);
        Route::get('my-orders/{orderNumber}', [OrdersController::class, 'myOrder']);
        Route::get('my-orders/{orderNumber}/invoice.pdf', [OrdersController::class, 'downloadMyInvoice']);
        Route::post('my-orders/{orderNumber}/cancel-request', [OrdersController::class, 'cancelRequest']);
        Route::post('my-orders/{orderNumber}/return-request', [OrdersController::class, 'returnRequest']);
    });

    Route::middleware(['bnf.authenticate', 'require.permissions:dashboard.view'])->get('stats', [OrdersController::class, 'getStats']);

    Route::middleware(['bnf.authenticate', 'require.permissions:orders.manage'])->group(function () {
        Route::post('manual', [OrdersController::class, 'createManual']);
        Route::get('requests', [OrdersController::class, 'listOrderRequests']);
        Route::get('/', [OrdersController::class, 'findAll']);
        Route::get('{id}/invoice.pdf', [OrdersController::class, 'downloadAdminInvoice']);
        Route::get('{id}', [OrdersController::class, 'findOne']);
        Route::patch('{id}', [OrdersController::class, 'update']);
        Route::post('{id}/cancel-quote', [OrdersController::class, 'cancelQuote']);
        Route::post('{id}/payment-reminder', [OrdersController::class, 'sendPaymentReminder']);
        Route::patch('{id}/quote', [OrdersController::class, 'updateQuote']);
        Route::post('{id}/notes', [OrdersController::class, 'addNote']);
        Route::post('{id}/refund', [OrdersController::class, 'processRefund']);
        Route::patch('{id}/requests/{requestId}', [OrdersController::class, 'resolveOrderRequest']);
    });
});
