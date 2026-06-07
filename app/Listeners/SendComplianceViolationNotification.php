<?php

namespace App\Listeners;

use App\Events\ComplianceViolationDetected;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendComplianceViolationNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 2;

    public function handle(ComplianceViolationDetected $event): void
    {
        $violation = $event->violation;

        $severityToLevel = [
            'critical' => NotificationService::LEVEL_CRITICAL,
            'high' => NotificationService::LEVEL_HIGH,
            'medium' => NotificationService::LEVEL_WARNING,
            'low' => NotificationService::LEVEL_INFO,
        ];

        $alertLevel = $severityToLevel[$violation->severity ?? 'medium'] ?? NotificationService::LEVEL_WARNING;
        $violationType = $violation->violation_type ?? 'Unknown';
        $description = $violation->description ?? '';
        $violationId = $violation->id ?? null;

        NotificationService::dispatch([
            'team_id' => $event->teamId,
            'type' => NotificationService::TYPE_ALERT,
            'title' => "Compliance Violation: {$violationType}",
            'message' => $description ?: "A compliance violation of type '{$violationType}' has been detected and requires immediate attention.",
            'alert_level' => $alertLevel,
            'data' => [
                'violation_id' => $violationId,
                'violation_type' => $violationType,
                'severity' => $violation->severity ?? 'medium',
                'remediation_deadline' => $violation->remediation_deadline ?? null,
                'event' => 'compliance_violation',
            ],
            'action_url' => $violationId ? "/compliance/violations/{$violationId}" : '/compliance',
            'notify_roles' => ['admin'],
        ]);
    }

    public function failed(ComplianceViolationDetected $event, \Throwable $exception): void
    {
        Log::error('SendComplianceViolationNotification failed', [
            'team_id' => $event->teamId,
            'error' => $exception->getMessage(),
        ]);
    }
}
