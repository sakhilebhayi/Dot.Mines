<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Central platform audit service.
 *
 * Records security-relevant events to the audit_logs table without ever
 * throwing — failures are structured-logged but never interrupt the request.
 *
 * Usage:
 *   AuditService::log(AuditLog::MACHINE_UPDATED, "Updated machine {$name}", $machine, ['...']);
 */
class AuditService
{
    /**
     * Record an audit event.
     *
     * @param  string  $action  One of the AuditLog::* constants
     * @param  string|null  $description  Human-readable summary of the action
     * @param  Model|null  $subject  The Eloquent model that was acted upon
     * @param  array<string, mixed>  $meta  Additional structured context (old/new values, IDs, etc.)
     * @param  int|null  $actorId  Defaults to auth()->id()
     * @param  int|null  $teamId  Defaults to auth()->user()?->current_team_id
     * @param  string|null  $ip  Defaults to request()->ip() (null in console/queue)
     */
    public static function log(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        array $meta = [],
        ?int $actorId = null,
        ?int $teamId = null,
        ?string $ip = null
    ): void {
        try {
            // Resolve IP safely — request() may not be available in queue workers
            $resolvedIp = $ip;
            if ($resolvedIp === null && ! app()->runningInConsole()) {
                try {
                    $resolvedIp = request()->ip();
                } catch (\Throwable) {
                    $resolvedIp = null;
                }
            }

            AuditLog::create([
                'actor_id' => $actorId ?? auth()->id(),
                'team_id' => $teamId ?? auth()->user()?->current_team_id,
                'action' => $action,
                'description' => $description,
                'ip_address' => $resolvedIp,
                'subject_type' => $subject !== null ? get_class($subject) : null,
                'subject_id' => $subject?->getKey(),
                'meta' => empty($meta) ? null : $meta,
            ]);
        } catch (\Throwable $e) {
            Log::error('AuditService: failed to record audit event', [
                'action' => $action,
                'subject_type' => $subject !== null ? get_class($subject) : null,
                'subject_id' => $subject?->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
