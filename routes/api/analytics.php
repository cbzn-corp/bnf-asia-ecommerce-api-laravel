<?php

use App\Http\Controllers\Api\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::prefix('analytics')->middleware(['bnf.authenticate', 'staff', 'permissions:analytics.view'])->group(function () {
    Route::get('dashboard', [AnalyticsController::class, 'dashboard']);
});
