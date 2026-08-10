<?php

namespace Tests\Unit;

use App\Jobs\SyncMachineMetricsJob;
use App\Models\Integration;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\Team;
use App\Services\Integration\CATService;
use App\Services\Integration\EpirocService;
use App\Services\Integration\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

/**
 * SyncMachineMetricsJob and IntegrationService::syncMachineMetrics() both
 * mass-assign whatever a manufacturer service's fetchMachineMetrics()
 * returns directly onto a MachineMetric -- they expect one flat array of
 * its own fillable columns. Two real, systemic bugs meant this never
 * actually worked for any of the 16 "available" manufacturers:
 *
 * 1. CAT/Komatsu/Volvo/Kawasaki built a *list* of {type, value, unit,
 *    timestamp} readings and returned it directly -- mass-assigning a list
 *    against named columns silently creates an almost-empty row (just
 *    team_id/machine_id), for every sync, forever. Fixed with
 *    normalizeMetricsForStorage(), tested here directly via reflection
 *    (see the note on CATService below for why not through its full HTTP
 *    call chain).
 * 2. The other 12 (Epiroc, Hitachi, JCB, Doosan, Roundebult, Kubota,
 *    Hyundai, Kobelco, XCMG, Sany, Liebherr, CTrack) call parseMetrics()
 *    2-3 times (one per API endpoint) and combined the results with a
 *    plain array_merge() -- since parseMetrics() always returns the same
 *    set of keys, only the *last* source's values ever survived; the
 *    earlier ones were silently overwritten. Three of parseMetrics()'s own
 *    key names ('timestamp'/'engine_temp'/'fuel_consumption'/'coolant_temp')
 *    also didn't match any real MachineMetric column at all. Fixed with
 *    mergeMetricsPreferNonNull() plus the renamed keys, tested here
 *    end-to-end through Epiroc's real HTTP call chain.
 */
class ManufacturerMetricsShapeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * normalizeMetricsForStorage() and mergeMetricsPreferNonNull() are
     * protected (shared helpers on BaseManufacturerService, not part of the
     * public ManufacturerServiceInterface) -- invoked via reflection rather
     * than through a manufacturer's full HTTP call chain. This is
     * deliberate: while auditing this fix, CATService's own fetchMachines()/
     * fetchMetrics() turned out to have a *separate*, pre-existing bug --
     * they read response keys (e.g. $diagnostics['diagnostics']) at the
     * wrong nesting level against makeRequest()'s own {success, data,
     * status} wrapper, so they always return empty regardless of this fix.
     * That's a real, further finding (affects CAT/Komatsu/Volvo/Kawasaki's
     * fetchMachines() too, not just metrics), but fixing it would mean
     * guessing at how each fake stub's *assumed* upstream API is shaped --
     * exactly the kind of guess this whole effort has been about removing,
     * not reintroducing. Left as a known, reported, separately-scoped gap.
     */
    private function invokeProtected(object $service, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($service, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($service, $args);
    }

    public function test_normalize_metrics_for_storage_flattens_a_list_of_readings(): void
    {
        $service = new CATService(['base_url' => 'https://api.example.test']);

        $readings = [
            ['type' => 'temp_sensor', 'value' => 88, 'unit' => 'C', 'timestamp' => '2026-06-01T10:00:00Z'],
            ['type' => 'fuel_used', 'value' => 450, 'unit' => 'liters', 'timestamp' => '2026-06-01T12:00:00Z'],
        ];

        $result = $this->invokeProtected($service, 'normalizeMetricsForStorage', [$readings]);

        // Must be a single flat array a MachineMetric can actually be built
        // from -- not the list (integer-keyed) that was passed in.
        $this->assertArrayHasKey('recorded_at', $result);
        $this->assertArrayHasKey('raw_data', $result);
        $this->assertArrayNotHasKey(0, $result, 'Should not still be a list -- that was the bug.');
        // Nothing from the real readings is silently dropped -- preserved
        // in raw_data even though we don't guess a column mapping for each
        // manufacturer's own invented reading "type" names.
        $this->assertCount(2, $result['raw_data']);
        // recorded_at is the *latest* reading's own timestamp, not just now().
        $this->assertTrue($result['recorded_at']->equalTo('2026-06-01T12:00:00Z'));
    }

    public function test_normalize_metrics_for_storage_handles_an_empty_list(): void
    {
        $service = new CATService(['base_url' => 'https://api.example.test']);

        $this->assertSame([], $this->invokeProtected($service, 'normalizeMetricsForStorage', [[]]));
    }

    public function test_merge_metrics_prefer_non_null_does_not_lose_data_to_array_merge_clobbering(): void
    {
        $service = new CATService(['base_url' => 'https://api.example.test']);

        // Same shape parseMetrics() always returns -- each source only has
        // one real (non-null) field, matching how a real manufacturer API
        // split across separate performance/production/maintenance calls.
        $performance = ['recorded_at' => '2026-06-01T10:00:00Z', 'operating_hours' => 120, 'fuel_level' => null, 'engine_temperature' => null, 'raw_data' => ['source' => 'performance']];
        $production = ['recorded_at' => '2026-06-01T11:00:00Z', 'operating_hours' => null, 'fuel_level' => 65, 'engine_temperature' => null, 'raw_data' => ['source' => 'production']];
        $maintenance = ['recorded_at' => '2026-06-01T12:00:00Z', 'operating_hours' => null, 'fuel_level' => null, 'engine_temperature' => 95, 'raw_data' => ['source' => 'maintenance']];

        $merged = $this->invokeProtected($service, 'mergeMetricsPreferNonNull', [$performance, $production, $maintenance]);

        // A plain array_merge() of these three would have left only
        // maintenance's values (the last one) -- operating_hours and
        // fuel_level would have been silently lost.
        $this->assertSame(120, $merged['operating_hours']);
        $this->assertSame(65, $merged['fuel_level']);
        $this->assertSame(95, $merged['engine_temperature']);
        // raw_data is unioned, not overwritten by the last source either.
        $this->assertCount(3, $merged['raw_data']);
    }

    public function test_epiroc_service_does_not_lose_data_to_array_merge_clobbering_end_to_end(): void
    {
        Http::fake([
            '*/performance' => Http::response(['operating_hours' => 120], 200),
            '*/production' => Http::response(['fuel_level' => 65], 200),
            '*/maintenance' => Http::response(['engine_temp' => 95], 200),
        ]);

        $service = new EpirocService(['base_url' => 'https://api.example.test', 'api_key' => 'k']);
        $result = $service->fetchMetrics('M1');
        $metrics = $result['metrics'];

        $this->assertSame(120.0, (float) $metrics['operating_hours']);
        $this->assertSame(65.0, (float) $metrics['fuel_level']);
        // 'engine_temp' isn't a real MachineMetric column -- 'engine_temperature' is.
        $this->assertSame(95.0, (float) $metrics['engine_temperature']);
        $this->assertArrayNotHasKey('engine_temp', $metrics);
        $this->assertArrayNotHasKey('timestamp', $metrics, "parseMetrics() used 'timestamp'; the real column is 'recorded_at'.");
        $this->assertArrayHasKey('recorded_at', $metrics);
    }

    /**
     * End-to-end through the real sync job, not just the service in
     * isolation: confirms a real, non-empty MachineMetric row actually
     * reaches the database now.
     */
    public function test_sync_machine_metrics_job_creates_a_real_populated_row_for_epiroc(): void
    {
        Http::fake([
            '*/performance' => Http::response(['operating_hours' => 300], 200),
            '*/production' => Http::response([], 200),
            '*/maintenance' => Http::response([], 200),
        ]);

        $team = Team::factory()->create();
        $integration = Integration::factory()->forProvider('epiroc')->create([
            'team_id' => $team->id,
            'status' => 'connected',
            'credentials' => ['api_key' => 'k', 'base_url' => 'https://api.example.test'],
        ]);
        $machine = Machine::factory()->create([
            'team_id' => $team->id,
            'manufacturer' => 'epiroc',
            'manufacturer_id' => 'M1',
            'integration_id' => $integration->id,
        ]);

        (new SyncMachineMetricsJob($machine))->handle(app(IntegrationService::class));

        $metric = MachineMetric::where('machine_id', $machine->id)->first();
        $this->assertNotNull($metric, 'A real MachineMetric row should have been created.');
        $this->assertSame(300.0, $metric->operating_hours);
        $this->assertNotNull($metric->recorded_at);
    }
}
