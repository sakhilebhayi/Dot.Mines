<?php

namespace App\Support\Reports;

use App\Jobs\GenerateReportJob;
use App\Models\Report;

class ReportGeneration
{
    public const SUPPORTED_TYPES = [
        'production',
        'fleet_utilization',
        'maintenance_schedule',
        'fuel_consumption',
        'material_tracking',
        'downtime_analysis',
        'truck_sensors',
        'tire_condition',
        'load_cycle',
        'fuel',
        'engine_parts',
        'maintenance',
        'custom',
    ];

    /** @return array<string> */
    public static function supportedTypes(): array
    {
        return self::SUPPORTED_TYPES;
    }

    /** @return array<string, mixed> */
    public static function normalizeFilters(mixed $filters): array
    {
        if (is_array($filters)) {
            return $filters;
        }

        if (is_string($filters) && $filters !== '') {
            $decoded = json_decode($filters, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public static function dispatch(Report $report): void
    {
        $connection = self::preferredQueueConnection();
        $pendingDispatch = GenerateReportJob::dispatch($report);

        if ($connection !== null) {
            $pendingDispatch->onConnection($connection);
        }
    }

    public static function preferredQueueConnection(): ?string
    {
        $configured = config('reports.queue_connection');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $connections = config('queue.connections', []);

        if (is_array($connections) && array_key_exists('background', $connections)) {
            return 'background';
        }

        if (is_array($connections) && array_key_exists('deferred', $connections)) {
            return 'deferred';
        }

        $default = config('queue.default');

        return is_string($default) && $default !== '' ? $default : null;
    }
}
