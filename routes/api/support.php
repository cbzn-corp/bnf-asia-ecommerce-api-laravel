<?php

use App\Http\Controllers\Api\SupportChatController;
use Illuminate\Support\Facades\Route;

Route::prefix('support')->group(function () {
    Route::middleware(['bnf.authenticate', 'staff', 'permissions:orders.manage'])->group(function () {
        Route::get('inbox', [SupportChatController::class, 'listInbox']);
        Route::patch('conversations/order/{orderId}/resolve', [SupportChatController::class, 'resolve']);
    });

    Route::middleware(['bnf.authenticate'])->group(function () {
        Route::get('conversations/order/{orderId}/messages', [SupportChatController::class, 'getMessages']);
        Route::post('conversations/order/{orderId}/messages', [SupportChatController::class, 'sendMessage']);
    });
});
