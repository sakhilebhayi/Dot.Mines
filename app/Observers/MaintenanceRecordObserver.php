<?php

namespace App\Observers;

use App\Events\MachineStatusChanged;
use App\Models\Machine;
use App\Models\MaintenanceRecord;
use App\Services\NotificationService;
use App\Services\QueryCacheService;
use Illuminate\Support\Facades\Log;

class MaintenanceRecordObserver
{
    /**
     * Active-maintenance statuses on a MaintenanceRecord that should
     * put the machine into the 'maintenance' operational status.
     */
    private const ACTIVE_STATUSES = ['scheduled', 'in_progress'];

    /**
     * Terminal statuses that mean maintenance for this record is finished.
     */
    private const TERMINAL_STATUSES = ['completed', 'cancelled'];

    /**
     * When a new work-order is created, immediately set the machine to
     * 'maintenance' so every dashboard reflects the booking.
     */
    public function created(MaintenanceRecord $record): void
    {
        if (in_array($record->status, self::ACTIVE_STATUSES, true)) {
            $this->setMachineStatus($record->machine_id, 'maintenance');
        }

        $machineName = $record->machine?->name ?? "Machine #{$record->machine_id}";

        NotificationService::notifyRoles(
            teamId: $record->team_id,
            roles: ['admin', 'fleet_manager', 'operator'],
            type: NotificationService::TYPE_MAINTENANCE,
            title: "Maintenance Scheduled: {$machineName}",
            message: "{$record->maintenance_type} maintenance '{$record->title}' has been scheduled for {$machineName}.",
            alertLevel: $record->priority === 'critical' ? NotificationService::LEVEL_HIGH : NotificationService::LEVEL_INFO,
            data: [
                'record_id' => $record->id,
                'machine' => $machineName,
                'type' => $record->maintenance_type,
                'title' => $record->title,
                'priority' => $record->priority,
                'status' => $record->status,
                'scheduled_date' => $record->scheduled_date?->format('Y-m-d'),
                'event' => 'created',
            ],
            actionUrl: '/maintenance',
        );
    }

    /**
     * When a work-order status changes, keep the machine status in sync:
     *  - scheduled / in_progress → machine = maintenance
     *  - completed / cancelled   → restore machine to 'idle'
     *                              (unless another active work-order exists)
     */
    public function updated(MaintenanceRecord $record): void
    {
        if (! $record->wasChanged('status')) {
            return;
        }

        $newStatus = $record->status;

        if (in_array($newStatus, self::ACTIVE_STATUSES, true)) {
            $this->setMachineStatus($record->machine_id, 'maintenance');

            return;
        }

        if (in_array($newStatus, self::TERMINAL_STATUSES, true)) {
            $machineName = $record->machine?->name ?? "Machine #{$record->machine_id}";

            if ($newStatus === 'completed') {
                NotificationService::notifyManagers(
                    teamId: $record->team_id,
                    type: NotificationService::TYPE_MAINTENANCE,
                    title: "Maintenance Completed: {$machineName}",
                    message: "Work order '{$record->title}' has been completed for {$machineName}.",
                    alertLevel: NotificationService::LEVEL_INFO,
                    data: [
                        'record_id' => $record->id,
                        'machine' => $machineName,
                        'title' => $record->title,
                        'event' => 'completed',
                    ],
                    actionUrl: '/maintenance',
                );
            }
            // Only restore if no other open work-orders exist for this machine
            $hasActiveRecord = MaintenanceRecord::where('machine_id', $record->machine_id)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->where('id', '!=', $record->id)
                ->exists();

            if (! $hasActiveRecord) {
                $this->setMachineStatus($record->machine_id, 'idle');
            }
        }
    }

    /**
     * Update the machine's operational status, fire the broadcast event,
     * and bust the dashboard/machine cache so the next poll gets fresh data.
     */
    private function setMachineStatus(int $machineId, string $targetStatus): void
    {
        $machine = Machine::find($machineId);

        if (! $machine || $machine->status === $targetStatus) {
            return;
        }

        $oldStatus = $machine->status;

        // withoutEvents() prevents infinite observer loops on the Machine model
        Machine::withoutEvents(function () use ($machine, $targetStatus) {
            $machine->update(['status' => $targetStatus]);
        });

        Log::info('Machine status synced from maintenance record', [
            'machine_id' => $machine->id,
            'old_status' => $oldStatus,
            'new_status' => $targetStatus,
        ]);

        // Broadcast to Fleet / Dashboard WebSocket listeners
        MachineStatusChanged::dispatch($machine, $oldStatus, $targetStatus);

        // Bust dashboard and machine caches so poll-based refreshes see fresh data
        QueryCacheService::invalidateDashboard($machine->team_id);
        QueryCacheService::invalidateMachine($machine->id, $machine->team_id);
    }
}
