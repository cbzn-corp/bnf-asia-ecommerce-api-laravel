<?php

use App\Http\Controllers\Api\LogsController;
use Illuminate\Support\Facades\Route;

Route::prefix('logs')->middleware(['bnf.authenticate', 'staff'])->group(function () {
    Route::get('payments', [LogsController::class, 'findPaymentLogs'])->middleware('permissions:logs.payments');
});
