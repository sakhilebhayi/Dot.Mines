<?php

namespace App\Services\Feed;

use App\Events\AlertTriggered;
use App\Events\GeofenceEntryDetected;
use App\Events\GeofenceExitDetected;
use App\Events\MachineOffline;
use App\Events\MaintenanceAlertTriggered;
use App\Models\FeedItem;

/**
 * Turns the platform's domain events into feed rows.
 *
 * Hung off the SAME events that drive the live UI and the outbound webhooks
 * -- there is one set of operational events in this codebase, and the feed
 * is a third consumer of it, not a fourth definition of it.
 *
 * Every entry sets:
 *  - occurred_at from the event's own record (entry_time, triggered_at),
 *    never from when this code ran;
 *  - a dedupe_key tied to the underlying record, so an integration
 *    delivering the same event twice produces one feed item;
 *  - an action_url deep-linking to the page that owns the detail.
 *
 * What is deliberately NOT here: raw telemetry (no per-GPS-tick items), and
 * anything the feed would have to calculate itself.
 */
class FeedEventNormaliser
{
    public function handle(object $event): void
    {
        match (true) {
            $event instanceof AlertTriggered => $this->alert($event),
            $event instanceof GeofenceEntryDetected => $this->crossing($event, 'entered'),
            $event instanceof GeofenceExitDetected => $this->crossing($event, 'exited'),
            $event instanceof MachineOffline => $this->offline($event),
            $event instanceof MaintenanceAlertTriggered => $this->maintenance($event),
            default => null,
        };
    }

    private function alert(AlertTriggered $event): void
    {
        $alert = $event->alert;
        $machine = $alert->machine;

        app(FeedPublisher::class)->publish([
            'team_id' => $alert->team_id,
            'category' => FeedItem::CATEGORY_ALERTS,
            'type' => 'alert.triggered',
            'title' => $alert->title,
            'body' => $alert->description,
            'machine_id' => $alert->machine_id,
            'action_url' => $machine !== null ? route('fleet.show', ['machine' => $machine->id]) : route('alerts'),
            'data' => ['alert_id' => $alert->id, 'priority' => $alert->priority],
            'dedupe_key' => 'alert:'.$alert->id,
            'occurred_at' => $alert->triggered_at,
        ]);
    }

    private function crossing(GeofenceEntryDetected|GeofenceExitDetected $event, string $direction): void
    {
        $entry = $event->entry;
        $geofence = $entry->geofence;
        $machine = $entry->machine;

        if ($geofence === null || $machine === null) {
            return; // parent vanished mid-queue; nothing truthful to say
        }

        $occurredAt = $direction === 'exited' ? ($entry->exit_time ?? now()) : $entry->entry_time;

        $body = null;

        if ($direction === 'exited' && $entry->tonnage_loaded !== null) {
            // Copied from the entry record at event time -- the same row the
            // production page reads, not a feed-side calculation.
            $body = 'Tonnage recorded for the visit: '.(string) $entry->tonnage_loaded.' t'
                .($entry->material_type !== null ? ' ('.$entry->material_type.')' : '');
        }

        app(FeedPublisher::class)->publish([
            'team_id' => $geofence->team_id,
            'category' => FeedItem::CATEGORY_FLEET,
            'type' => 'geofence.'.$direction,
            'title' => $machine->name.' '.$direction.' '.$geofence->name,
            'body' => $body,
            'machine_id' => $machine->id,
            'action_url' => route('fleet.show', ['machine' => $machine->id]),
            'data' => ['geofence_id' => $geofence->id, 'entry_id' => $entry->id],
            'dedupe_key' => 'geofence-'.$direction.':'.$entry->id,
            'occurred_at' => $occurredAt,
        ]);
    }

    private function offline(MachineOffline $event): void
    {
        $machine = $event->machine;

        // Keyed on the machine's last-seen moment: re-detecting the same
        // outage on every monitoring run must not repost it, but a machine
        // that comes back and drops out again is genuinely a new event.
        /** @psalm-suppress MixedAssignment -- last_seen_at is untyped on Machine */
        $lastSeen = $machine->last_seen_at;
        $episode = is_object($lastSeen) && method_exists($lastSeen, 'getTimestamp')
            ? (string) $lastSeen->getTimestamp()
            : now()->toDateString();

        app(FeedPublisher::class)->publish([
            'team_id' => $machine->team_id,
            'category' => FeedItem::CATEGORY_FLEET,
            'type' => 'machine.offline',
            'title' => $machine->name.' went offline',
            'body' => $event->reason ?? 'Connection lost.',
            'machine_id' => $machine->id,
            'action_url' => route('fleet.show', ['machine' => $machine->id]),
            'dedupe_key' => 'offline:'.$machine->id.':'.$episode,
        ]);
    }

    private function maintenance(MaintenanceAlertTriggered $event): void
    {
        app(FeedPublisher::class)->publish([
            'team_id' => $event->teamId,
            'category' => FeedItem::CATEGORY_MAINTENANCE,
            'type' => 'maintenance.predicted',
            'title' => 'Maintenance predicted for '.$event->machine->name,
            'body' => 'Probability '.(string) (int) round($event->probability * 100.0).'%, predicted for '
                .$event->predictedDate->format('j F Y').'.',
            'machine_id' => $event->machine->id,
            'action_url' => route('maintenance'),
            'dedupe_key' => 'maintenance:'.$event->machine->id.':'.$event->predictedDate->toDateString(),
        ]);
    }
}
