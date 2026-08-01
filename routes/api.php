<?php

use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\AdminController;
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
    });
});
