<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register only the session routes required for the Step 1 foundation.
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));

        RateLimiter::for('login', function (Request $request) {
            $username = $request->input(Fortify::username());
            $key = Str::transliterate(
                Str::lower(is_string($username) ? $username : '').'|'.$request->ip()
            );

            return Limit::perMinute(5)->by($key);
        });
    }
}
