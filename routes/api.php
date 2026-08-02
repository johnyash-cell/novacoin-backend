<?php

use App\Http\Controllers\Api\Activity\UserPageVisitController;
use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminAuthenticationLoginLogController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminUserPageVisitLogController;
use App\Http\Controllers\Api\Auth\UserAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [UserAuthController::class, 'register'])
        ->middleware('throttle:auth');
    Route::post('login', [UserAuthController::class, 'login'])
        ->middleware('throttle:auth');
    Route::post('google', [UserAuthController::class, 'google'])
        ->middleware('throttle:auth');

    Route::middleware('auth:api')->group(function (): void {
        Route::post('logout', [UserAuthController::class, 'logout']);
        Route::post('refresh', [UserAuthController::class, 'refresh']);
        Route::get('me', [UserAuthController::class, 'me']);
    });
});

Route::prefix('activity')->group(function (): void {
    Route::post('page-visits', [UserPageVisitController::class, 'store'])
        ->middleware('throttle:page-visits');
});

Route::prefix('admin')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AdminAuthController::class, 'login'])
            ->middleware('throttle:auth');

        Route::middleware('auth:admin')->group(function (): void {
            Route::post('logout', [AdminAuthController::class, 'logout']);
            Route::post('refresh', [AdminAuthController::class, 'refresh']);
            Route::get('me', [AdminAuthController::class, 'me']);
        });
    });

    Route::middleware('auth:admin')->group(function (): void {
        Route::get('admins', [AdminController::class, 'index']);
        Route::post('admins', [AdminController::class, 'store']);

        Route::get('users/filter-options', [AdminUserController::class, 'filterOptions']);
        Route::get('users/{user}/authentication-login-logs', [AdminAuthenticationLoginLogController::class, 'indexForUser']);
        Route::get('users/{user}/page-visit-logs', [AdminUserPageVisitLogController::class, 'indexForUser']);
        Route::apiResource('users', AdminUserController::class);
        Route::post('users/{user}/promote-to-admin', [AdminUserController::class, 'promoteToAdmin']);

        Route::get('authentication-login-logs/filter-options', [AdminAuthenticationLoginLogController::class, 'filterOptions']);
        Route::get('authentication-login-logs', [AdminAuthenticationLoginLogController::class, 'index']);

        Route::get('user-page-visit-logs/filter-options', [AdminUserPageVisitLogController::class, 'filterOptions']);
        Route::get('user-page-visit-logs/overview', [AdminUserPageVisitLogController::class, 'overview']);
        Route::get('user-page-visit-logs', [AdminUserPageVisitLogController::class, 'index']);
    });
});
