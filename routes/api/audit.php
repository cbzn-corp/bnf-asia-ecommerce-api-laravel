<?php

use App\Http\Controllers\Api\AuditController;
use Illuminate\Support\Facades\Route;

Route::prefix('audit-logs')->middleware(['bnf.authenticate', 'staff', 'permissions:logs.audit'])->group(function () {
    Route::get('/', [AuditController::class, 'findAll']);
});
