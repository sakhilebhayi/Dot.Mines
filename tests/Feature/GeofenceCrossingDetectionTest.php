<?php

namespace Tests\Feature;

use App\Jobs\GeofenceCrossingDetectionJob;
use App\Models\Geofence;
use App\Models\GeofenceEntry;
use App\Models\Machine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * R9 audit coverage for GeofenceCrossingDetectionJob. Two of its bugs were
 * fixed with no test pinning them:
 * - it checked a phantom exited_at attribute (real column exit_time), so
 *   re-entries were never recorded and exits re-fired on every poll;
 * - isPointInPolygon() only read [lat, lng] indexed vertex pairs, while
 *   every zone the app itself creates (Geofence Manager map draws,
 *   confirmed GPS zone suggestions) stores {lat, lng} objects -- so
 *   crossings in those zones could never fire at all.
 */
class GeofenceCrossingDetectionTest extends TestCase
{
    use RefreshDatabase;

    /** @var array{team: Team, machine: Machine, geofence: Geofence} */
    private array $world;

    private function buildWorld(mixed $coordinates): void
    {
        Event::fake(); // broadcast events are not under test

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
            'last_location_latitude' => -26.0050,
            'last_location_longitude' => 28.0050,
        ]);

        $geofence = Geofence::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
            'coordinates' => $coordinates,
        ]);

        $this->world = ['team' => $team, 'machine' => $machine, 'geofence' => $geofence];
    }

    /**
     * A square around (-26.005, 28.005) in the {lat, lng} object shape the
     * app's own zone producers write.
     *
     * @return list<array{lat: float, lng: float}>
     */
    private function objectSquare(): array
    {
        return [
            ['lat' => -26.0100, 'lng' => 28.0000],
            ['lat' => -26.0100, 'lng' => 28.0100],
            ['lat' => -26.0000, 'lng' => 28.0100],
            ['lat' => -26.0000, 'lng' => 28.0000],
            ['lat' => -26.0100, 'lng' => 28.0000],
        ];
    }

    public function test_object_shaped_zone_coordinates_detect_an_entry(): void
    {
        $this->buildWorld($this->objectSquare());

        (new GeofenceCrossingDetectionJob($this->world['team']))->handle();

        $this->assertSame(1, GeofenceEntry::withoutGlobalScopes()->count(), 'The {lat, lng} object shape is what the app itself stores; it must register crossings.');
        $entry = GeofenceEntry::withoutGlobalScopes()->first();
        $this->assertSame($this->world['machine']->id, $entry->machine_id);
        $this->assertNull($entry->exit_time);
    }

    public function test_indexed_pair_zone_coordinates_also_detect_an_entry(): void
    {
        $this->buildWorld([
            [-26.0100, 28.0000],
            [-26.0100, 28.0100],
            [-26.0000, 28.0100],
            [-26.0000, 28.0000],
            [-26.0100, 28.0000],
        ]);

        (new GeofenceCrossingDetectionJob($this->world['team']))->handle();

        $this->assertSame(1, GeofenceEntry::withoutGlobalScopes()->count());
    }

    public function test_a_full_entry_exit_reentry_cycle_produces_two_entries_and_one_exit(): void
    {
        $this->buildWorld($this->objectSquare());
        $team = $this->world['team'];
        $machine = $this->world['machine'];

        // Inside: opens the first entry. A second run while still inside
        // must not duplicate it.
        (new GeofenceCrossingDetectionJob($team))->handle();
        (new GeofenceCrossingDetectionJob($team))->handle();
        $this->assertSame(1, GeofenceEntry::withoutGlobalScopes()->count());

        // Outside: closes it. A second run outside must not touch it again
        // (the phantom exited_at check used to re-fire the exit every poll).
        $machine->update(['last_location_latitude' => -26.5, 'last_location_longitude' => 28.5]);
        (new GeofenceCrossingDetectionJob($team))->handle();
        $closed = GeofenceEntry::withoutGlobalScopes()->first();
        $this->assertNotNull($closed->exit_time);
        $firstExitTime = $closed->exit_time;

        $this->travel(5)->minutes();
        (new GeofenceCrossingDetectionJob($team))->handle();
        $this->assertSame(1, GeofenceEntry::withoutGlobalScopes()->count());
        $this->assertEquals($firstExitTime, GeofenceEntry::withoutGlobalScopes()->first()->exit_time, 'A closed entry must not have its exit re-stamped on later polls.');

        // Back inside: a NEW entry row (re-entries were never recorded
        // under the phantom-attribute check).
        $machine->update(['last_location_latitude' => -26.0050, 'last_location_longitude' => 28.0050]);
        (new GeofenceCrossingDetectionJob($team))->handle();

        $this->assertSame(2, GeofenceEntry::withoutGlobalScopes()->count(), 'Re-entering a zone must open a second ledger row.');
        $this->assertSame(1, GeofenceEntry::withoutGlobalScopes()->whereNull('exit_time')->count());
    }
}
