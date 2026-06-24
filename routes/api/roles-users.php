<?php

declare(strict_types=1);

use App\Http\Controllers\Api\RolesController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['bnf.authenticate', 'require.permissions'])->group(function (): void {
    Route::get('roles/permissions', [RolesController::class, 'getPermissionCatalog'])
        ->middleware('require.permissions:roles.manage');
    Route::get('roles/staff', [RolesController::class, 'findStaffRoles'])
        ->middleware('require.permissions:users.manage');
    Route::get('roles', [RolesController::class, 'findAll'])
        ->middleware('require.permissions:roles.manage');
    Route::get('roles/{id}', [RolesController::class, 'findOne'])
        ->middleware('require.permissions:roles.manage');
    Route::post('roles', [RolesController::class, 'create'])
        ->middleware('require.permissions:roles.manage');
    Route::patch('roles/{id}', [RolesController::class, 'update'])
        ->middleware('require.permissions:roles.manage');
    Route::delete('roles/{id}', [RolesController::class, 'remove'])
        ->middleware('require.permissions:roles.manage');

    Route::get('users/customers', [UsersController::class, 'findCustomers'])
        ->middleware('require.permissions:customers.view');
    Route::get('users/customers/{id}', [UsersController::class, 'findCustomer'])
        ->middleware('require.permissions:customers.view');
    Route::patch('users/customers/{id}', [UsersController::class, 'updateCustomer'])
        ->middleware('require.permissions:customers.view');
    Route::get('users', [UsersController::class, 'findAll'])
        ->middleware('require.permissions:users.manage');
    Route::post('users/staff', [UsersController::class, 'createStaff'])
        ->middleware('require.permissions:users.manage');
    Route::patch('users/{id}', [UsersController::class, 'updateStaff'])
        ->middleware('require.permissions:users.manage');
});
