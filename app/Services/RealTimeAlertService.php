<?php

namespace App\Services;

use App\Events\AlertTriggered;
use App\Events\MaintenanceAlertTriggered;
use App\Models\Alert;
use App\Models\Machine;
use App\Models\Notification;
use App\Models\OperatorFatigue;
use App\Models\Team;
use App\Models\User;
use App\Notifications\OperatorFatigueAlert;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RealTimeAlertService
{
    /**
     * Dispatch maintenance alert
     *
     * @psalm-suppress PossiblyUnusedMethod -- exercised only by its test today; kept as covered public API
     */
    public function dispatchMaintenanceAlert(Machine $machine, float $probability, Carbon $predictedDate, int $teamId): void
    {
        $severity = match (true) {
            $probability >= 0.8 => 'critical',
            $probability >= 0.6 => 'high',
            default => 'medium',
        };

        // Managers get emailed (gated by their notification preferences);
        // everyone on the team sees it in the bell.
        NotificationService::notifyManagers(
            $teamId,
            'maintenance_alert',
            "Maintenance Alert: {$machine->name}",
            'Predicted maintenance needed on '.$predictedDate->format('M d, Y'),
            $severity,
            [
                'machine_id' => $machine->id,
                'probability' => $probability,
                'predicted_date' => $predictedDate,
            ],
            "/fleet/{$machine->id}/maintenance",
        );

        // Broadcast via WebSocket
        MaintenanceAlertTriggered::dispatch($machine, $probability, $predictedDate, $teamId);
    }

    /**
     * Dispatch an operator fatigue alert.
     *
     * Unlike the other dispatch* methods above, this creates an Alert record
     * (not just a Notification) -- fatigue alerts belong on the same
     * acknowledge/resolve dashboard as every other operational alert
     * (Alerts.php / resources/views/livewire/alerts.blade.php), since a
     * supervisor needs to be able to act on and clear one. Also emails the
     * team so a critical fatigue reading reaches a supervisor even when
     * they're not looking at the dashboard -- the whole point of proactive
     * safety alerting.
     */
    public function dispatchFatigueAlert(OperatorFatigue $fatigue, Team $team): void
    {
        $operatorName = $fatigue->user?->name ?? 'Unknown operator';

        $alert = Alert::create([
            'team_id' => $team->id,
            'machine_id' => $fatigue->machine_id,
            'type' => 'fatigue',
            'title' => "Operator Fatigue: {$operatorName}",
            'description' => sprintf(
                '%s has a fatigue score of %d/100 (%s) after %s hours on shift, %s consecutive day(s) worked.',
                $operatorName,
                $fatigue->fatigue_score,
                $fatigue->alert_level,
                $fatigue->hours_worked,
                $fatigue->consecutive_days
            ),
            'priority' => $fatigue->alert_level,
            'status' => 'active',
            'triggered_at' => now(),
            'metadata' => [
                'operator_fatigue_id' => $fatigue->id,
                'user_id' => $fatigue->user_id,
                'fatigue_score' => $fatigue->fatigue_score,
                'shift_date' => $fatigue->shift_date->toDateString(),
                'shift_type' => $fatigue->shift_type,
            ],
        ]);

        AlertTriggered::dispatch($alert);

        /** @var Collection<int, User> $members */
        $members = $team->allUsers();

        foreach ($members as $member) {
            $member->notify(new OperatorFatigueAlert($fatigue));
        }
    }
}
