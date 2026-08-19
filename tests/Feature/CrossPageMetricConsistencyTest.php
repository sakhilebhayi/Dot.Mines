<?php

namespace Tests\Feature;

use App\Livewire\ProductionDashboard;
use App\Models\Machine;
use App\Models\MachineMetric;
use App\Models\ProductionRecord;
use App\Models\Report;
use App\Models\Team;
use App\Models\User;
use App\Services\FuelManagementService;
use App\Services\MachinePerformanceService;
use App\Services\Reports\ReportDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Global consistency pass (truth-loop step 12): one machine, one day of
 * telemetry and production -- every surface that reports its numbers must
 * agree, because they all derive from the same single sources of truth:
 *
 * - Engine hours: cumulative-meter DELTA (MachinePerformanceService
 *   semantics), consumed by Fleet, utilization reports, fuel efficiency,
 *   and maintenance risk. Never a sum of meter readings.
 * - Loads/cycles/tonnes: ProductionRecord rows (telemetry metadata),
 *   consumed by the production dashboard and production reports.
 */
class CrossPageMetricConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Team, 1: Machine}
     */
    private function machineWithOneDayOfData(): array
    {
        $team = Team::factory()->create();
        $machine = Machine::factory()->create(['team_id' => $team->id, 'name' => 'ADT-42', 'status' => 'active']);

        // Cumulative meters: 8 engine hours, 2 idle hours today.
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'operating_hours' => 1000.0,
            'idle_hours' => 300.0,
            'recorded_at' => now()->startOfDay()->addHours(6),
        ]);
        MachineMetric::factory()->create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'operating_hours' => 1008.0,
            'idle_hours' => 302.0,
            'recorded_at' => now()->startOfDay()->addHours(16),
        ]);

        ProductionRecord::create([
            'team_id' => $team->id,
            'machine_id' => $machine->id,
            'record_date' => now()->toDateString(),
            'shift' => 'continuous',
            'quantity_produced' => 640.0,
            'unit' => 'tonnes',
            'status' => 'completed',
            'metadata' => ['source' => 'telemetry', 'provider' => 'bell', 'loads' => 40, 'cycles' => 40],
        ]);

        return [$team, $machine];
    }

    public function test_engine_hours_agree_across_performance_service_utilization_report_and_fuel_metrics(): void
    {
        [$team, $machine] = $this->machineWithOneDayOfData();

        // 1. MachinePerformanceService -- the canonical source Fleet and the
        //    AI agents consume.
        $performance = collect(app(MachinePerformanceService::class)->dailyPerformanceForTeam($team->id))
            ->firstWhere('machine_id', $machine->id);
        $this->assertNotNull($performance);
        $this->assertEqualsWithDelta(8.0, $performance['operating_hours_today'], 0.01);

        // 2. Fleet utilization report.
        $report = Report::create([
            'team_id' => $team->id,
            'title' => 'Utilization',
            'type' => 'fleet_utilization',
            'format' => 'csv',
            'status' => 'pending',
            'filters' => ['start_date' => now()->toDateString(), 'end_date' => now()->toDateString()],
        ]);
        $reportData = app(ReportDataService::class)->build($report);
        $row = collect($reportData['rows'])->firstWhere(0, 'ADT-42');
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(8.0, $row[3], 0.01, 'Utilization report must agree with MachinePerformanceService.');

        // 3. Daily fuel consumption metric.
        $fuelMetric = app(FuelManagementService::class)->calculateDailyMetrics($machine, now());
        $this->assertEqualsWithDelta(8.0, $fuelMetric->operating_hours, 0.01, 'Fuel efficiency must divide by the same day-hours Fleet shows.');
        $this->assertEqualsWithDelta(2.0, $fuelMetric->idle_time_hours, 0.01, 'Idle hours come from the idle_hours meter delta (idle_time never existed).');
    }

    public function test_loads_and_tonnes_agree_between_performance_service_production_summary_and_report(): void
    {
        [$team, $machine] = $this->machineWithOneDayOfData();

        // 1. MachinePerformanceService (Fleet cards, AI agents).
        $performance = collect(app(MachinePerformanceService::class)->dailyPerformanceForTeam($team->id))
            ->firstWhere('machine_id', $machine->id);
        $this->assertSame(40, $performance['loads_today']);
        $this->assertEqualsWithDelta(640.0, $performance['tonnes_today'], 0.01);

        // 2. Production report totals.
        $report = Report::create([
            'team_id' => $team->id,
            'title' => 'Production',
            'type' => 'production',
            'format' => 'csv',
            'status' => 'pending',
            'filters' => ['start_date' => now()->toDateString(), 'end_date' => now()->toDateString()],
        ]);
        $reportData = app(ReportDataService::class)->build($report);
        $this->assertEqualsWithDelta(640.0, $reportData['summary']['Total Produced'], 0.01);

        // 3. Production dashboard summary (loads from telemetry metadata via
        //    ProductionService::recordLoads -- the single derivation).
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user);
        $summary = Livewire::actingAs($user)
            ->test(ProductionDashboard::class)
            ->instance()
            ->summary;
        $this->assertSame(40, $summary['total_loads']);
        $this->assertEqualsWithDelta(640.0, $summary['total_tonnage'], 0.01);
    }
}
