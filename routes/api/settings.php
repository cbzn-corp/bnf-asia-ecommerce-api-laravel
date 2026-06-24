<?php

use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')->group(function () {
    Route::get('shipping-rates', [SettingsController::class, 'listShipping']);
    Route::get('platform', [SettingsController::class, 'platform']);
    Route::get('pickup-locations', [SettingsController::class, 'listPickup']);

    Route::middleware(['bnf.authenticate', 'staff', 'permissions:settings.shipping'])->group(function () {
        Route::post('shipping-rates', [SettingsController::class, 'createShipping']);
        Route::patch('shipping-rates/{id}', [SettingsController::class, 'updateShipping']);
        Route::delete('shipping-rates/{id}', [SettingsController::class, 'removeShipping']);
        Route::get('pickup-locations/admin', [SettingsController::class, 'listPickupAdmin']);
        Route::post('pickup-locations', [SettingsController::class, 'createPickup']);
        Route::patch('pickup-locations/{id}', [SettingsController::class, 'updatePickup']);
        Route::delete('pickup-locations/{id}', [SettingsController::class, 'removePickup']);
    });

    Route::middleware(['bnf.authenticate', 'staff', 'permissions:settings.payments'])->group(function () {
        Route::get('platform/admin', [SettingsController::class, 'platformAdmin']);
        Route::patch('platform', [SettingsController::class, 'updatePlatform']);
    });

    Route::middleware(['bnf.authenticate', 'staff', 'permissions:settings.email_templates'])->group(function () {
        Route::get('email-templates', [SettingsController::class, 'listEmailTemplates']);
        Route::get('email-templates/placeholders', [SettingsController::class, 'emailTemplatePlaceholders']);
        Route::patch('email-templates/{key}', [SettingsController::class, 'updateEmailTemplate']);
        Route::post('email/test', [SettingsController::class, 'sendTestEmail']);
        Route::post('email-templates/{key}/test', [SettingsController::class, 'sendTemplateTestEmail']);
    });
});
