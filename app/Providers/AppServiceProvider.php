<?php

namespace App\Providers;

use App\Contracts\Auth\GoogleIdTokenVerifierContract;
use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GoogleIdTokenVerifierContract::class, GoogleIdTokenVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('page-visits', function (Request $request) {
            $userId = auth('api')->id();

            return Limit::perMinute(120)->by($userId ?? $request->ip());
        });
    }
}
