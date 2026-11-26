<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\Platform\ConfirmPasswordController;
use App\Http\Controllers\Api\Platform\DashboardController;
use App\Http\Controllers\Api\Platform\PaymentController;
use App\Http\Controllers\Api\Platform\ProfileController;
use App\Http\Controllers\Api\Platform\SubscriptionPlanController;
use App\Http\Controllers\Api\Platform\SupportMessageController;
use App\Http\Controllers\Api\Platform\TenantController;
use App\Http\Controllers\Api\Platform\TenantSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/auth/me', [AuthController::class, 'me']);
Route::post('/contact', [ContactController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::prefix('platform')
        ->middleware('role:platform_super_admin')
        ->group(function () {
            Route::get('/dashboard', DashboardController::class);

            Route::get('/tenants', [TenantController::class, 'index']);
            Route::post('/tenants', [TenantController::class, 'store']);
            Route::get('/tenants/{tenant}', [TenantController::class, 'show']);
            Route::match(['patch', 'post'], '/tenants/{tenant}', [TenantController::class, 'update']);
            Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy']);
            Route::post('/tenants/{tenant}/activate', [TenantController::class, 'activate']);
            Route::post('/tenants/{tenant}/deactivate', [TenantController::class, 'deactivate']);

            Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index']);
            Route::post('/subscription-plans', [SubscriptionPlanController::class, 'store']);
            Route::patch('/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'update']);
            Route::delete('/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'destroy']);

            Route::get('/subscriptions', [TenantSubscriptionController::class, 'index']);
            Route::post('/subscriptions', [TenantSubscriptionController::class, 'store']);
            Route::patch('/subscriptions/{subscription}', [TenantSubscriptionController::class, 'update']);

            Route::get('/payments', [PaymentController::class, 'index']);
            Route::post('/payments', [PaymentController::class, 'store']);

            Route::get('/support-messages', [SupportMessageController::class, 'index']);
            Route::get('/support-messages/{supportMessage}', [SupportMessageController::class, 'show']);
            Route::patch('/support-messages/{supportMessage}', [SupportMessageController::class, 'update']);

            Route::match(['patch', 'post'], '/profile', [ProfileController::class, 'update']);
            Route::post('/confirm-password', ConfirmPasswordController::class);
        });
});
