<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Platform\ProfileController;
use App\Http\Controllers\Api\Platform\TenantController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/me', [AuthController::class, 'me']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::prefix('platform')
        ->middleware('role:platform_super_admin')
        ->group(function () {
            Route::get('/tenants', [TenantController::class, 'index']);
            Route::post('/tenants', [TenantController::class, 'store']);
            Route::get('/tenants/{tenant}', [TenantController::class, 'show']);
            Route::patch('/tenants/{tenant}', [TenantController::class, 'update']);
            Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy']);

            Route::patch('/profile', [ProfileController::class, 'update']);
        });
});
