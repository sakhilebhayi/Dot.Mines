<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;

class NotifyOnJobFailed
{
    /**
     * Handle the event.
     */
    public function handle(JobFailed $event): void
    {
        $payload = [
            'connection' => $event->connectionName,
            'queue' => $event->job?->getQueue(),
            'job' => $event->job?->resolveName() ?? $event->job?->getName() ?? 'unknown',
            'exception' => $event->exception->getMessage(),
        ];

        // Log to default logger (stack) and ensure it reaches configured channels
        try {
            logger()->error('Background job failed', $payload + ['trace' => $event->exception->getTraceAsString()]);
        } catch (\Throwable $e) {
            // swallow logging errors
        }

        // Send to Sentry if available and configured
        try {
            if (config('services.sentry.dsn') && class_exists('\\Sentry\\State\\HubInterface')) {
                // safe-call Sentry capture if SDK is installed
                if (function_exists('\\Sentry\\captureException')) {
                    \Sentry\captureException($event->exception);
                }
            }
        } catch (\Throwable $e) {
            // don't let monitoring failures crash the worker
        }
    }
}
