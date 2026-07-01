<?php

use App\Http\Middleware\CacheControlHeaders;
use App\Http\Middleware\EnforceDownloadRateLimit;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminHasTwoFactor;
use App\Http\Middleware\EnsureTeamContext;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\SecurityHeaders;
use App\Services\ErrorLoggerService;
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

        // 500 — Log all unhandled exceptions to the platform logbook.
        // In production: hide internals and render a branded error page.
        // In non-production: let Laravel's default handler show the exception detail.
        $exceptions->render(function (Throwable $e, $request) {
            $isExpected = $e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || $e instanceof ThrottleRequestsException
                || $e instanceof ModelNotFoundException;

            // Log unexpected errors to the platform logbook in all environments
            $errorLog = null;
            if (! $isExpected) {
                try {
                    $errorLog = ErrorLoggerService::record($e, $request);
                } catch (Throwable) {
                    // Logging must never crash the app
                }
            }

            // In non-production: let Laravel default rendering handle display
            // (shows full debug page in local/testing, no user-visible change)
            if (! app()->isProduction()) {
                return null;
            }

            if ($isExpected) {
                return null;
            }

            $isApiRequest = $request->expectsJson() || $request->is('api/*');

            if ($isApiRequest) {
                $status = method_exists($e, 'getStatusCode') ? (int) $e->getStatusCode() : 500;

                return response()->json(array_filter([
                    'message' => 'An unexpected error occurred. Please try again.',
                    'error_ref' => $errorLog?->error_id,
                ]), $status);
            }

            // Web: render branded error page — no stack trace exposed
            $status = method_exists($e, 'getStatusCode') ? (int) $e->getStatusCode() : 500;

            return response()->view('errors.platform', [
                'status' => $status,
                'error_ref' => $errorLog?->error_id,
            ], $status);
        });
    })->create();
