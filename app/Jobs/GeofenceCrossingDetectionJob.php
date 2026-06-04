<?php

namespace App\Jobs;

use App\Events\GeofenceEntryDetected;
use App\Events\GeofenceExitDetected;
use App\Models\Geofence;
use App\Models\GeofenceEntry;
use App\Models\Machine;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeofenceCrossingDetectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Team $team;

    public int $tries = 2;

    public int $timeout = 90;

    /** @var array<int> */
    public array $backoff = [30, 120]; // 30s, 2 mins

    /**
     * Create a new job instance.
     */
    public function __construct(Team $team)
    {
        $this->team = $team;
        $this->onQueue('geofences');
    }

    /**
     * Execute the job - checks all machines for geofence entries/exits
     * and broadcasts events in real-time.
     */
    public function handle(): void
    {
        Log::info('Starting geofence crossing detection job', [
            'team_id' => $this->team->id,
        ]);

        try {
            $entryCount = 0;
            $exitCount = 0;

            // Get all active geofences for this team
            $geofences = $this->team->geofences()
                ->where('status', 'active')
                ->get();

            if ($geofences->isEmpty()) {
                Log::debug('No active geofences found for team', [
                    'team_id' => $this->team->id,
                ]);

                return;
            }

            // Get all machines with recent locations
            $machines = $this->team->machines()
                ->where('status', '!=', 'offline')
                ->whereNotNull('last_location_latitude')
                ->whereNotNull('last_location_longitude')
                ->get();

            if ($machines->isEmpty()) {
                Log::debug('No machines with locations found', [
                    'team_id' => $this->team->id,
                ]);

                return;
            }

            // Check each machine against each geofence
            foreach ($machines as $machine) {
                foreach ($geofences as $geofence) {
                    $isInside = $this->isPointInPolygon(
                        $machine->last_location_latitude,
                        $machine->last_location_longitude,
                        $geofence->coordinates
                    );

                    // Check last known state
                    $lastEntry = $geofence->entries()
                        ->where('machine_id', $machine->id)
                        ->latest('entry_time')
                        ->first();

                    // Detect entry
                    if ($isInside && (! $lastEntry || $lastEntry->exited_at)) {
                        $entry = $this->recordGeofenceEntry($machine, $geofence);
                        $this->broadcastGeofenceEntry($entry);
                        $entryCount++;
                    }

                    // Detect exit
                    if (! $isInside && $lastEntry && ! $lastEntry->exited_at) {
                        $this->recordGeofenceExit($lastEntry);
                        $this->broadcastGeofenceExit($lastEntry);
                        $exitCount++;
                    }
                }
            }

            Log::info('Geofence crossing detection completed', [
                'team_id' => $this->team->id,
                'entries_detected' => $entryCount,
                'exits_detected' => $exitCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Geofence crossing detection job failed', [
                'team_id' => $this->team->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Determine if a point is inside a polygon using ray casting algorithm.
     *
     * @param  float  $lat  Point latitude
     * @param  float  $lon  Point longitude
     * @param  array  $polygon  Array of coordinates [[lat, lon], [lat, lon], ...]
     */
    private function isPointInPolygon(float $lat, float $lon, array $polygon): bool
    {
        if (empty($polygon) || count($polygon) < 3) {
            return false;
        }

        $inside = false;
        $p1Lat = $polygon[0][0];
        $p1Lon = $polygon[0][1];

        for ($i = 1; $i <= count($polygon); $i++) {
            $p2Lat = $polygon[$i % count($polygon)][0];
            $p2Lon = $polygon[$i % count($polygon)][1];

            if ($lat > min($p1Lat, $p2Lat)) {
                if ($lat <= max($p1Lat, $p2Lat)) {
                    if ($lon <= max($p1Lon, $p2Lon)) {
                        $xinters = ($lat - $p1Lat) * ($p2Lon - $p1Lon) / ($p2Lat - $p1Lat) + $p1Lon;
                        if ($p1Lon == $p2Lon || $lon <= $xinters) {
                            $inside = ! $inside;
                        }
                    }
                }
            }

            $p1Lat = $p2Lat;
            $p1Lon = $p2Lon;
        }

        return $inside;
    }

    /**
     * Record a geofence entry in the database.
     */
    private function recordGeofenceEntry(Machine $machine, Geofence $geofence): GeofenceEntry
    {
        $entry = GeofenceEntry::create([
            'team_id' => $this->team->id,
            'geofence_id' => $geofence->id,
            'machine_id' => $machine->id,
            'entry_time' => now(),
            'entry_latitude' => $machine->last_location_latitude,
            'entry_longitude' => $machine->last_location_longitude,
        ]);

        Log::debug('Recorded geofence entry', [
            'entry_id' => $entry->id,
            'machine_id' => $machine->id,
            'geofence_id' => $geofence->id,
        ]);

        return $entry;
    }

    /**
     * Record a geofence exit in the database.
     */
    private function recordGeofenceExit(GeofenceEntry $entry): void
    {
        $machine = $entry->machine;

        $entry->update([
            'exit_time' => now(),
            'exit_latitude' => $machine->last_location_latitude,
            'exit_longitude' => $machine->last_location_longitude,
        ]);

        Log::debug('Recorded geofence exit', [
            'entry_id' => $entry->id,
            'machine_id' => $machine->id,
            'duration_minutes' => $entry->entry_time->diffInMinutes(now()),
        ]);
    }

    /**
     * Broadcast geofence entry event to all team members.
     */
    private function broadcastGeofenceEntry(GeofenceEntry $entry): void
    {
        event(new GeofenceEntryDetected($entry));

        Log::debug('Broadcasted geofence entry event', [
            'entry_id' => $entry->id,
            'geofence_id' => $entry->geofence_id,
        ]);
    }

    /**
     * Broadcast geofence exit event to all team members.
     */
    private function broadcastGeofenceExit(GeofenceEntry $entry): void
    {
        event(new GeofenceExitDetected($entry));

        Log::debug('Broadcasted geofence exit event', [
            'entry_id' => $entry->id,
            'geofence_id' => $entry->geofence_id,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Geofence crossing detection job permanently failed', [
            'team_id' => $this->team->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
