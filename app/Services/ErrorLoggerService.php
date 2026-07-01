<?php

namespace App\Services;

use App\Models\PlatformErrorLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ErrorLoggerService
 *
 * Central service for recording errors to the platform_error_logs table.
 * Strips PII from request context before storing.
 * Never exposes stack traces to end-users — only the UUID error_id.
 */
class ErrorLoggerService
{
    /**
     * Fields to strip from request data before storing (PII / security).
     *
     * @var array<int, string>
     */
    private static array $sensitiveKeys = [
        'password', 'password_confirmation', 'token', 'secret',
        'credential', 'credentials', 'api_key', 'api_secret',
        'client_secret', 'access_token', 'refresh_token',
        'authorization', 'x-api-key', 'cookie',
    ];

    /**
     * Record an exception thrown during an HTTP request.
     */
    public static function record(
        \Throwable $e,
        ?Request $request = null,
        string $level = 'error',
        string $category = 'app'
    ): PlatformErrorLog {
        $errorId = (string) Str::uuid();

        $context = null;
        $httpMethod = null;
        $url = null;
        $routeName = null;
        $httpStatus = null;
        $ipAddress = null;
        $userAgent = null;
        $userId = null;
        $teamId = null;

        if ($request !== null) {
            $httpMethod = $request->method();
            $url = $request->fullUrl();
            $routeName = $request->route()?->getName();
            $httpStatus = method_exists($e, 'getStatusCode') ? (int) $e->getStatusCode() : 500;
            $ipAddress = $request->ip();
            $userAgent = substr($request->userAgent() ?? '', 0, 500);
            $context = self::stripSensitive($request->all());
            $category = $request->is('api/*') ? 'api' : 'app';

            if (Auth::check()) {
                $userId = (string) Auth::id();
                /** @var User $authUser */
                $authUser = Auth::user();
                $teamId = $authUser->currentTeam?->id;
            }
        }

        try {
            $log = PlatformErrorLog::create([
                'error_id' => $errorId,
                'level' => $level,
                'category' => $category,
                'http_method' => $httpMethod,
                'url' => $url,
                'route_name' => $routeName,
                'http_status' => $httpStatus,
                'exception_class' => get_class($e),
                'message' => Str::limit($e->getMessage(), 5000),
                'stack_trace' => self::limitTrace($e->getTraceAsString()),
                'context' => $context,
                'user_id' => $userId,
                'team_id' => $teamId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'environment' => app()->environment(),
                'app_version' => config('app.version', config('app.git_sha')),
            ]);
        } catch (\Throwable $inner) {
            // Never let the logger itself crash the application
            Log::critical('ErrorLoggerService failed to write to platform_error_logs', [
                'original_error' => $e->getMessage(),
                'logger_error' => $inner->getMessage(),
                'error_id' => $errorId,
            ]);

            // Return a minimal in-memory stub so callers still get an error_id
            $log = new PlatformErrorLog(['error_id' => $errorId]);
        }

        return $log;
    }

    /**
     * Record a queue job failure.
     */
    public static function recordQueueFailure(
        string $jobClass,
        \Throwable $e,
        string $queue = 'default'
    ): PlatformErrorLog {
        return self::record($e, null, 'error', 'queue');
    }

    /**
     * Record a frontend JS error submitted via the API.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function recordFrontend(array $payload, ?Request $request = null): PlatformErrorLog
    {
        $errorId = (string) Str::uuid();

        $userId = null;
        $teamId = null;
        $ipAddress = null;

        if ($request !== null) {
            if (Auth::check()) {
                $userId = (string) Auth::id();
                /** @var User $authUser */
                $authUser = Auth::user();
                $teamId = $authUser->currentTeam?->id;
            }
            $ipAddress = $request->ip();
        }

        try {
            $log = PlatformErrorLog::create([
                'error_id' => $errorId,
                'level' => 'error',
                'category' => 'frontend',
                'url' => $payload['url'] ?? null,
                'exception_class' => $payload['type'] ?? 'JavaScriptError',
                'message' => Str::limit($payload['message'] ?? 'Unknown frontend error', 5000),
                'stack_trace' => self::limitTrace($payload['stack'] ?? ''),
                'context' => self::stripSensitive($payload['context'] ?? []),
                'user_id' => $userId,
                'team_id' => $teamId,
                'ip_address' => $ipAddress,
                'environment' => app()->environment(),
                'app_version' => config('app.version', config('app.git_sha')),
            ]);
        } catch (\Throwable $inner) {
            Log::error('ErrorLoggerService::recordFrontend failed', ['error' => $inner->getMessage()]);
            $log = new PlatformErrorLog(['error_id' => $errorId]);
        }

        return $log;
    }

    /**
     * Strip sensitive fields from request data before storing.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function stripSensitive(array $data): array
    {
        $stripped = [];
        foreach ($data as $key => $value) {
            $lower = strtolower((string) $key);
            $isSensitive = false;
            foreach (self::$sensitiveKeys as $sensitive) {
                if (str_contains($lower, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }
            $stripped[$key] = $isSensitive ? '[REDACTED]' : (is_array($value) ? self::stripSensitive($value) : $value);
        }

        return $stripped;
    }

    /**
     * Truncate stack trace to 10KB to prevent storage bloat.
     */
    private static function limitTrace(string $trace): string
    {
        return mb_substr($trace, 0, 10240);
    }
}
