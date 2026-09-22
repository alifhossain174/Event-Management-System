<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApplyOrganizationSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = app(SettingsService::class);
        $timezone = $settings->string('general.timezone');
        $locale = $settings->string('general.locale');

        config(['app.timezone' => $timezone, 'app.locale' => $locale]);
        date_default_timezone_set($timezone);
        app()->setLocale($locale);

        return $next($request);
    }
}
