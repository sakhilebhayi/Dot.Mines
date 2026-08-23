<?php

namespace App\Services\Webhooks;

use App\Events\AlertTriggered;
use App\Events\GeofenceEntryDetected;
use App\Events\GeofenceExitDetected;
use App\Events\MachineOffline;
use App\Events\MaintenanceAlertTriggered;
use App\Http\Resources\AlertResource;
use App\Http\Resources\MachineResource;
use App\Models\GeofenceEntry;
use App\Support\ApiPayload;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The catalogue of events a webhook can subscribe to, and how each domain
 * event becomes a payload.
 *
 * Every event listed here is one the application genuinely dispatches today.
 * That constraint is the whole point: an event catalogue that advertises
 * something nothing fires is worse than a short catalogue, because the
 * integrator builds a handler, sees nothing arrive, and cannot tell whether
 * they got it wrong or we did. (ComplianceViolationDetected has a broadcast
 * channel but no dispatch site anywhere in the app, so it is deliberately not
 * offered.)
 *
 * Payloads reuse the API Resources wherever one exists, so the object in a
 * webhook is the same shape, with the same field names, as the object from
 * the REST endpoint. One parser works for both.
 */
final class WebhookEvent
{
    public const ALERT_TRIGGERED = 'alert.triggered';

    public const GEOFENCE_ENTERED = 'geofence.entered';

    public const GEOFENCE_EXITED = 'geofence.exited';

    public const MACHINE_OFFLINE = 'machine.offline';

    public const MAINTENANCE_PREDICTED = 'maintenance.predicted';

    /**
     * Event name => what it means, for the docs and the subscription UI.
     *
     * @var array<string, string>
     */
    public const CATALOGUE = [
        self::ALERT_TRIGGERED => 'An operational alert was raised against a machine or mine area.',
        self::GEOFENCE_ENTERED => 'A machine entered a geofenced zone.',
        self::GEOFENCE_EXITED => 'A machine left a geofenced zone, with the tonnage recorded for the visit.',
        self::MACHINE_OFFLINE => 'A machine stopped reporting and was marked offline.',
        self::MAINTENANCE_PREDICTED => 'Maintenance was predicted for a machine, with a probability and a date.',
    ];

    /**
     * The domain events that produce webhooks, in dispatch order of nothing
     * in particular -- this is a lookup, not a sequence.
     *
     * @var array<class-string, string>
     */
    public const SOURCES = [
        AlertTriggered::class => self::ALERT_TRIGGERED,
        GeofenceEntryDetected::class => self::GEOFENCE_ENTERED,
        GeofenceExitDetected::class => self::GEOFENCE_EXITED,
        MachineOffline::class => self::MACHINE_OFFLINE,
        MaintenanceAlertTriggered::class => self::MAINTENANCE_PREDICTED,
    ];

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::CATALOGUE);
    }

    /**
     * The team an event belongs to, or null when it cannot be established.
     *
     * Returning null drops the event rather than guessing: a webhook
     * delivered to the wrong team is a data breach, and every one of these
     * carries a machine or a record that knows its own team.
     */
    public static function teamIdFor(object $event): ?int
    {
        return match (true) {
            $event instanceof AlertTriggered => $event->alert->team_id,
            $event instanceof GeofenceEntryDetected => $event->entry->geofence?->team_id,
            $event instanceof GeofenceExitDetected => $event->entry->geofence?->team_id,
            $event instanceof MachineOffline => $event->machine->team_id,
            $event instanceof MaintenanceAlertTriggered => $event->teamId,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function dataFor(object $event): ?array
    {
        return match (true) {
            $event instanceof AlertTriggered => self::fields(AlertResource::make($event->alert->loadMissing('machine'))),
            $event instanceof GeofenceEntryDetected => self::crossing($event->entry, 'entered'),
            $event instanceof GeofenceExitDetected => self::crossing($event->entry, 'exited'),
            $event instanceof MachineOffline => self::offline($event),
            $event instanceof MaintenanceAlertTriggered => self::maintenance($event),
            default => null,
        };
    }

    /**
     * An API Resource's fields, typed as the string-keyed shape it always is.
     *
     * JsonResource::resolve() is declared as array<array-key, mixed>, which
     * is true of the base class and never true of ours.
     *
     * @return array<string, mixed>
     */
    private static function fields(JsonResource $resource): array
    {
        /** @var array<string, mixed> */
        return $resource->resolve();
    }

    /**
     * @return array<string, mixed>
     */
    private static function crossing(GeofenceEntry $entry, string $direction): array
    {
        $entry->loadMissing(['geofence', 'machine']);

        return [
            'id' => $entry->id,
            'direction' => $direction,
            'machine' => $entry->machine === null ? null : [
                'id' => $entry->machine->id,
                'name' => $entry->machine->name,
            ],
            'geofence' => $entry->geofence === null ? null : [
                'id' => $entry->geofence->id,
                'name' => $entry->geofence->name,
                'type' => $entry->geofence->type,
            ],
            'entry_time' => $entry->entry_time->toIso8601String(),
            'exit_time' => $entry->exit_time?->toIso8601String(),
            'tonnage_loaded' => $entry->tonnage_loaded,
            'material_type' => $entry->material_type,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function offline(MachineOffline $event): array
    {
        return [
            'machine' => self::fields(MachineResource::make($event->machine)),
            'reason' => $event->reason ?? 'Connection lost',
            'last_seen_at' => ApiPayload::iso($event->machine->last_seen_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function maintenance(MaintenanceAlertTriggered $event): array
    {
        return [
            'machine' => self::fields(MachineResource::make($event->machine)),
            'probability' => round($event->probability, 2),
            'predicted_date' => $event->predictedDate->toIso8601String(),
            'severity' => match (true) {
                $event->probability >= 0.8 => 'critical',
                $event->probability >= 0.6 => 'high',
                default => 'medium',
            },
        ];
    }
}
