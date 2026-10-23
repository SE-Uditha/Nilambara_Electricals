<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Modules\AuditLogs\Models\AuditLog;
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

            $throttled = function (Request $request, array $headers) {
                AuditLog::query()->create([
                    'action' => 'auth.login.throttled',
                    'entity_type' => 'user',
                    'occurred_at' => now(),
                    'new_values' => ['username' => is_string($username = $request->input(Fortify::username())) ? $username : null],
                    'ip_address' => $request->ip(),
                ]);

                return response('Too many login attempts. Please try again later.', 429, $headers);
            };

            return [
                Limit::perMinute(5)->by($key)->response($throttled),
                Limit::perMinute(20)->by('ip|'.$request->ip())->response($throttled),
            ];
        });
    }
}
