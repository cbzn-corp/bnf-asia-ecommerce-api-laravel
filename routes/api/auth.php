<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('customer/register', [AuthController::class, 'customerRegister']);
    Route::post('customer/register-from-order', [AuthController::class, 'registerFromOrder']);
    Route::post('customer/login', [AuthController::class, 'customerLogin']);
    Route::post('customer/forgot-password', [AuthController::class, 'customerForgotPassword']);
    Route::post('customer/reset-password', [AuthController::class, 'customerResetPassword']);

    Route::middleware(['bnf.authenticate', 'require.permissions'])->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::get('admin-bootstrap', [AuthController::class, 'adminBootstrap']);
        Route::patch('me/password', [AuthController::class, 'changePassword']);
    });

    Route::middleware(['bnf.authenticate', 'require.customer'])->group(function (): void {
        Route::get('customer/me', [AuthController::class, 'customerMe']);
        Route::patch('customer/me', [AuthController::class, 'updateCustomerProfile']);
        Route::patch('customer/me/password', [AuthController::class, 'customerChangePassword']);
    });
});
