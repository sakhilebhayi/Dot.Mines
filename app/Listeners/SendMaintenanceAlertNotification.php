<?php

namespace App\Listeners;

use App\Events\MaintenanceAlertTriggered;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendMaintenanceAlertNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 2;

    public function handle(MaintenanceAlertTriggered $event): void
    {
        $machine = $event->machine;
        $probability = $event->probability;
        $predictedDate = $event->predictedDate;

        $alertLevel = $probability >= 0.8
            ? NotificationService::LEVEL_CRITICAL
            : ($probability >= 0.6 ? NotificationService::LEVEL_HIGH : NotificationService::LEVEL_WARNING);

        $probabilityPct = round($probability * 100);

        NotificationService::dispatch([
            'team_id' => $event->teamId,
            'type' => NotificationService::TYPE_AI_PREDICTION,
            'title' => "Predictive Maintenance Alert: {$machine->name}",
            'message' => "AI analysis predicts a {$probabilityPct}% probability of maintenance required for {$machine->name} by {$predictedDate}.",
            'alert_level' => $alertLevel,
            'data' => [
                'machine_id' => $machine->id,
                'machine_name' => $machine->name,
                'probability' => $probabilityPct.'%',
                'predicted_date' => $predictedDate,
                'event' => 'ai_prediction',
            ],
            'action_url' => '/maintenance',
            'notify_roles' => ['admin', 'fleet_manager'],
            'email' => true,
        ]);
    }

    public function failed(MaintenanceAlertTriggered $event, \Throwable $exception): void
    {
        Log::error('SendMaintenanceAlertNotification failed', [
            'machine_id' => $event->machine->id ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
