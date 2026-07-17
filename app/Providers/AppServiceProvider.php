<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Strict limit for login and auth actions (5 attempts per minute per IP)
        RateLimiter::for('auth-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Strict limit for public contact messages (3 messages per minute per IP)
        RateLimiter::for('public-contact', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Strict limit for support tickets & replies creation (10 per minute per user/IP)
        RateLimiter::for('tickets-creation', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
