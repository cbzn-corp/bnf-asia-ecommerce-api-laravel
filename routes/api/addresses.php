<?php

use App\Http\Controllers\Api\AddressesController;
use Illuminate\Support\Facades\Route;

Route::prefix('addresses')->middleware(['bnf.authenticate', 'require.customer'])->group(function () {
    Route::get('/', [AddressesController::class, 'findMine']);
    Route::post('/', [AddressesController::class, 'create']);
    Route::patch('{id}', [AddressesController::class, 'update']);
    Route::delete('{id}', [AddressesController::class, 'remove']);
});
