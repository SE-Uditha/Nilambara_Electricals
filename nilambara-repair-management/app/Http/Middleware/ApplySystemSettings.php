<?php

namespace App\Http\Middleware;

use App\Modules\Settings\Services\SystemSettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySystemSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        app(SystemSettingService::class)->apply();

        return $next($request);
    }
}
