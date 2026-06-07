<?php

namespace App\Services;

use App\Events\MapEventRecorded;
use App\Models\Machine;
use App\Models\MapEvent;

/**
 * MapEventService
 *
 * Central helper for recording operational events that appear on the Live Map.
 * Every call persists a MapEvent row and broadcasts in real time via Reverb.
 *
 * Usage example (from an Observer, Job, or controller):
 *
 *   MapEventService::record(
 *       teamId:     $machine->team_id,
 *       machineId:  $machine->id,
 *       eventType:  MapEvent::TYPE_BREAKDOWN,
 *       title:      "Engine fault – {$machine->name}",
 *       latitude:   $machine->last_location_latitude,
 *       longitude:  $machine->last_location_longitude,
 *       notes:      'Fault code: P0301',
 *       metadata:   ['fault_code' => 'P0301'],
 *   );
 */
class MapEventService
{
    /**
     * Record a map event and broadcast it immediately.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public static function record(
        int $teamId,
        string $eventType,
        string $title,
        ?int $machineId = null,
        ?int $mineAreaId = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $notes = null,
        ?array $metadata = null,
    ): MapEvent {
        // If no explicit coords, try to pull from the machine's last known loc
        if (($latitude === null || $longitude === null) && $machineId !== null) {
            $machine = Machine::select(['last_location_latitude', 'last_location_longitude'])
                ->find($machineId);

            $latitude = $latitude ?? $machine?->last_location_latitude;
            $longitude = $longitude ?? $machine?->last_location_longitude;
        }

        $event = MapEvent::create([
            'team_id' => $teamId,
            'machine_id' => $machineId,
            'mine_area_id' => $mineAreaId,
            'event_type' => $eventType,
            'title' => $title,
            'notes' => $notes,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'occurred_at' => now(),
            'metadata' => $metadata,
        ]);

        // Eager-load relationships used in broadcastWith()
        $event->load(['machine', 'mineArea']);

        broadcast(new MapEventRecorded($event))->toOthers();

        return $event;
    }

    /**
     * Auto-derive and record a status_change event when a machine's status
     * transitions. Convenience wrapper called from MachineStatusChanged listener.
     */
    public static function recordStatusChange(
        Machine $machine,
        string $oldStatus,
        string $newStatus,
    ): MapEvent {
        $labels = [
            'active' => 'Active',
            'idle' => 'Idle',
            'maintenance' => 'Under Maintenance',
            'offline' => 'Offline',
        ];

        $statusLabel = $labels[$newStatus] ?? $newStatus;

        return self::record(
            teamId: $machine->team_id,
            eventType: MapEvent::TYPE_STATUS_CHANGE,
            title: "{$machine->name} -> {$statusLabel}",
            machineId: $machine->id,
            metadata: ['old_status' => $oldStatus, 'new_status' => $newStatus],
        );
    }
}
