<?php

use App\Http\Middleware\CacheControlHeaders;
use App\Http\Middleware\EnforceDownloadRateLimit;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminHasTwoFactor;
use App\Http\Middleware\EnsureTeamContext;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
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
        // Return JSON for all API requests and requests expecting JSON
        $exceptions->shouldRenderJsonWhen(
            fn ($request, Throwable $e) => $request->expectsJson() || $request->is('api/*')
        );

        // 422 — Validation errors (consistent JSON shape for all API consumers)
        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // 401 — Unauthenticated
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        // 403 — Unauthorized / policy denial
        $exceptions->render(function (AuthorizationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'This action is unauthorized.'], 403);
            }
        });

        // 429 — Rate limit exceeded
        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Too many requests. Please slow down.',
                    'retry_after' => (int) ($e->getHeaders()['Retry-After'] ?? 60),
                ], 429);
            }
        });

        // 404 — Model not found (route model binding miss)
        $exceptions->render(function (ModelNotFoundException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });

        // 500 — In production, hide all unhandled exception details from API responses
        if (app()->environment('production')) {
            $exceptions->render(function (Throwable $e, $request) {
                if (! $e instanceof ValidationException
                    && ! $e instanceof AuthenticationException
                    && ! $e instanceof AuthorizationException
                    && ! $e instanceof ThrottleRequestsException
                    && ! $e instanceof ModelNotFoundException
                    && ($request->expectsJson() || $request->is('api/*'))
                ) {
                    return response()->json(
                        ['message' => 'An unexpected error occurred. Please try again.'],
                        500
                    );
                }
            });
        }
    })->create();
