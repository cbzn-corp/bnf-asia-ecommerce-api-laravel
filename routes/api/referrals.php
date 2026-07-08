<?php

use App\Http\Controllers\ReferralsController;
use Illuminate\Support\Facades\Route;

Route::prefix('referrals')->group(function () {
    Route::post('clicks', [ReferralsController::class, 'recordClick']);

    Route::middleware(['bnf.authenticate', 'require.permissions:referrals.manage'])->group(function () {
        Route::get('partners', [ReferralsController::class, 'findAllPartners']);
        Route::post('partners', [ReferralsController::class, 'createPartner']);
        Route::get('partners/{id}/stats', [ReferralsController::class, 'partnerStats']);
        Route::patch('partners/{id}', [ReferralsController::class, 'updatePartner']);
        Route::delete('partners/{id}', [ReferralsController::class, 'removePartner']);
        Route::get('commissions', [ReferralsController::class, 'findAllCommissions']);
    });
});
