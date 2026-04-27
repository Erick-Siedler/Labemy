<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('registration', function (Request $request) {
            $identifier = $request->user()?->id;
            $routeName = $request->route()?->getName() ?? 'registration';
            $keyBase = $identifier ? 'user:' . $identifier : 'ip:' . ($request->ip() ?? 'unknown');
            $key = $keyBase . '|route:' . $routeName;

            return Limit::perHour(5)->by($key);
        });
    }
}
