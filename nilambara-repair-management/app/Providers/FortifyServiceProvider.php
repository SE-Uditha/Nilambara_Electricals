<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
        Fortify::authenticateUsing(function (Request $request): ?User {
            $username = $request->input(Fortify::username());
            $password = $request->input('password');

            if (! is_string($username) || ! is_string($password)) {
                return null;
            }

            $user = User::query()->where('username', Str::lower($username))->first();

            return $user !== null && $user->hasActiveAccount() && Hash::check($password, $user->password)
                ? $user
                : null;
        });

        RateLimiter::for('login', function (Request $request) {
            $username = $request->input(Fortify::username());
            $key = Str::transliterate(
                Str::lower(is_string($username) ? $username : '').'|'.$request->ip()
            );

            return Limit::perMinute(5)->by($key);
        });
    }
}
