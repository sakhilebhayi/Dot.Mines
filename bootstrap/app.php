<?php

use App\Http\Middleware\CacheControlHeaders;
use App\Http\Middleware\EnforceDownloadRateLimit;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminHasTwoFactor;
use App\Http\Middleware\EnsureTeamContext;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Session-cookie authentication for the incremental sync API
        // (hybrid Slice 2): the browser's sync client is a first-party
        // frontend, so Sanctum treats same-origin API requests as stateful.
        $middleware->statefulApi();

        $middleware->alias([
            'ensure_team' => EnsureTeamContext::class,
            'cache.headers' => CacheControlHeaders::class,
            'admin' => EnsureAdmin::class,
            'admin.2fa' => EnsureAdminHasTwoFactor::class,
        ]);

        // Force HTTPS, CSP and add security headers to all web requests
        $middleware->web(append: [
            ForceHttps::class,
            SecurityHeaders::class,
            EnforceDownloadRateLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
