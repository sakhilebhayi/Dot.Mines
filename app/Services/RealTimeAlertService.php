<?php

namespace App\Services;

use App\Events\AlertTriggered;
use App\Events\ComplianceViolationDetected;
use App\Events\MaintenanceAlertTriggered;
use App\Events\SensorReadingRecorded;
use App\Events\SensorStatusChanged;
use App\Models\Alert;
use App\Models\IoTSensor;
use App\Models\Machine;
use App\Models\Notification;
use App\Models\OperatorFatigue;
use App\Models\Team;
use App\Notifications\OperatorFatigueAlert;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class RealTimeAlertService
{
    /**
     * Dispatch sensor reading alert
     */
    public function dispatchSensorAlert(IoTSensor $sensor, array $reading, int $teamId, bool $isAnomaly = false): void
    {
        // Create notification record
        Notification::create([
            'team_id' => $teamId,
            'type' => 'sensor_reading',
            'title' => "Sensor Reading: {$sensor->name}",
            'message' => "New reading: {$reading['value']} {$reading['unit']}",
            'alert_level' => $isAnomaly ? 'warning' : 'info',
            'data' => array_merge($reading, ['sensor_id' => $sensor->id]),
            'action_url' => "/iot/sensors/{$sensor->id}",
        ]);

        // Broadcast via WebSocket
        SensorReadingRecorded::dispatch($sensor, array_merge($reading, ['is_anomaly' => $isAnomaly]), $teamId);
    }

    /**
     * Dispatch maintenance alert
     */
    public function dispatchMaintenanceAlert(Machine $machine, float $probability, Carbon $predictedDate, int $teamId): void
    {
        $severity = match (true) {
            $probability >= 0.8 => 'critical',
            $probability >= 0.6 => 'high',
            default => 'medium',
        };

        // Create notification
        Notification::create([
            'team_id' => $teamId,
            'type' => 'maintenance_alert',
            'title' => "Maintenance Alert: {$machine->name}",
            'message' => 'Predicted maintenance needed on '.$predictedDate->format('M d, Y'),
            'alert_level' => $severity,
            'data' => [
                'machine_id' => $machine->id,
                'probability' => $probability,
                'predicted_date' => $predictedDate,
            ],
            'action_url' => "/fleet/{$machine->id}/maintenance",
        ]);

        // Broadcast via WebSocket
        MaintenanceAlertTriggered::dispatch($machine, $probability, $predictedDate, $teamId);
    }

    /**
     * Dispatch compliance violation alert
     *
     * @param  array<string, mixed>|\stdClass|object  $violation
     */
    public function dispatchComplianceAlert($violation, int $teamId): void
    {
        $severityMap = [
            'critical' => 'critical',
            'high' => 'high',
            'medium' => 'warning',
            'low' => 'info',
        ];

        // Support both array and object
        $isArray = is_array($violation);
        $violationType = $isArray ? ($violation['type'] ?? 'unknown') : $violation->violation_type;
        $description = $isArray ? ($violation['description'] ?? '') : $violation->description;
        $severity = $isArray ? ($violation['severity'] ?? 'medium') : $violation->severity;
        $deadline = $isArray ? ($violation['deadline'] ?? null) : $violation->remediation_deadline;
        $violationId = $isArray ? ($violation['id'] ?? null) : $violation->id;

        // Create notification
        Notification::create([
            'team_id' => $teamId,
            'type' => 'compliance_violation',
            'title' => "Compliance Violation: {$violationType}",
            'message' => $description,
            'alert_level' => $severityMap[$severity] ?? 'warning',
            'data' => [
                'violation_id' => $violationId,
                'severity' => $severity,
                'deadline' => $deadline,
            ],
            'action_url' => $violationId ? "/compliance/violations/{$violationId}" : null,
        ]);

        // Broadcast via WebSocket only if we have a violation object
        if (! $isArray) {
            ComplianceViolationDetected::dispatch($violation, $teamId);
        }
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
                'shift_date' => optional($fatigue->shift_date)->toDateString(),
                'shift_type' => $fatigue->shift_type,
            ],
        ]);

        AlertTriggered::dispatch($alert);

        foreach ($team->allUsers() as $member) {
            $member->notify(new OperatorFatigueAlert($fatigue));
        }
    }

    /**
     * Dispatch sensor status change alert
     */
    public function dispatchSensorStatusAlert(IoTSensor $sensor, string $oldStatus, string $newStatus, int $teamId): void
    {
        $alertLevel = $newStatus === 'inactive' ? 'warning' : 'info';

        // Create notification
        Notification::create([
            'team_id' => $teamId,
            'type' => 'sensor_status_changed',
            'title' => "Sensor Status Change: {$sensor->name}",
            'message' => 'Status changed from '.ucfirst($oldStatus).' to '.ucfirst($newStatus),
            'alert_level' => $alertLevel,
            'data' => [
                'sensor_id' => $sensor->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ],
            'action_url' => "/iot/sensors/{$sensor->id}",
        ]);

        // Broadcast via WebSocket
        SensorStatusChanged::dispatch($sensor, $oldStatus, $newStatus, $teamId);
    }

    /**
     * Get recent alerts for team
     *
     * @return Collection<int,Notification>
     */
    public function getRecentAlerts(int $teamId, int $limit = 20): Collection
    {
        return Notification::where('team_id', $teamId)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get unread alerts for user
     *
     * @return Collection<int,Notification>
     */
    public function getUnreadAlerts(int $userId, int $teamId, int $limit = 20): Collection
    {
        return Notification::where('team_id', $teamId)
            ->whereDoesntHave('readBy', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Mark alert as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::find($notificationId);
        if ($notification) {
            $notification->readBy()->attach($userId);
            $notification->update(['is_read' => true, 'read_at' => now()]);

            return true;
        }

        return false;
    }

    /**
     * Batch mark alerts as read
     *
     * @param  array<int>  $notificationIds
     */
    public function markMultipleAsRead(array $notificationIds, int $userId): int
    {
        $count = 0;
        Notification::whereIn('id', $notificationIds)->each(function ($notification) use ($userId, &$count) {
            $notification->readBy()->attach($userId);
            $notification->update(['is_read' => true, 'read_at' => now()]);
            $count++;
        });

        return $count;
    }

    /**
     * Get alert statistics
     *
     * @return array<string, mixed>
     */
    public function getAlertStats(int $teamId, int $days = 7): array
    {
        $fromDate = now()->subDays($days);

        $alerts = Notification::where('team_id', $teamId)
            ->where('created_at', '>=', $fromDate)
            ->get();

        return [
            'total' => $alerts->count(),
            'by_level' => [
                'critical' => $alerts->where('alert_level', 'critical')->count(),
                'high' => $alerts->where('alert_level', 'high')->count(),
                'warning' => $alerts->where('alert_level', 'warning')->count(),
                'info' => $alerts->where('alert_level', 'info')->count(),
            ],
            'by_type' => $alerts->groupBy('type')->map->count(),
            'period_days' => $days,
        ];
    }
}
