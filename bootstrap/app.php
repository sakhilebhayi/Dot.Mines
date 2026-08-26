<?php

use App\Http\Middleware\ApiVersion;
use App\Http\Middleware\AuthenticateGuardian;
use App\Http\Middleware\CacheControlHeaders;
use App\Http\Middleware\EnforceDownloadRateLimit;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminHasTwoFactor;
use App\Http\Middleware\EnsureTeamContext;
use App\Http\Middleware\EnsureTokenAbility;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\NormalizeApiParameters;
use App\Http\Middleware\SecurityHeaders;
use App\Services\Guardian\ErrorCounter;
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

        // Paystack webhooks authenticate by HMAC signature, not session --
        // without this exemption every REAL webhook dies with 419 before
        // WebhookController's signature check runs. Latent since the
        // billing program: the test client skips CSRF, so only a live
        // unsigned curl against production exposed it.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        $middleware->alias([
            'ensure_team' => EnsureTeamContext::class,
            'cache.headers' => CacheControlHeaders::class,
            'admin' => EnsureAdmin::class,
            'admin.2fa' => EnsureAdminHasTwoFactor::class,
            'token.ability' => EnsureTokenAbility::class,
            'api.params' => NormalizeApiParameters::class,
            'api.version' => ApiVersion::class,
            'guardian.token' => AuthenticateGuardian::class,
        ]);

        // Force HTTPS, CSP and add security headers to all web requests
        $middleware->web(append: [
            ForceHttps::class,
            SecurityHeaders::class,
            EnforceDownloadRateLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Feed 1 of the guardian's hourly error counter: every reported
        // throwable (feed 2 is the CountGuardianLogErrors listener, which
        // catches error-level log writes the report pipeline never sees).
        // ErrorCounter::record() swallows its own failures, so this hook
        // can never break real error handling.
        $exceptions->report(function (Throwable $e): void {
            app(ErrorCounter::class)->record($e);
        });
    })->create();
