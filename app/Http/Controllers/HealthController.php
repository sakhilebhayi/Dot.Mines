<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class HealthController extends Controller
{
    /**
     * Comprehensive health check endpoint for monitoring and load balancers.
     *
     * Returns 200 if all critical services are healthy, 503 if any are degraded.
     * Used as the K8s liveness probe — a 503 here triggers pod restart.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $allHealthy = true;

        // Database check
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'error', 'message' => 'Database unreachable'];
            $allHealthy = false;
        }

        // Cache check
        try {
            $key = 'health:ping:'.now()->timestamp;
            Cache::put($key, 'pong', 10);
            $value = Cache::get($key);
            Cache::forget($key);
            if ($value !== 'pong') {
                throw new \RuntimeException('Cache read/write mismatch');
            }
            $checks['cache'] = ['status' => 'ok'];
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'error', 'message' => 'Cache unreachable'];
            $allHealthy = false;
        }

        // Queue check
        try {
            $connection = config('queue.default');
            $checks['queue'] = ['status' => 'ok', 'driver' => $connection];
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'error', 'message' => 'Queue configuration invalid'];
            $allHealthy = false;
        }

        // Storage check
        try {
            $disk = config('filesystems.default', 'local');
            Storage::disk($disk)->exists('health-check-probe');
            $checks['storage'] = ['status' => 'ok', 'disk' => $disk];
        } catch (\Throwable $e) {
            $checks['storage'] = ['status' => 'error', 'message' => 'Storage unreachable'];
            $allHealthy = false;
        }

        $statusCode = $allHealthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return response()->json([
            'status' => $allHealthy ? 'ok' : 'degraded',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
        ], $statusCode);
    }

    /**
     * Lightweight readiness probe for K8s.
     *
     * Returns 200 as soon as the PHP process is up and can reach the database.
     * Does NOT perform cache writes or storage checks — those are for the liveness probe.
     * A 503 here tells K8s not to route traffic to this pod (e.g. during startup or drain),
     * without triggering a full pod restart.
     */
    public function ready(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            return response()->json(['status' => 'not_ready', 'reason' => 'database'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json(['status' => 'ready']);
    }
}
